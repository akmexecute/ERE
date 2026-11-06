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
        $core = match ($locale) {
            'am' => match ($this) {
                self::Unverified => 'እስካሁን ምንም አይነት የማንነት ወይም የባለቤትነት ሰነድ አልተመረመረም።',
                self::IdentityVerified => 'የስልክ ቁጥርና በመንግስት የተሰጠ የግል መታወቂያ በመድረኩ ተረጋግጧል።',
                self::SellerOrCompanyVerified => 'የሻጭ ማንነት ወይም የድርጅቱ ንግድ ፈቃድና የግብር ከፋይ መለያ ቁጥር (TIN) ተረጋግጧል።',
                self::DocumentsSubmitted => 'የንብረቱ ባለቤትነት ወይም የሊዝ ሰነዶች ወደ ደህንነቱ የተጠበቀው የሰነድ ማከማቻ ገብተዋል።',
                self::ProfessionalReview => 'የቀረቡት የንብረት ሰነዶች በመድረኩ የአሰራር ሥርዓት መሠረት የተሟሉ መሆናቸው ተመርምሯል።',
                self::TransactionReady => 'ሁሉም የመድረኩ ቅድመ-ግብይት ሰነድ እና የማንነት ማረጋገጫ ፍተሻዎች ተጠናቀዋል።',
            },
            'om' => match ($this) {
                self::Unverified => 'Ragaan eenyummaa yookiin qabeenyaa kamiyyuu ammayyuu hin qoratamne.',
                self::IdentityVerified => 'OTP bilbilaa fi waraqaan eenyummaa mootummaan kenname sirnichaan mirkanaaʼeera.',
                self::SellerOrCompanyVerified => 'Eenyummaan gurguraa yookiin hayyamni daldalaa fi koodiin TIN mirkanaaʼeera.',
                self::DocumentsSubmitted => 'Sanadoonni abbaa qabeenyummaa yookiin kireeffannaa baankii sanadaa keessatti galaniiru.',
                self::ProfessionalReview => 'Sanadoonni dhiyaatan akkaataa adeemsa sirnichaatiin guutuu ta\'uun isaanii qoratameera.',
                self::TransactionReady => 'Qorannoowwan sanadaa fi eenyummaa duraa fi daldalaa barbaachisan hundi xumuramaniiru.',
            },
            default => match ($this) {
                self::Unverified => 'No identity or property documentation has been reviewed yet.',
                self::IdentityVerified => 'Phone OTP and government-issued personal ID have been verified by the platform.',
                self::SellerOrCompanyVerified => 'Seller identity or company trade license & TIN registration have been verified.',
                self::DocumentsSubmitted => 'Supporting property ownership or lease documents have been uploaded to the secure vault.',
                self::ProfessionalReview => 'Submitted property documents have been reviewed for completeness according to platform procedure.',
                self::TransactionReady => 'All platform-required identity and pre-transaction document checks have been completed.',
            },
        };

        $disclaimer = match ($locale) {
            'am' => ' የERE ማረጋገጫ የመድረክ ማረጋገጫ ሂደት ነው። በመንግስት የመሬት አስተዳደር የተሰጠ ህጋዊ የይዞታ ማረጋገጫ (ካርታ) አይደለም፤ እንዲሁም ከሚመለከተው የመንግስት አካል ጋር የባለቤትነት ሰነዶችን ማረጋገጥን አይተካም።',
            'om' => ' Mirkaneessi ERE adeemsa mirkaneessa sirnichaa qofa. Waraqaa ragaa abbaa qabeenyummaa lafaa mootummaa miti, akkasumas qaama mootummaa dhimmi ilaallatu waliin sanadoota abbaa qabeenyummaa mirkaneessuu bakka hin bu\'u.',
            default => ' ERE verification is a platform verification process. It is not official government certification of land ownership and does not replace verification of ownership documents with the relevant government authority.',
        };

        return $core . ' ' . $disclaimer;
    }
}
