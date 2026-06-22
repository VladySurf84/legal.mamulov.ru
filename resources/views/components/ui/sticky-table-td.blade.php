@props([
    'align' => 'left',
    'hiddenUntil' => null,
    'first' => false,
    'last' => false,
    'strong' => false,
    'nowrap' => true,
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
        $first => 'py-2.5 pr-3 pl-4 sm:pl-6 lg:pl-8',
        $last => 'py-2.5 pr-4 pl-3 sm:pr-6 lg:pr-8',
        default => 'px-3 py-2.5',
    };

    $toneClass = $strong
        ? 'font-medium text-gray-900 dark:text-white'
        : 'text-gray-500 dark:text-gray-400';

    $nowrapClass = $nowrap ? 'whitespace-nowrap' : '';
@endphp

<td {{ $attributes->class(trim("border-b border-gray-200 text-sm dark:border-white/10 {$paddingClass} {$alignClass} {$visibilityClass} {$toneClass} {$nowrapClass}")) }}>
    {{ $slot }}
</td>
