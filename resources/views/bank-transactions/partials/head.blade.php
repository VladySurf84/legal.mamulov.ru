<tr>
    <x-ui.sticky-table-th padding="p-0" aria-label="Юрлицо" style="width: 5px; min-width: 5px; max-width: 5px; padding: 0;"></x-ui.sticky-table-th>
    <x-ui.sticky-table-th>Дата</x-ui.sticky-table-th>
    @if ($showAccountColumn)
        <x-ui.sticky-table-th>Счет</x-ui.sticky-table-th>
    @endif
    <x-ui.money-columns-head />
    <x-ui.sticky-table-th>Контрагент</x-ui.sticky-table-th>
    <x-ui.sticky-table-th>Назначение</x-ui.sticky-table-th>
    <x-ui.sticky-table-th last align="right">Итог</x-ui.sticky-table-th>
</tr>
