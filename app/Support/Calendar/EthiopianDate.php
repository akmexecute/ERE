<?php

declare(strict_types=1);

namespace App\Support\Calendar;

final readonly class EthiopianDate
{
    private const array MONTHS = [
        'en' => [
            1 => 'Meskerem', 2 => 'Tikimt', 3 => 'Hidar', 4 => 'Tahsas',
            5 => 'Tir', 6 => 'Yekatit', 7 => 'Megabit', 8 => 'Miazia',
            9 => 'Ginbot', 10 => 'Sene', 11 => 'Hamle', 12 => 'Nehase', 13 => 'Pagume',
        ],
        'am' => [
            1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ',
            5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
            9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን',
        ],
        'om' => [
            1 => 'Fulbaana', 2 => 'Onkoloolessa', 3 => 'Sadaasa', 4 => 'Muddee',
            5 => 'Amajjii', 6 => 'Guraandhala', 7 => 'Bitootessa', 8 => 'Elba',
            9 => 'Caamsaa', 10 => 'Waxabajjii', 11 => 'Adoolessa', 12 => 'Hagayya', 13 => 'Qaammee',
        ],
    ];

    public function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {}

    public function monthName(string $locale = 'am'): string
    {
        $lang = array_key_exists($locale, self::MONTHS) ? $locale : 'en';

        return self::MONTHS[$lang][$this->month] ?? self::MONTHS['en'][$this->month];
    }

    /**
     * Formats as "2019 መስከረም 12" (SRS Section 125 format) or localized equivalent.
     */
    public function format(string $locale = 'am'): string
    {
        return sprintf('%d %s %02d', $this->year, $this->monthName($locale), $this->day);
    }
}
