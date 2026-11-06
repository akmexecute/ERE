<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Financial\Enums\PayoutSchedule;
use App\Domains\Financial\Enums\PayoutStatus;
use App\Domains\Financial\Models\HostPayout;
use App\Domains\Financial\Models\HostPayoutDestination;
use App\Domains\Host\Models\Host;
use App\Domains\Offer\Models\PropertyOffer;
use App\Domains\Payment\Enums\BankTransferVerificationStatus;
use App\Domains\Payment\Enums\PaymentMethod;
use App\Domains\Payment\Enums\PaymentStatus;
use App\Domains\Payment\Enums\PaymentType;
use App\Domains\Payment\Models\Payment;
use App\Domains\Property\Models\Property;
use App\Domains\Stay\Models\AccommodationUnit;
use App\Domains\Stay\Models\AccommodationUnitType;
use App\Domains\Stay\Models\StayListingProfile;
use App\Domains\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class Phase5E2ESeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production', 'prod')) {
            throw new \RuntimeException('Phase5E2ESeeder is strictly restricted to testing and local demonstration environments and must never be executed in production.');
        }

        $user = User::firstOrCreate(
            ['phone' => '+251911234567'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Admin Buyer Host',
                'email' => 'admin@ethioproperty.et',
                'verification_level' => 3,
                'status' => 'active',
            ]
        );
        if (! $user->hasRole('super_admin')) {
            $user->assignRole('super_admin');
        }

        $host = Host::firstOrCreate(
            ['user_id' => $user->id],
            [
                'uuid' => (string) Str::uuid(),
                'host_type' => 'individual_owner',
                'display_name' => 'Bole Premium Properties',
                'payout_schedule' => 'after_checkin',
                'is_superhost' => true,
                'status' => 'active',
            ]
        );

        // Host destination
        HostPayoutDestination::firstOrCreate(
            ['host_id' => $host->id, 'channel' => 'bank_account'],
            [
                'user_id' => $user->id,
                'institution_name' => 'Commercial Bank of Ethiopia',
                'account_holder_name' => 'Bole Premium Properties PLC',
                'account_number_encrypted' => '1000123456789',
                'account_number_masked' => '•••••••••6789',
                'swift_code' => 'CBETETAA',
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );

        // Property and Listing
        $property = Property::first();
        if (! $property) {
            return;
        }

        $listing = \App\Domains\Listing\Models\Listing::where('property_id', $property->id)->first()
            ?? \App\Domains\Listing\Models\Listing::first();

        // Accepted Property Offer for testing Property Purchase Checkout
        $offer = PropertyOffer::updateOrCreate(
            ['offer_code' => 'OFR-E2E-TEST-001'],
            [
                'listing_id' => $listing?->id ?? 1,
                'property_id' => $property->id,
                'buyer_user_id' => $user->id,
                'seller_user_id' => $property->owner_id ?? $user->id,
                'listed_asking_price_etb' => 18000000.00,
                'initial_offer_amount_etb' => 16500000.00,
                'latest_amount_etb' => 17000000.00,
                'status' => 'accepted',
                'expires_at' => now()->addDays(30),
                'accepted_at' => now()->subDay(),
                'metadata_json' => [
                    'total_paid_etb' => 2000000.00,
                    'remaining_balance_etb' => 15000000.00,
                ],
            ]
        );

        // Clean up transient pending payments on offer from prior tests
        Payment::where('payable_type', PropertyOffer::class)
            ->where('payable_id', $offer->id)
            ->where('status', PaymentStatus::Pending->value)
            ->delete();

        // Confirmed initial purchase payment on offer
        Payment::updateOrCreate(
            ['reference_code' => 'PAY-ERE-PROP-DEMO01'],
            [
                'payable_type' => PropertyOffer::class,
                'payable_id' => $offer->id,
                'customer_user_id' => $user->id,
                'payment_type' => PaymentType::PropertyPurchase->value,
                'payment_method' => PaymentMethod::Telebirr->value,
                'status' => PaymentStatus::Successful->value,
                'allocation_status' => \App\Domains\Payment\Enums\PurchaseAllocationStatus::Allocated->value,
                'allocated_amount_etb' => 2000000.00,
                'principal_amount_etb' => 2000000.00,
                'fee_amount_etb' => 0.00,
                'tax_amount_etb' => 0.00,
                'total_amount_etb' => 2000000.00,
                'currency' => 'ETB',
                'idempotency_key' => 'idemp_prop_demo_01',
                'confirmed_at' => now()->subDay(),
            ]
        );
        $offer->syncFinancialBalanceSnapshot();

        // Booking for testing checkout
        $profile = StayListingProfile::first();
        $unit = AccommodationUnit::first();
        if ($profile && $unit) {
            $booking = Booking::updateOrCreate(
                ['booking_code' => 'BK-E2E-PHASE5-01'],
                [
                    'uuid' => (string) Str::uuid(),
                    'idempotency_key' => 'idemp_e2e_'.Str::random(12),
                    'listing_id' => $profile->listing_id,
                    'property_id' => $profile->property_id,
                    'stay_listing_profile_id' => $profile->id,
                    'accommodation_unit_id' => $unit->id,
                    'host_id' => $host->id,
                    'guest_user_id' => $user->id,
                    'check_in_date' => now()->addDays(5)->toDateString(),
                    'check_out_date' => now()->addDays(10)->toDateString(),
                    'nights_count' => 5,
                    'adults_count' => 2,
                    'children_count' => 0,
                    'infants_count' => 0,
                    'accommodation_raw_subtotal_etb' => 35000.00,
                    'accommodation_net_subtotal_etb' => 35000.00,
                    'cleaning_fee_etb' => 1500.00,
                    'platform_fee_etb' => 3000.00,
                    'tax_amount_etb' => 5500.00,
                    'total_amount_etb' => 45000.00,
                    'status' => BookingStatus::AwaitingPayment,
                ]
            );

            // Awaiting verification bank transfer payment
            $bankPayment = Payment::updateOrCreate(
                ['reference_code' => 'PAY-ERE-BANK-DEMO01'],
                [
                    'payable_type' => Booking::class,
                    'payable_id' => $booking->id,
                    'customer_user_id' => $user->id,
                    'payment_type' => PaymentType::Booking->value,
                    'payment_method' => PaymentMethod::BankTransfer->value,
                    'status' => PaymentStatus::Pending->value,
                    'principal_amount_etb' => 45000.00,
                    'fee_amount_etb' => 0.00,
                    'tax_amount_etb' => 0.00,
                    'total_amount_etb' => 45000.00,
                    'currency' => 'ETB',
                    'idempotency_key' => 'idemp_bank_demo_01',
                    'metadata_json' => ['description' => 'Direct CBE bank deposit awaiting slip review'],
                ]
            );

            // Host payout record
            HostPayout::updateOrCreate(
                ['payout_reference' => 'PO-ERE-E2E-001'],
                [
                    'host_id' => $host->id,
                    'booking_id' => $booking->id,
                    'gross_revenue_etb' => 45000.00,
                    'platform_deductions_etb' => 3150.00,
                    'adjustments_amount_etb' => 0.00,
                    'net_payable_etb' => 41850.00,
                    'schedule_type' => PayoutSchedule::AfterCheckin->value,
                    'status' => PayoutStatus::Scheduled->value,
                    'scheduled_for' => now()->addDays(6),
                ]
            );
        }

        // Seed unapplied funds reconciliation exception for E2E testing
        $offer = PropertyOffer::first();
        if ($offer) {
            Payment::updateOrCreate(
                ['reference_code' => 'PAY-E2E-UNAPPLIED-999'],
                [
                    'uuid' => (string) Str::uuid(),
                    'transaction_id' => 'TXN-E2E-UNAPPLIED-999',
                    'idempotency_key' => 'idemp_e2e_unapplied_999',
                    'customer_user_id' => $user->id,
                    'payable_type' => PropertyOffer::class,
                    'payable_id' => $offer->id,
                    'property_id' => $offer->property_id,
                    'payment_type' => PaymentType::PropertyPurchase->value,
                    'principal_amount_etb' => 350000.00,
                    'fee_amount_etb' => 0.00,
                    'tax_amount_etb' => 0.00,
                    'total_amount_etb' => 350000.00,
                    'currency' => 'ETB',
                    'payment_method' => PaymentMethod::Telebirr->value,
                    'provider' => 'telebirr',
                    'provider_transaction_reference' => 'TB-LATE-888999',
                    'status' => PaymentStatus::Successful->value,
                    'allocation_status' => \App\Domains\Payment\Enums\PurchaseAllocationStatus::ReconciliationRequired->value,
                    'allocated_amount_etb' => 0.00,
                    'unapplied_amount_etb' => 350000.00,
                    'refunded_amount_etb' => 0.00,
                    'reconciliation_notes' => 'Late provider payment callback received after reservation expired. Contractual balance limit reached.',
                    'confirmed_at' => now(),
                ]
            );

            $existingRefundPayment = Payment::where('reference_code', 'PAY-E2E-UNAPPLIED-REFUND')->first();
            if ($existingRefundPayment) {
                $groupIds = \Illuminate\Support\Facades\DB::table('journal_posting_groups')
                    ->where('source_type', Payment::class)
                    ->where('source_id', $existingRefundPayment->id)
                    ->pluck('id');
                \Illuminate\Support\Facades\DB::table('ledger_entries')->whereIn('posting_group_id', $groupIds)->delete();
                \Illuminate\Support\Facades\DB::table('journal_posting_groups')->whereIn('id', $groupIds)->delete();
            }

            Payment::updateOrCreate(
                ['reference_code' => 'PAY-E2E-UNAPPLIED-REFUND'],
                [
                    'uuid' => (string) Str::uuid(),
                    'transaction_id' => 'TXN-E2E-UNAPPLIED-REFUND',
                    'idempotency_key' => 'idemp_e2e_unapplied_refund',
                    'customer_user_id' => $user->id,
                    'payable_type' => PropertyOffer::class,
                    'payable_id' => $offer->id,
                    'property_id' => $offer->property_id,
                    'payment_type' => PaymentType::PropertyPurchase->value,
                    'principal_amount_etb' => 150000.00,
                    'fee_amount_etb' => 0.00,
                    'tax_amount_etb' => 0.00,
                    'total_amount_etb' => 150000.00,
                    'currency' => 'ETB',
                    'payment_method' => PaymentMethod::Telebirr->value,
                    'provider' => 'telebirr',
                    'provider_transaction_reference' => 'TB-LATE-777888',
                    'status' => PaymentStatus::Successful->value,
                    'allocation_status' => \App\Domains\Payment\Enums\PurchaseAllocationStatus::ReconciliationRequired->value,
                    'allocated_amount_etb' => 0.00,
                    'unapplied_amount_etb' => 150000.00,
                    'refunded_amount_etb' => 0.00,
                    'reconciliation_notes' => 'Late callback held as unapplied funds for admin refund test.',
                    'confirmed_at' => now(),
                ]
            );
        }

        // Phase 5 Milestone 2: Seed Sample Published Virtual Tour
        $tour = \App\Domains\VirtualTour\Models\PropertyVirtualTour::updateOrCreate(
            ['uuid' => 'tour-e2e-demo-uuid-001'],
            [
                'property_id' => $property->id,
                'created_by_user_id' => $user->id,
                'title' => 'Bole Grand Residence 360° Walkthrough',
                'description' => 'Interactive 360 panorama tour through the grand living salon and master suite.',
                'tour_type' => 'equirectangular_panorama',
                'is_published' => true,
                'published_at' => now(),
            ]
        );

        $scene1 = \App\Domains\VirtualTour\Models\PropertyTourScene::updateOrCreate(
            ['tour_id' => $tour->id, 'room_name' => 'Grand Salon'],
            [
                'panorama_image_path' => 'demo/pano_salon.jpg',
                'sort_order' => 1,
                'initial_pitch' => 0.0,
                'initial_yaw' => 0.0,
                'initial_zoom' => 100.0,
                'is_default_entry' => true,
            ]
        );

        $scene2 = \App\Domains\VirtualTour\Models\PropertyTourScene::updateOrCreate(
            ['tour_id' => $tour->id, 'room_name' => 'Master Bedroom'],
            [
                'panorama_image_path' => 'demo/pano_bedroom.jpg',
                'sort_order' => 2,
                'initial_pitch' => -5.0,
                'initial_yaw' => 45.0,
                'initial_zoom' => 100.0,
                'is_default_entry' => false,
            ]
        );

        \App\Domains\VirtualTour\Models\PropertyTourHotspot::updateOrCreate(
            ['scene_id' => $scene1->id, 'target_scene_id' => $scene2->id],
            [
                'title' => 'Enter Master Suite',
                'pitch' => -3.0,
                'yaw' => 40.0,
                'icon' => 'arrow_forward',
            ]
        );
    }
}
