<?php

declare(strict_types=1);

namespace App\Domains\Calendar\Services;

use Carbon\CarbonImmutable;

final class IcalSyncService
{
    /**
     * Parses RFC 5545 VCALENDAR string and extracts booked/blocked date ranges [start_date, end_date).
     *
     * @return array<int, array{uid: string, summary: string, start_date: string, end_date: string}>
     */
    public static function parseIncomingIcs(string $icsContent): array
    {
        $events = [];
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/si', $icsContent, $matches);

        foreach ($matches[1] ?? [] as $block) {
            preg_match('/UID:([^\r\n]+)/i', $block, $uidMatch);
            preg_match('/SUMMARY:([^\r\n]+)/i', $block, $sumMatch);
            preg_match('/DTSTART(?:;[^:]*)?:(\d{8})/i', $block, $startMatch);
            preg_match('/DTEND(?:;[^:]*)?:(\d{8})/i', $block, $endMatch);

            if (! empty($startMatch[1]) && ! empty($endMatch[1])) {
                $start = CarbonImmutable::createFromFormat('Ymd', $startMatch[1])->toDateString();
                $end = CarbonImmutable::createFromFormat('Ymd', $endMatch[1])->toDateString();

                if ($end > $start) {
                    $events[] = [
                        'uid' => trim($uidMatch[1] ?? uniqid('ical_', true)),
                        'summary' => trim($sumMatch[1] ?? 'External Reservation'),
                        'start_date' => $start,
                        'end_date' => $end,
                    ];
                }
            }
        }

        return $events;
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
        ];

        foreach ($reservations as $res) {
            $dtStart = str_replace('-', '', $res['start_date']);
            $dtEnd = str_replace('-', '', $res['end_date']);
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$res['uid'].'@ethioproperty.et';
            $lines[] = "DTSTART;VALUE=DATE:{$dtStart}";
            $lines[] = "DTEND;VALUE=DATE:{$dtEnd}";
            $lines[] = 'SUMMARY:'.($res['summary'] ?: "Booked ({$propertyCode})");
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }
}
