@props(['date'])

@php
    if ($date) {
        $ethDate = \App\Support\Calendar\EthiopianCalendarConverter::fromGregorian($date)->format(app()->getLocale());
        $gregDate = \Carbon\CarbonImmutable::parse($date)->format('M d, Y');
    } else {
        $ethDate = '';
        $gregDate = '';
    }
@endphp

@if($date)
    <span
        x-data
        x-text="($store.preferences?.calendar ?? 'ethiopian') === 'ethiopian' ? @js($ethDate) : @js($gregDate)"
        data-eth="{{ $ethDate }}"
        data-greg="{{ $gregDate }}"
        title="{{ $ethDate }} | {{ $gregDate }}"
    >{{ $ethDate }}</span>
@endif
