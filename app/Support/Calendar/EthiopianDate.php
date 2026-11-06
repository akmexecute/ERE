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

    private const array DAYS_OF_WEEK = [
        'en' => [
            0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
            4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday',
        ],
        'am' => [
            0 => 'እሁድ', 1 => 'ሰኞ', 2 => 'ማክሰኞ', 3 => 'ረቡዕ',
            4 => 'ሐሙስ', 5 => 'ዓርብ', 6 => 'ቅዳሜ',
        ],
        'om' => [
            0 => 'Dilbata', 1 => 'Wiixata', 2 => 'Kibxata', 3 => 'Roobii',
            4 => 'Kamisa', 5 => 'Jimaata', 6 => 'Sanbata',
        ],
    ];

    public function __construct(
        public int $year,
        public int $month,
        public int $day,
        public ?int $dayOfWeek = null,
    ) {}

    public function monthName(string $locale = 'am'): string
    {
        $lang = array_key_exists($locale, self::MONTHS) ? $locale : 'en';

        return self::MONTHS[$lang][$this->month] ?? self::MONTHS['en'][$this->month];
    }

    public function dayName(string $locale = 'am'): string
    {
        $lang = array_key_exists($locale, self::DAYS_OF_WEEK) ? $locale : 'en';
        $dow = $this->dayOfWeek ?? 0;

        return self::DAYS_OF_WEEK[$lang][$dow] ?? self::DAYS_OF_WEEK['en'][$dow];
    }

    /**
     * Formats as "2019 መስከረም 12" (SRS Section 125 format) or localized equivalent.
     */
    public function format(string $locale = 'am'): string
    {
        return sprintf('%d %s %02d', $this->year, $this->monthName($locale), $this->day);
    }

    /**
     * Formats with the day of the week, e.g. "ዓርብ፣ ጥቅምት 13 ቀን 2019 ዓ.ም." or "Friday, Tikimt 13, 2019"
     */
    public function toHumanString(string $locale = 'am'): string
    {
        return match ($locale) {
            'am' => sprintf('%s፣ %s %d ቀን %d ዓ.ም.', $this->dayName('am'), $this->monthName('am'), $this->day, $this->year),
            'om' => sprintf('%s, %s %d, %d', $this->dayName('om'), $this->monthName('om'), $this->day, $this->year),
            default => sprintf('%s, %s %d, %d', $this->dayName('en'), $this->monthName('en'), $this->day, $this->year),
        };
    }
}
