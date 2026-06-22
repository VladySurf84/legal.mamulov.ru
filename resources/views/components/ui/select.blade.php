@props([
    'id' => null,
    'name' => null,
    'label' => null,
    'value' => null,
    'options' => [],
    'placeholder' => null,
])

@php
    $selectId = $id ?? $name;
    $selectName = $name ?? $id;

    $selectClasses = 'col-start-1 row-start-1 w-full appearance-none rounded-md bg-white py-1.5 pr-8 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:*:bg-gray-800 dark:focus-visible:outline-indigo-500';
@endphp

@if ($label)
    <label @if ($selectId) for="{{ $selectId }}" @endif class="block text-sm/6 font-medium text-gray-900 dark:text-white">
        {{ $label }}
    </label>
@endif

<div @class(['mt-2' => $label, 'grid grid-cols-1'])>
    <select
        @if ($selectId) id="{{ $selectId }}" @endif
        @if ($selectName) name="{{ $selectName }}" @endif
        {{ $attributes->class($selectClasses) }}
    >
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif

        @forelse ($options as $optionValue => $option)
            @php
                $optionIsArray = is_array($option);
                $optionValue = $optionIsArray ? ($option['value'] ?? $optionValue) : $optionValue;
                $optionLabel = $optionIsArray ? ($option['label'] ?? $optionValue) : $option;
                $optionDisabled = $optionIsArray && ($option['disabled'] ?? false);
            @endphp

            <option value="{{ $optionValue }}" @selected((string) $optionValue === (string) $value) @disabled($optionDisabled)>
                {{ $optionLabel }}
            </option>
        @empty
            {{ $slot }}
        @endforelse
    </select>

    <svg viewBox="0 0 16 16" fill="currentColor" data-slot="icon" aria-hidden="true" class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end text-gray-500 sm:size-4 dark:text-gray-400">
        <path d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
    </svg>
</div>
