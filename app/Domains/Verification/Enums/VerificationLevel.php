<?php

declare(strict_types=1);

namespace App\Domains\Verification\Enums;

enum VerificationLevel: int
{
    case Unverified = 0;
    case IdentityVerified = 1;
    case SellerOrCompanyVerified = 2;
    case DocumentsSubmitted = 3;
    case ProfessionalReview = 4;
    case TransactionReady = 5;

    public function label(string $locale = 'en'): string
    {
        return match ($this) {
            self::Unverified => match ($locale) {
                'am' => 'ያልተረጋገጠ (ደረጃ 0)',
                'om' => 'Kan Hin Mirkanaaʼin (Sadarkaa 0)',
                default => 'Unverified (Level 0)',
            },
            self::IdentityVerified => match ($locale) {
                'am' => 'ማንነት የተረጋገጠ (ደረጃ 1)',
                'om' => 'Eenyummaan Mirkanaaʼe (Sadarkaa 1)',
                default => 'Identity Verified (Level 1)',
            },
            self::SellerOrCompanyVerified => match ($locale) {
                'am' => 'የተረጋገጠ ሻጭ / ድርጅት (ደረጃ 2)',
                'om' => 'Gurguraa / Dhaabbata Mirkanaaʼe (Sadarkaa 2)',
                default => 'Verified Seller / Company (Level 2)',
            },
            self::DocumentsSubmitted => match ($locale) {
                'am' => 'ሰነዶች የቀረቡለት (ደረጃ 3)',
                'om' => 'Sanadoonni Dhiyaatan (Sadarkaa 3)',
                default => 'Documents Submitted (Level 3)',
            },
            self::ProfessionalReview => match ($locale) {
                'am' => 'በባለሙያ የታየ (ደረጃ 4)',
                'om' => 'Ogeessaan Kan Ilaalame (Sadarkaa 4)',
                default => 'Professionally Reviewed (Level 4)',
            },
            self::TransactionReady => match ($locale) {
                'am' => 'ለግብይት ዝግጁ (ደረጃ 5)',
                'om' => 'Gurgurtaa/Bittaaf Qophii (Sadarkaa 5)',
                default => 'Transaction Ready (Level 5)',
            },
        };
    }

    /**
     * SRS Sections 22, 23 & 177: Mandatory visible explanation & statutory legal disclaimer.
     */
    public function visibleExplanation(string $locale = 'en'): string
    {
        $core = match ($this) {
            self::Unverified => 'No identity or property documentation has been reviewed yet.',
            self::IdentityVerified => 'Phone OTP and government-issued personal ID have been verified by the platform.',
            self::SellerOrCompanyVerified => 'Seller identity or company trade license & TIN registration have been verified.',
            self::DocumentsSubmitted => 'Supporting property ownership or lease documents have been uploaded to the secure vault.',
            self::ProfessionalReview => 'Submitted property documents have been reviewed for completeness according to platform procedure.',
            self::TransactionReady => 'All platform-required identity and pre-transaction document checks have been completed.',
        };

        return $core.' Platform verification is an internal trust check conducted in accordance with Ethiopian Real Estate Proclamation No. 1357/2024 guidelines and does NOT constitute official government land-registry title certification.';
    }
}
