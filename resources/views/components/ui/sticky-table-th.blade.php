@props([
    'align' => 'left',
    'hiddenUntil' => null,
    'first' => false,
    'last' => false,
])

@php
    $alignClass = [
        'left' => 'text-left',
        'center' => 'text-center',
        'right' => 'text-right',
    ][$align] ?? 'text-left';

    $visibilityClass = [
        'sm' => 'hidden sm:table-cell',
        'md' => 'hidden md:table-cell',
        'lg' => 'hidden lg:table-cell',
        'xl' => 'hidden xl:table-cell',
    ][$hiddenUntil] ?? '';

    $paddingClass = match (true) {
        $first => 'py-3.5 pr-3 pl-4 sm:pl-6 lg:pl-8',
        $last => 'py-3.5 pr-4 pl-3 sm:pr-6 lg:pr-8',
        default => 'px-3 py-3.5',
    };
@endphp

<th
    scope="col"
    {{ $attributes->class(trim("sticky top-0 z-10 border-b border-gray-300 bg-white/75 text-sm font-semibold text-gray-900 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75 dark:text-white {$paddingClass} {$alignClass} {$visibilityClass}")) }}
>
    {{ $slot }}
</th>
