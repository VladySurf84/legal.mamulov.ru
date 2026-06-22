@props([
    'id' => null,
    'label' => null,
    'nameFrom' => 'date_from',
    'nameTo' => 'date_to',
    'valueFrom' => null,
    'valueTo' => null,
    'placeholder' => 'Выберите период',
    'dateFormat' => 'yyyy-MM-dd',
])

@php
    $id ??= 'air-datepicker-range-' . \Illuminate\Support\Str::random(6);
    $inputId = $id . '-input';
    $fromId = $id . '-from';
    $toId = $id . '-to';
    $separator = ' — ';
    $displayValue = collect([$valueFrom, $valueTo])->filter()->implode($separator);
@endphp

@once
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/air-datepicker@3.6.0/air-datepicker.css">
    <script src="https://cdn.jsdelivr.net/npm/air-datepicker@3.6.0/air-datepicker.js"></script>
    <script>
        (() => {
            const toDate = (value) => {
                if (!value) {
                    return null;
                }

                const parts = String(value).split('-').map(Number);

                if (parts.length !== 3 || parts.some(Number.isNaN)) {
                    return null;
                }

                return new Date(parts[0], parts[1] - 1, parts[2]);
            };

            const toIsoDate = (date) => {
                if (!(date instanceof Date)) {
                    return '';
                }

                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');

                return `${year}-${month}-${day}`;
            };

            const updateHiddenInputs = (root, dates) => {
                const fromInput = root.querySelector('[data-airdatepicker-range-from]');
                const toInput = root.querySelector('[data-airdatepicker-range-to]');
                const selectedDates = Array.isArray(dates) ? dates.filter(Boolean) : [];

                if (!fromInput || !toInput) {
                    return selectedDates.length;
                }

                if (selectedDates.length === 0) {
                    fromInput.value = '';
                    toInput.value = '';
                    return 0;
                }

                const sortedDates = selectedDates
                    .slice(0, 2)
                    .sort((first, second) => first.getTime() - second.getTime());

                fromInput.value = toIsoDate(sortedDates[0]);
                toInput.value = toIsoDate(sortedDates[1] ?? sortedDates[0]);

                return sortedDates.length;
            };

            const initAirDatepickerRanges = () => {
                document.querySelectorAll('[data-airdatepicker-range]:not([data-airdatepicker-ready])').forEach((root) => {
                    const input = root.querySelector('[data-airdatepicker-range-input]');

                    if (!input || !window.AirDatepicker) {
                        return;
                    }

                    root.dataset.airdatepickerReady = 'true';

                    const selectedDates = [
                        toDate(root.dataset.valueFrom),
                        toDate(root.dataset.valueTo),
                    ].filter(Boolean);

                    new AirDatepicker(input, {
                        range: true,
                        multipleDatesSeparator: root.dataset.separator || ' — ',
                        dateFormat: root.dataset.dateFormat || 'yyyy-MM-dd',
                        selectedDates,
                        buttons: ['today', 'clear'],
                        autoClose: false,
                        onSelect({date}) {
                            const selectedCount = updateHiddenInputs(root, date);

                            root.dispatchEvent(new CustomEvent('airdatepicker-range-change', {
                                bubbles: true,
                                detail: {selectedCount},
                            }));
                        },
                    });
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initAirDatepickerRanges);
            } else {
                initAirDatepickerRanges();
            }

            document.addEventListener('livewire:navigated', initAirDatepickerRanges);
        })();
    </script>
@endonce

<div
    {{ $attributes->class('block') }}
    data-airdatepicker-range
    data-value-from="{{ $valueFrom }}"
    data-value-to="{{ $valueTo }}"
    data-date-format="{{ $dateFormat }}"
    data-separator="{{ $separator }}"
>
    @if ($label)
        <label for="{{ $inputId }}" class="block text-sm/6 font-medium text-gray-900 dark:text-white">{{ $label }}</label>
    @endif

    <div class="{{ $label ? 'mt-2' : '' }} grid grid-cols-1">
        <input
            id="{{ $inputId }}"
            type="text"
            value="{{ $displayValue }}"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            data-airdatepicker-range-input
            class="col-start-1 row-start-1 w-full rounded-md bg-white py-1.5 pr-10 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus-visible:outline-indigo-500"
        >

        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="pointer-events-none col-start-1 row-start-1 mr-3 size-5 self-center justify-self-end text-gray-400">
            <path d="M5.25 3A2.25 2.25 0 0 0 3 5.25v9.5A2.25 2.25 0 0 0 5.25 17h9.5A2.25 2.25 0 0 0 17 14.75v-9.5A2.25 2.25 0 0 0 14.75 3h-.5a.75.75 0 0 0 0 1.5h.5a.75.75 0 0 1 .75.75V7h-11V5.25a.75.75 0 0 1 .75-.75h.5a.75.75 0 0 0 0-1.5h-.5Z" />
            <path d="M7.75 2.25a.75.75 0 0 0-1.5 0v2.5a.75.75 0 0 0 1.5 0v-2.5ZM13.75 2.25a.75.75 0 0 0-1.5 0v2.5a.75.75 0 0 0 1.5 0v-2.5ZM4.5 8.5v6.25c0 .414.336.75.75.75h9.5a.75.75 0 0 0 .75-.75V8.5h-11Z" />
        </svg>
    </div>

    <input id="{{ $fromId }}" type="hidden" name="{{ $nameFrom }}" value="{{ $valueFrom }}" data-airdatepicker-range-from>
    <input id="{{ $toId }}" type="hidden" name="{{ $nameTo }}" value="{{ $valueTo }}" data-airdatepicker-range-to>
</div>
