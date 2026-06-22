@props([
    'id' => null,
    'name' => 'selected',
    'label' => null,
    'value' => null,
    'options' => [],
    'placeholder' => 'Выберите',
    'placeholderSecondary' => null,
])

@php
    $id ??= $name . '-' . \Illuminate\Support\Str::random(6);

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

    $selectedOption = $normalizedOptions->firstWhere('value', (string) $value);
@endphp

@once
    <script src="https://cdn.jsdelivr.net/npm/@tailwindplus/elements@1" type="module"></script>
@endonce

<div {{ $attributes->class('block') }}>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm/6 font-medium text-gray-900 dark:text-white">{{ $label }}</label>
    @endif

    <el-select id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" class="{{ $label ? 'mt-2' : '' }} block">
        <button type="button" class="grid w-full cursor-default grid-cols-1 rounded-md bg-white py-1.5 pr-2 pl-3 text-left text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500">
            <el-selectedcontent class="col-start-1 row-start-1 flex w-full gap-2 pr-6">
                <span class="truncate">{{ $selectedOption['label'] ?? $placeholder }}</span>
                @if (($selectedOption['secondary'] ?? null) || $placeholderSecondary)
                    <span class="truncate text-gray-500 dark:text-gray-400">{{ $selectedOption['secondary'] ?? $placeholderSecondary }}</span>
                @endif
            </el-selectedcontent>
            <svg viewBox="0 0 16 16" fill="currentColor" data-slot="icon" aria-hidden="true" class="col-start-1 row-start-1 size-5 self-center justify-self-end text-gray-500 sm:size-4 dark:text-gray-400">
                <path d="M5.22 10.22a.75.75 0 0 1 1.06 0L8 11.94l1.72-1.72a.75.75 0 1 1 1.06 1.06l-2.25 2.25a.75.75 0 0 1-1.06 0l-2.25-2.25a.75.75 0 0 1 0-1.06ZM10.78 5.78a.75.75 0 0 1-1.06 0L8 4.06 6.28 5.78a.75.75 0 0 1-1.06-1.06l2.25-2.25a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 0 1 0 1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
            </svg>
        </button>

        <el-options anchor="bottom start" popover class="max-h-60 w-(--button-width) overflow-auto rounded-md bg-white py-1 text-base shadow-lg outline-1 outline-black/5 [--anchor-gap:--spacing(1)] data-leave:transition data-leave:transition-discrete data-leave:duration-100 data-leave:ease-in data-closed:data-leave:opacity-0 sm:text-sm dark:bg-gray-800 dark:shadow-none dark:-outline-offset-1 dark:outline-white/10">
            @foreach ($normalizedOptions as $option)
                <el-option
                    value="{{ $option['value'] }}"
                    @if ($option['disabled']) disabled @endif
                    class="group/option relative block cursor-default py-2 pr-9 pl-3 text-gray-900 select-none focus:bg-indigo-600 focus:text-white focus:outline-hidden aria-disabled:cursor-not-allowed aria-disabled:opacity-50 dark:text-white dark:focus:bg-indigo-500"
                >
                    <div class="flex">
                        <span class="truncate font-normal group-aria-selected/option:font-semibold">{{ $option['label'] }}</span>
                        @if ($option['secondary'] !== '')
                            <span class="ml-2 truncate text-gray-500 group-focus/option:text-indigo-200 dark:text-gray-400 dark:group-focus/option:text-indigo-100">{{ $option['secondary'] }}</span>
                        @endif
                    </div>
                    <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-indigo-600 group-not-aria-selected/option:hidden group-focus/option:text-white in-[el-selectedcontent]:hidden dark:text-indigo-400">
                        <svg viewBox="0 0 20 20" fill="currentColor" data-slot="icon" aria-hidden="true" class="size-5">
                            <path d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" fill-rule="evenodd" />
                        </svg>
                    </span>
                </el-option>
            @endforeach
        </el-options>
    </el-select>
</div>
