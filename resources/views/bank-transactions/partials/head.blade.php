<tr>
    <x-ui.sticky-table-th first>Дата</x-ui.sticky-table-th>
    @if ($showAccountColumn)
        <x-ui.sticky-table-th>Юрлицо / счет</x-ui.sticky-table-th>
    @endif
    <x-ui.money-columns-head />
    <x-ui.sticky-table-th>Контрагент</x-ui.sticky-table-th>
    <x-ui.sticky-table-th>Назначение</x-ui.sticky-table-th>
    <x-ui.sticky-table-th last align="right">Итог</x-ui.sticky-table-th>
</tr>
