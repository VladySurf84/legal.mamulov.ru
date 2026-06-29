@php
    $count = static fn ($value) => number_format((int) $value, 0, ',', ' ');
@endphp

<tr>
    <x-ui.sticky-table-summary-label first :columns="1">
        Всего: {{ $count($filteredSummary->total_count ?? 0) }}
    </x-ui.sticky-table-summary-label>
    <x-ui.sticky-table-td summary nowrap class="text-sm text-gray-600 dark:text-gray-300">
        Действующих: {{ $count($filteredSummary->active_count ?? 0) }}
    </x-ui.sticky-table-td>
    <x-ui.sticky-table-td summary nowrap class="text-sm text-gray-600 dark:text-gray-300">
        С детализацией: {{ $count($filteredSummary->detailed_count ?? 0) }}
    </x-ui.sticky-table-td>
    <x-ui.sticky-table-td summary :nowrap="false" class="text-sm text-gray-600 dark:text-gray-300">
        Импорт списка: {{ $count($state->next_offset ?? 0) }} / {{ $count($state->total_count ?? 0) }}
    </x-ui.sticky-table-td>
    <x-ui.sticky-table-td summary />
    <x-ui.sticky-table-td summary />
    <x-ui.sticky-table-td summary />
    <x-ui.sticky-table-td summary last />
</tr>
