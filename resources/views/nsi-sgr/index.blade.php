@extends('layouts.app', [
    'title' => 'СГР',
    'titleDescription' => 'Единый реестр свидетельств о государственной регистрации НСИ ЕАЭС',
])

@php
    $count = static fn ($value) => number_format((int) $value, 0, ',', ' ');
@endphp

@section('content')
    <x-ui.table-filters
        :action="route('nsi-sgr.index')"
        rows-id="nsi-sgr-rows"
        loader-id="nsi-sgr-loader"
        table-selector="[data-nsi-sgr-table]"
        columns="lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]"
    >
        <x-ui.input
            label="Поиск"
            name="q"
            :value="$filters['q'] ?? ''"
            placeholder="Номер СГР, продукция, изготовитель, получатель"
            autocomplete="off"
            data-ui-table-filter-input
        />

        <x-ui.select
            label="Статус"
            name="status"
            :value="$filters['status'] ?? ''"
            :options="$statuses"
            placeholder="Все статусы"
        />

        <x-ui.select
            label="Детализация"
            name="details"
            :value="$filters['details'] ?? ''"
            :options="['yes' => 'Загружена', 'no' => 'Не загружена']"
            placeholder="Все записи"
        />

        <x-slot:actions>
            <x-ui.button :href="route('nsi-sgr.index')" variant="ghost" wire:navigate>
                Сбросить
            </x-ui.button>

            <x-ui.button type="submit" variant="soft">
                Показать
            </x-ui.button>
        </x-slot:actions>
    </x-ui.table-filters>

    <div class="mb-4 grid gap-3 md:grid-cols-4">
        <div class="border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Записей</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $count($summary->total_count ?? 0) }}</div>
        </div>
        <div class="border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Действующих</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $count($summary->active_count ?? 0) }}</div>
        </div>
        <div class="border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">С детализацией</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $count($summary->detailed_count ?? 0) }}</div>
        </div>
        <div class="border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Позиция импорта</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                {{ $count($state->next_offset ?? 0) }}
                <span class="text-sm font-medium text-gray-400">/ {{ $count($state->total_count ?? 0) }}</span>
            </div>
        </div>
    </div>

    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :sticky-summary-enabled="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
        body-id="nsi-sgr-rows"
        data-nsi-sgr-table
    >
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th first>Номер</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Статус</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Дата</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Продукция</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Изготовитель</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Получатель</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Применение</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last>Обновлено</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @include('nsi-sgr.partials.rows', [
            'records' => $records,
            'tableColspan' => $tableColspan,
        ])

        @include('nsi-sgr.partials.loader-row', [
            'nextPage' => $nextPage,
            'tableColspan' => $tableColspan,
        ])

        <x-slot:stickySummary>
            @include('nsi-sgr.partials.foot', [
                'filteredSummary' => $filteredSummary,
                'state' => $state,
            ])
        </x-slot:stickySummary>
    </x-ui.sticky-table>
@endsection
