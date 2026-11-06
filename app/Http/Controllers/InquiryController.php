<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Inquiry\Actions\CreateInquiryAction;
use App\Domains\Inquiry\Actions\RevealSellerPhoneAction;
use App\Domains\Inquiry\Actions\TransitionInquiryStatusAction;
use App\Domains\Inquiry\Models\Inquiry;
use App\Domains\Inquiry\Models\Message;
use App\Domains\Inquiry\Models\MessageAttachment;
use App\Domains\Listing\Models\Listing;
use App\Domains\Notification\Services\MultiChannelNotificationService;
use App\Domains\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class InquiryController extends Controller
{
    /**
     * Display the contextual messaging inbox (SRS Section 74).
     */
    public function inbox(Request $request, ?string $uuid = null): View
    {
        $user = $request->user();

        // 1. Fetch user inquiries (as buyer or seller)
        $inquiries = Inquiry::query()
            ->where(function ($q) use ($user) {
                $q->where('buyer_id', $user->id)
                    ->orWhere('seller_id', $user->id);
            })
            ->with(['listing.property.images', 'buyer', 'seller'])
            ->orderByDesc('last_message_at')
            ->get();

        // 2. Resolve active inquiry
        $activeInquiry = null;
        if ($uuid) {
            $activeInquiry = $inquiries->firstWhere('uuid', $uuid);
        }
        if (! $activeInquiry && $inquiries->isNotEmpty()) {
            $activeInquiry = $inquiries->first();
        }

        // 3. Load active inquiry conversation thread & mark read
        if ($activeInquiry) {
            $activeInquiry->load(['messages.sender', 'messages.attachments', 'property.city', 'property.subcity']);

            // Mark unread messages as read
            if ($user->id === $activeInquiry->buyer_id && $activeInquiry->unread_buyer_count > 0) {
                $activeInquiry->update(['unread_buyer_count' => 0]);
                Message::query()
                    ->where('inquiry_id', $activeInquiry->id)
                    ->where('sender_id', '!=', $user->id)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
            } elseif ($user->id === $activeInquiry->seller_id && $activeInquiry->unread_seller_count > 0) {
                $activeInquiry->update(['unread_seller_count' => 0]);
                Message::query()
                    ->where('inquiry_id', $activeInquiry->id)
                    ->where('sender_id', '!=', $user->id)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
            }
        }

        return view('messages.inbox', [
            'inquiries' => $inquiries,
            'activeInquiry' => $activeInquiry,
        ]);
    }

    /**
     * Submit a new inquiry lead for a listing.
     */
    public function store(Request $request, CreateInquiryAction $action): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'initial_message' => ['required', 'string', 'max:2000'],
            'lead_source' => ['nullable', 'string', 'max:32'],
        ]);

        $listing = Listing::query()->findOrFail($validated['listing_id']);

        $inquiry = $action->execute(
            listing: $listing,
            buyer: $request->user(),
            data: [
                'initial_message' => $validated['initial_message'],
                'lead_source' => $validated['lead_source'] ?? 'website',
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Inquiry submitted successfully.',
                'inquiry' => $inquiry,
            ], 201);
        }

        return redirect()->route('messages.inbox', ['uuid' => $inquiry->uuid])
            ->with('success', 'Your inquiry has been sent to the property owner.');
    }

    /**
     * Send a new message within an inquiry conversation.
     */
    public function sendMessage(string $uuid, Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $inquiry = Inquiry::query()->where('uuid', $uuid)->firstOrFail();

        // Authorize participant
        if ($user->id !== $inquiry->buyer_id && $user->id !== $inquiry->seller_id) {
            throw new AccessDeniedHttpException('You are not a participant in this conversation.');
        }

        $validated = $request->validate([
            'body' => ['required_without:attachment', 'nullable', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx'],
        ]);

        $body = trim($validated['body'] ?? '');
        $hasAttachment = $request->hasFile('attachment');

        if ($body === '' && ! $hasAttachment) {
            throw ValidationException::withMessages(['body' => ['Message text or attachment is required.']]);
        }

        $message = Message::query()->create([
            'uuid' => (string) Str::uuid(),
            'inquiry_id' => $inquiry->id,
            'sender_id' => $user->id,
            'message_type' => $hasAttachment ? 'attachment' : 'text',
            'body' => $body ?: 'Attached file: '.$request->file('attachment')->getClientOriginalName(),
            'created_at' => now(),
        ]);

        if ($hasAttachment) {
            $file = $request->file('attachment');
            $disk = config('filesystems.default', 'local');
            $path = $file->store("inquiries/{$inquiry->id}", $disk);

            MessageAttachment::query()->create([
                'message_id' => $message->id,
                'storage_disk' => $disk,
                'storage_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'file_size_bytes' => $file->getSize(),
            ]);
        }

        // Update inquiry unread count and timestamp
        $isBuyer = ($user->id === $inquiry->buyer_id);
        $inquiry->update([
            'unread_seller_count' => $isBuyer ? $inquiry->unread_seller_count + 1 : $inquiry->unread_seller_count,
            'unread_buyer_count' => ! $isBuyer ? $inquiry->unread_buyer_count + 1 : $inquiry->unread_buyer_count,
            'last_message_at' => now(),
        ]);

        // Notify conversation counterpart (SRS Sections 74, 214)
        $recipient = $isBuyer ? $inquiry->seller : $inquiry->buyer;
        if ($recipient) {
            app(MultiChannelNotificationService::class)->send(
                recipient: $recipient,
                eventKey: 'inquiry.message_received',
                data: [
                    'sender_name' => $user->name,
                    'inquiry_code' => $inquiry->inquiry_code,
                    'body' => Str::limit($message->body, 150),
                ],
                isMarketing: false
            );
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Message sent.',
                'data' => $message->load(['sender', 'attachments']),
            ], 201);
        }

        return redirect()->route('messages.inbox', ['uuid' => $inquiry->uuid]);
    }

    /**
     * Transition the inquiry pipeline status.
     */
    public function updateStatus(string $uuid, Request $request, TransitionInquiryStatusAction $action): JsonResponse|RedirectResponse
    {
        $inquiry = Inquiry::query()->where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'status' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $updated = $action->execute(
            inquiry: $inquiry,
            targetStatus: $validated['status'],
            actingUser: $request->user(),
            notes: $validated['notes'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Inquiry status updated.',
                'inquiry' => $updated,
            ]);
        }

        return back()->with('success', 'Pipeline stage updated successfully.');
    }

    /**
     * Download authorized private attachment.
     */
    public function downloadAttachment(string $uuid, int $attachmentId, Request $request): StreamedResponse
    {
        $user = $request->user();
        $inquiry = Inquiry::query()->where('uuid', $uuid)->firstOrFail();

        $isStaff = $user->hasAnyRole(['super_admin', 'platform_admin', 'moderator']);
        if ($user->id !== $inquiry->buyer_id && $user->id !== $inquiry->seller_id && ! $isStaff) {
            throw new AccessDeniedHttpException('Unauthorized to access this attachment.');
        }

        $attachment = MessageAttachment::query()
            ->whereHas('message', fn ($q) => $q->where('inquiry_id', $inquiry->id))
            ->findOrFail($attachmentId);

        // Path traversal guard
        $path = $attachment->storage_path;
        if (str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            abort(403, 'Invalid attachment path.');
        }

        $disk = Storage::disk($attachment->storage_disk);
        if (! $disk->exists($path)) {
            abort(404, 'Attachment file not found.');
        }

        return $disk->download($path, $attachment->original_filename);
    }

    /**
     * Reveal seller phone through authorized rate-limited endpoint (SRS Section 75).
     */
    public function revealPhone(Listing $listing, Request $request, RevealSellerPhoneAction $action): JsonResponse
    {
        $listing->loadMissing(['property.owner', 'publisher']);
        $seller = $listing->publisher ?? $listing->property?->owner;

        if (! $seller) {
            return response()->json(['error' => 'Seller not found for this listing.'], 404);
        }

        $phone = $action->execute(
            listing: $listing,
            seller: $seller,
            viewer: $request->user(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'phone' => $phone,
            'message' => 'Seller phone revealed securely.',
        ]);
    }
}
