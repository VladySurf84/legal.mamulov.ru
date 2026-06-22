@props([
    'id' => null,
    'name' => 'selected',
    'label' => null,
    'value' => [],
    'options' => [],
    'placeholder' => 'Выберите',
    'selectedLabel' => null,
])

@php
    $id ??= $name . '-' . \Illuminate\Support\Str::random(6);
    $inputName = \Illuminate\Support\Str::endsWith($name, '[]') ? $name : $name . '[]';
    $selectedValues = collect(\Illuminate\Support\Arr::wrap($value))
        ->map(fn ($item) => (string) $item)
        ->all();

    $normalizedOptions = collect($options)->map(function ($option) {
        if (is_object($option)) {
            $option = get_object_vars($option);
        }

        return [
            'value' => (string) ($option['value'] ?? ''),
            'label' => (string) ($option['label'] ?? ''),
            'secondary' => (string) ($option['secondary'] ?? ''),
            'disabled' => (bool) ($option['disabled'] ?? false),
        ];
    });

    $selectedOptions = $normalizedOptions->filter(fn ($option) => in_array($option['value'], $selectedValues, true));
    $summary = $selectedLabel
        ?? match ($selectedOptions->count()) {
            0 => $placeholder,
            1 => $selectedOptions->first()['label'],
            default => 'Выбрано ' . $selectedOptions->count(),
        };
@endphp

@once
    <script>
        document.addEventListener('change', (event) => {
            const input = event.target.closest('[data-multi-select-option]');

            if (!input) {
                return;
            }

            const root = input.closest('[data-multi-select]');
            const label = root?.querySelector('[data-multi-select-label]');
            const secondary = root?.querySelector('[data-multi-select-secondary]');

            if (!root || !label || !secondary) {
                return;
            }

            const checkedOptions = [...root.querySelectorAll('[data-multi-select-option]:checked')];

            if (checkedOptions.length === 0) {
                label.textContent = root.dataset.placeholder || '';
                secondary.textContent = '';
                secondary.hidden = true;
                return;
            }

            if (checkedOptions.length === 1) {
                label.textContent = checkedOptions[0].dataset.label || '';
                secondary.textContent = checkedOptions[0].dataset.secondary || '';
                secondary.hidden = !secondary.textContent;
                return;
            }

            label.textContent = `Выбрано ${checkedOptions.length}`;
            secondary.textContent = '';
            secondary.hidden = true;
        });
    </script>
@endonce

<div
    {{ $attributes->class('block') }}
    data-multi-select
    data-placeholder="{{ $placeholder }}"
>
    @if ($label)
        <label for="{{ $id }}-button" class="block text-sm/6 font-medium text-gray-900 dark:text-white">{{ $label }}</label>
    @endif

    <details class="{{ $label ? 'mt-2' : '' }} group relative">
        <summary
            id="{{ $id }}-button"
            class="grid w-full cursor-default list-none grid-cols-1 rounded-md bg-white py-1.5 pr-2 pl-3 text-left text-gray-900 outline-1 -outline-offset-1 outline-gray-300 marker:hidden focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 group-open:outline-2 group-open:-outline-offset-2 group-open:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500 dark:group-open:outline-indigo-500 [&::-webkit-details-marker]:hidden"
        >
            <span class="col-start-1 row-start-1 flex w-full gap-2 pr-6">
                <span class="truncate" data-multi-select-label>{{ $summary }}</span>
                <span
                    class="truncate text-gray-500 dark:text-gray-400"
                    data-multi-select-secondary
                    @if (! ($selectedOptions->count() === 1 && $selectedOptions->first()['secondary'] !== '')) hidden @endif
                >{{ $selectedOptions->count() === 1 ? $selectedOptions->first()['secondary'] : '' }}</span>
            </span>
            <svg viewBox="0 0 16 16" fill="currentColor" data-slot="icon" aria-hidden="true" class="col-start-1 row-start-1 size-5 self-center justify-self-end text-gray-500 sm:size-4 dark:text-gray-400">
                <path d="M5.22 10.22a.75.75 0 0 1 1.06 0L8 11.94l1.72-1.72a.75.75 0 1 1 1.06 1.06l-2.25 2.25a.75.75 0 0 1-1.06 0l-2.25-2.25a.75.75 0 0 1 0-1.06ZM10.78 5.78a.75.75 0 0 1-1.06 0L8 4.06 6.28 5.78a.75.75 0 0 1-1.06-1.06l2.25-2.25a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 0 1 0 1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
            </svg>
        </summary>

        <div class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg outline-1 outline-black/5 sm:text-sm dark:bg-gray-800 dark:shadow-none dark:-outline-offset-1 dark:outline-white/10">
            @foreach ($normalizedOptions as $option)
                @php
                    $optionId = $id . '-' . $loop->index;
                    $checked = in_array($option['value'], $selectedValues, true);
                @endphp

                <label
                    for="{{ $optionId }}"
                    @class([
                        'group/option relative flex cursor-default gap-3 py-2 pr-9 pl-3 text-gray-900 select-none hover:bg-indigo-600 hover:text-white dark:text-white dark:hover:bg-indigo-500',
                        'opacity-50' => $option['disabled'],
                    ])
                >
                    <span class="grid size-5 shrink-0 place-items-center">
                        <input
                            id="{{ $optionId }}"
                            type="checkbox"
                            name="{{ $inputName }}"
                            value="{{ $option['value'] }}"
                            data-multi-select-option
                            data-label="{{ $option['label'] }}"
                            data-secondary="{{ $option['secondary'] }}"
                            @checked($checked)
                            @disabled($option['disabled'])
                            class="peer col-start-1 row-start-1 size-4 appearance-none rounded-sm border border-gray-300 bg-white checked:border-indigo-600 checked:bg-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:border-gray-300 disabled:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:checked:border-indigo-500 dark:checked:bg-indigo-500 dark:focus-visible:outline-indigo-500"
                        >
                        <svg viewBox="0 0 14 14" fill="none" class="pointer-events-none col-start-1 row-start-1 size-3.5 stroke-white opacity-0 peer-checked:opacity-100">
                            <path d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-normal">{{ $option['label'] }}</span>
                        @if ($option['secondary'] !== '')
                            <span class="block truncate text-gray-500 group-hover/option:text-indigo-200 dark:text-gray-400 dark:group-hover/option:text-indigo-100">{{ $option['secondary'] }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
    </details>
</div>
