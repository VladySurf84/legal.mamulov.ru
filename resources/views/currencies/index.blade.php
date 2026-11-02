@extends('layouts.app', ['title' => 'Справочник валют'])

@section('content')
    <x-ui.table-filters :action="route('currencies.index')" rows-id="currencies-rows" table-selector="[data-currencies-table]" columns="grid-cols-1">
        <x-ui.input name="q" :value="$search" placeholder="Название валюты, код ОКВ или ISO" data-ui-table-filter-input />
    </x-ui.table-filters>

    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
        body-id="currencies-rows"
        data-currencies-table
    >
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th first>Код ОКВ</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>ISO</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Название</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Разрядов</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Всего</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Счета</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Документы</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Банк</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Money layer</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last align="right">Книги НДС</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @include('currencies.partials.rows')
    </x-ui.sticky-table>
@endsection
