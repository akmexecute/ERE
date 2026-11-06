<?php

declare(strict_types=1);

namespace App\Domains\Inquiry\Actions;

use App\Domains\Inquiry\Enums\InquiryStatus;
use App\Domains\Inquiry\Models\Inquiry;
use App\Domains\Inquiry\Models\Message;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Notification\Services\MultiChannelNotificationService;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class CreateInquiryAction
{
    public function __construct(
        private GenerateInquiryCodeAction $codeGenerator = new GenerateInquiryCodeAction(),
        private MultiChannelNotificationService $notificationService = new MultiChannelNotificationService()
    ) {}

    /**
     * @param array{
     *   lead_source?: string,
     *   initial_message: string
     * } $data
     */
    public function execute(Listing $listing, User $buyer, array $data): Inquiry
    {
        $listing->loadMissing(['property.owner']);
        $property = $listing->property;

        if (! $property) {
            throw ValidationException::withMessages(['listing' => ['The selected listing does not have a linked property.']]);
        }

        // Determine seller: listing publisher or property owner
        $sellerId = $listing->published_by_user_id ?? $property->owner_id;
        if (! $sellerId) {
            throw ValidationException::withMessages(['listing' => ['Listing has no associated seller or owner.']]);
        }

        // Prevent self-inquiry
        if ($buyer->id === $sellerId) {
            throw new AccessDeniedHttpException('You cannot submit an inquiry on your own property or listing.');
        }

        // Verify listing eligibility
        $statusStr = $listing->status instanceof ListingStatus ? $listing->status->value : (string) $listing->status;
        if ($statusStr !== 'published') {
            throw ValidationException::withMessages(['listing' => ['Inquiries can only be submitted for published listings.']]);
        }

        $messageText = trim($data['initial_message'] ?? '');
        if ($messageText === '') {
            throw ValidationException::withMessages(['initial_message' => ['Initial inquiry message is required.']]);
        }

        if (mb_strlen($messageText) > 2000) {
            throw ValidationException::withMessages(['initial_message' => ['Inquiry message cannot exceed 2000 characters.']]);
        }

        $leadSource = $data['lead_source'] ?? 'website';
        $validSources = ['website', 'mobile', 'facebook', 'google', 'telegram', 'whatsapp', 'referral', 'phone'];
        if (! in_array($leadSource, $validSources, true)) {
            $leadSource = 'website';
        }

        return DB::transaction(function () use ($listing, $property, $buyer, $sellerId, $leadSource, $messageText) {
            $code = $this->codeGenerator->execute();

            $inquiry = Inquiry::query()->create([
                'uuid' => (string) Str::uuid(),
                'inquiry_code' => $code,
                'listing_id' => $listing->id,
                'property_id' => $property->id,
                'buyer_id' => $buyer->id,
                'seller_id' => $sellerId,
                'company_id' => $listing->company_id ?? $property->company_id,
                'lead_source' => $leadSource,
                'status' => InquiryStatus::New,
                'initial_message' => $messageText,
                'unread_buyer_count' => 0,
                'unread_seller_count' => 1,
                'last_message_at' => now(),
            ]);

            // Save first message
            Message::query()->create([
                'uuid' => (string) Str::uuid(),
                'inquiry_id' => $inquiry->id,
                'sender_id' => $buyer->id,
                'message_type' => 'text',
                'body' => $messageText,
                'created_at' => now(),
            ]);

            if (Schema::hasTable('audit_logs')) {
                DB::table('audit_logs')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $buyer->id,
                    'action' => 'inquiry.created',
                    'auditable_type' => Inquiry::class,
                    'auditable_id' => (string) $inquiry->id,
                    'new_values' => json_encode([
                        'inquiry_code' => $code,
                        'listing_id' => $listing->id,
                        'seller_id' => $sellerId,
                    ]),
                    'reason' => 'New lead generated',
                    'created_at' => now(),
                ]);
            }

            return $inquiry;
        });

        // Notify seller of incoming inquiry lead (SRS Sections 74, 214)
        $seller = User::query()->find($sellerId);
        if ($seller) {
            $this->notificationService->send(
                recipient: $seller,
                eventKey: 'inquiry.received',
                data: [
                    'name' => $seller->name,
                    'listing_code' => $listing->slug ?? (string) $listing->id,
                    'property_code' => $property->property_code ?? (string) $property->id,
                    'message' => Str::limit($messageText, 150),
                ],
                isMarketing: false
            );
        }

        return $inquiry;
    }
}
