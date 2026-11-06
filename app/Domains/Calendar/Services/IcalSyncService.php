<?php

declare(strict_types=1);

namespace App\Domains\Calendar\Services;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Calendar\Models\AvailabilityCalendar;
use App\Domains\Calendar\Models\ExternalIcalFeed;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class IcalSyncService
{
    /**
     * Maximum allowed size for downloaded iCal feeds (2 MB).
     */
    public const int MAX_FEED_SIZE_BYTES = 2 * 1024 * 1024;

    /**
     * HTTP request timeout for external feeds in seconds.
     */
    public const int SYNC_TIMEOUT_SECONDS = 10;

    /**
     * Validate external feed URL to prevent Server-Side Request Forgery (SSRF).
     * Strictly blocks private IPs, loopbacks, link-local metadata endpoints, and invalid protocols.
     */
    public static function validateExternalUrl(string $url): void
    {
        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
            throw new InvalidArgumentException('Invalid external calendar URL format.');
        }

        $scheme = strtolower($parsed['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException("Unsupported URL protocol: {$scheme}. Only HTTP/HTTPS are permitted.");
        }

        $host = strtolower($parsed['host']);

        // Check for local names
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true) || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new InvalidArgumentException("Feed URL host '{$host}' is an internal address and cannot be accessed.");
        }

        // Resolve DNS and inspect target IPs
        $resolvedIps = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : gethostbynamel($host);
        if ($resolvedIps === false || $resolvedIps === []) {
            throw new InvalidArgumentException("Unable to resolve feed host '{$host}'.");
        }

        foreach ($resolvedIps as $ip) {
            // Reject loopback and AWS/GCP/Azure link-local metadata (169.254.169.254)
            if (str_starts_with($ip, '127.') || str_starts_with($ip, '169.254.') || str_starts_with($ip, '0.')) {
                throw new InvalidArgumentException("Target IP {$ip} is restricted (loopback or link-local metadata).");
            }

            // Reject private and reserved IP ranges (RFC 1918 / RFC 4193)
            $isPublic = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );

            if ($isPublic === false) {
                throw new InvalidArgumentException("Target IP {$ip} is a private/internal network address.");
            }
        }
    }

    /**
     * Check whether an external feed URL is safe without throwing exceptions.
     */
    public static function isUrlAllowed(string $url): bool
    {
        try {
            self::validateExternalUrl($url);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Parses RFC 5545 VCALENDAR string, unfolding lines and extracting booked/blocked date ranges [start_date, end_date).
     *
     * @return array<int, array{uid: string, summary: string, start_date: string, end_date: string}>
     */
    public static function parseIncomingIcs(string $icsContent): array
    {
        // 1. Unfold continuation lines (RFC 5545 Section 3.1)
        $unfolded = preg_replace("/\r?\n[ \t]/", '', $icsContent) ?? $icsContent;

        $events = [];
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/si', $unfolded, $matches);

        foreach ($matches[1] ?? [] as $block) {
            preg_match('/UID:(?:[^\r\n]*?)?([^\r\n]+)/i', $block, $uidMatch);
            preg_match('/SUMMARY:(?:[^\r\n]*?)?([^\r\n]+)/i', $block, $sumMatch);

            // Match DTSTART: DATE (8 digits) or DATETIME (e.g. 20261101T140000Z)
            preg_match('/DTSTART(?:;[^:]*)?:(\d{8}(?:T\d{6}Z?)?)/i', $block, $startMatch);
            // Match DTEND: DATE or DATETIME
            preg_match('/DTEND(?:;[^:]*)?:(\d{8}(?:T\d{6}Z?)?)/i', $block, $endMatch);

            if (! empty($startMatch[1])) {
                $rawStart = substr($startMatch[1], 0, 8);
                $rawEnd = ! empty($endMatch[1]) ? substr($endMatch[1], 0, 8) : null;

                try {
                    $start = CarbonImmutable::createFromFormat('Ymd', $rawStart)->toDateString();
                    // If no DTEND is given, RFC 5545 defines event duration as 1 day
                    $end = $rawEnd
                        ? CarbonImmutable::createFromFormat('Ymd', $rawEnd)->toDateString()
                        : CarbonImmutable::createFromFormat('Ymd', $rawStart)->addDay()->toDateString();

                    if ($end > $start) {
                        $rawSummary = trim($sumMatch[1] ?? 'External Reservation');
                        // Unescape RFC 5545 text
                        $summary = str_replace(['\,', '\;', '\\\\', '\n', '\N'], [',', ';', '\\', "\n", "\n"], $rawSummary);

                        $events[] = [
                            'uid' => trim($uidMatch[1] ?? uniqid('ical_', true)),
                            'summary' => $summary,
                            'start_date' => $start,
                            'end_date' => $end,
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::warning('[IcalSyncService] Malformed event date in block: '.$e->getMessage());
                }
            }
        }

        return $events;
    }

    /**
     * Fetch external calendar feed securely and sync blocked dates into availability_calendars.
     *
     * @return array{events_count: int, dates_blocked: int}
     */
    public static function syncFeed(ExternalIcalFeed $feed): array
    {
        if (empty($feed->import_ical_url)) {
            throw new InvalidArgumentException('Feed has no import URL configured.');
        }

        self::validateExternalUrl($feed->import_ical_url);

        try {
            $response = Http::timeout(self::SYNC_TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => 'EthioProperty-CalendarSync/1.0'])
                ->get($feed->import_ical_url);

            if (! $response->successful()) {
                throw new InvalidArgumentException("HTTP error {$response->status()} while fetching calendar feed.");
            }

            $body = $response->body();
            if (strlen($body) > self::MAX_FEED_SIZE_BYTES) {
                throw new InvalidArgumentException('Feed size exceeds maximum limit of 2MB.');
            }

            $events = self::parseIncomingIcs($body);

            $datesBlocked = DB::transaction(function () use ($feed, $events): int {
                $unitId = $feed->accommodation_unit_id;
                $feedTag = "iCal [feed:{$feed->id}]";

                // Collect incoming blocked dates
                $incomingDates = [];
                foreach ($events as $event) {
                    $cursor = CarbonImmutable::parse($event['start_date']);
                    $end = CarbonImmutable::parse($event['end_date']);

                    while ($cursor < $end) {
                        $d = $cursor->toDateString();
                        $incomingDates[$d] = $event['summary'];
                        $cursor = $cursor->addDay();
                    }
                }

                // 1. Revert dates previously blocked by this feed that are no longer in the imported feed
                AvailabilityCalendar::query()
                    ->where('accommodation_unit_id', $unitId)
                    ->where('status', 'external_ical_blocked')
                    ->where('block_reason', 'LIKE', "{$feedTag}%")
                    ->whereNotIn('calendar_date', array_keys($incomingDates))
                    ->update([
                        'status' => 'available',
                        'block_reason' => null,
                        'updated_at' => now(),
                    ]);

                $blockedCount = 0;

                // 2. Apply incoming dates, NEVER overwriting local confirmed or held bookings
                foreach ($incomingDates as $date => $summary) {
                    $existing = AvailabilityCalendar::query()
                        ->where('accommodation_unit_id', $unitId)
                        ->where('calendar_date', $date)
                        ->first();

                    if ($existing && in_array($existing->status, ['booked', 'held_pending_payment'], true)) {
                        // Preserved: Local confirmed bookings and holds ALWAYS take precedence
                        continue;
                    }

                    AvailabilityCalendar::query()->updateOrCreate(
                        [
                            'accommodation_unit_id' => $unitId,
                            'calendar_date' => $date,
                        ],
                        [
                            'status' => 'external_ical_blocked',
                            'block_reason' => "{$feedTag} {$feed->channel_name}: {$summary}",
                            'booking_id' => null,
                        ]
                    );

                    $blockedCount++;
                }

                $feed->update([
                    'last_synced_at' => now(),
                    'last_sync_status' => 'success',
                ]);

                return $blockedCount;
            });

            return [
                'events_count' => count($events),
                'dates_blocked' => $datesBlocked,
            ];
        } catch (\Throwable $e) {
            $feed->update([
                'last_synced_at' => now(),
                'last_sync_status' => 'failed: '.substr($e->getMessage(), 0, 200),
            ]);

            throw $e;
        }
    }

    /**
     * Export an RFC 5545 feed by its opaque export token without exposing guest private data.
     */
    public static function exportFeedByToken(string $token): string
    {
        $feed = ExternalIcalFeed::query()->where('export_token', $token)->firstOrFail();
        $unit = $feed->unit()->with('property')->firstOrFail();
        $propertyCode = $unit->property?->property_code ?? "UNIT-{$unit->id}";

        // Confirmed bookings on this unit
        $bookings = Booking::query()
            ->where('accommodation_unit_id', $unit->id)
            ->whereIn('status', [
                BookingStatus::Confirmed->value,
                BookingStatus::CheckedIn->value,
                BookingStatus::Active->value,
            ])
            ->get(['booking_code', 'check_in_date', 'check_out_date']);

        $reservations = [];
        foreach ($bookings as $b) {
            $reservations[] = [
                'uid' => $b->booking_code,
                'start_date' => $b->check_in_date->toDateString(),
                'end_date' => $b->check_out_date->toDateString(),
                'summary' => 'Reserved (EthioProperty)',
            ];
        }

        // Host blocks on this unit
        $blocks = AvailabilityCalendar::query()
            ->where('accommodation_unit_id', $unit->id)
            ->where('status', 'blocked_by_host')
            ->orderBy('calendar_date')
            ->get(['id', 'calendar_date', 'block_reason']);

        foreach ($blocks as $block) {
            $d = $block->calendar_date->toDateString();
            $nextD = CarbonImmutable::parse($d)->addDay()->toDateString();
            $reservations[] = [
                'uid' => "BLOCK-{$block->id}",
                'start_date' => $d,
                'end_date' => $nextD,
                'summary' => 'Blocked by Host',
            ];
        }

        return self::generateExportIcs($propertyCode, $reservations);
    }

    /**
     * Exports confirmed bookings and host blocks as an RFC 5545 .ics calendar feed.
     *
     * @param  array<int, array{uid: string, start_date: string, end_date: string, summary: string}>  $reservations
     */
    public static function generateExportIcs(string $propertyCode, array $reservations): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//EthioProperty National Platform//Short-Stay Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($reservations as $res) {
            $dtStart = str_replace('-', '', $res['start_date']);
            $dtEnd = str_replace('-', '', $res['end_date']);
            $summary = str_replace(["\r", "\n", ',', ';', '\\'], [' ', ' ', '\,', '\;', '\\\\'], $res['summary'] ?: "Booked ({$propertyCode})");

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$res['uid'].'@ethioproperty.et';
            $lines[] = "DTSTART;VALUE=DATE:{$dtStart}";
            $lines[] = "DTEND;VALUE=DATE:{$dtEnd}";
            $lines[] = 'SUMMARY:'.$summary;
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }
}
