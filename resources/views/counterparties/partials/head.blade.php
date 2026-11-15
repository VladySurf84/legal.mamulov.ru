<tr>
    <x-ui.sticky-table-th first>Контрагент</x-ui.sticky-table-th>
    <x-ui.sticky-table-th>ИНН</x-ui.sticky-table-th>
    <x-ui.sticky-table-th align="right">Входящее</x-ui.sticky-table-th>
    <x-ui.sticky-table-th align="right">Наше сальдо</x-ui.sticky-table-th>
    <x-ui.sticky-table-th align="right">Книги покупок</x-ui.sticky-table-th>
    <x-ui.sticky-table-th align="right">Разница</x-ui.sticky-table-th>
    <x-ui.sticky-table-th align="right">Разница НДС</x-ui.sticky-table-th>
    <x-ui.money-columns-head />
    <x-ui.sticky-table-th align="right">Операций</x-ui.sticky-table-th>
    @if ($showLegalEntitiesCount)
        <x-ui.sticky-table-th align="right">Наших юрлиц</x-ui.sticky-table-th>
    @endif
    <x-ui.sticky-table-th last align="right"></x-ui.sticky-table-th>
</tr>
