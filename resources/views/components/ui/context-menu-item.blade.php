@props([
    'href' => null,
    'type' => 'button',
    'danger' => false,
    'disabled' => false,
])

@php
    $toneClass = $danger
        ? 'text-red-700 hover:bg-red-50 focus:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10 dark:focus:bg-red-500/10'
        : 'text-gray-700 hover:bg-gray-50 focus:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/10 dark:focus:bg-white/10';

    $classes = trim("flex w-full items-center gap-x-3 rounded-md px-3 py-1.5 text-left text-sm outline-none disabled:pointer-events-none disabled:opacity-50 {$toneClass}");
@endphp

@if ($href)
    <a href="{{ $href }}" role="menuitem" tabindex="-1" {{ $attributes->class($classes) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" role="menuitem" tabindex="-1" @disabled($disabled) {{ $attributes->class($classes) }}>
        {{ $slot }}
    </button>
@endif
