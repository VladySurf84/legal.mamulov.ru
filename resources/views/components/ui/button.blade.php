@props([
    'type' => 'button',
    'size' => 'md',
    'variant' => 'neutral',
    'href' => null,
])

@php
    $sizes = [
        'xs' => 'rounded-sm px-2 py-1 text-xs',
        'sm' => 'rounded-sm px-2 py-1 text-sm',
        'md' => 'rounded-md px-2.5 py-1.5 text-sm',
        'lg' => 'rounded-md px-3 py-2 text-sm',
        'xl' => 'rounded-md px-3.5 py-2.5 text-sm',
    ];

    $variants = [
        'neutral' => 'text-gray-900 bg-white shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20',
        'soft' => 'text-indigo-600 bg-indigo-50 shadow-xs hover:bg-indigo-100 dark:bg-indigo-500/20 dark:text-indigo-400 dark:shadow-none dark:hover:bg-indigo-500/30',
    ];

    $classes = trim(($sizes[$size] ?? $sizes['md']) . ' font-semibold disabled:cursor-not-allowed disabled:opacity-50 ' . ($variants[$variant] ?? $variants['neutral']));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        {{ $slot }}
    </button>
@endif
