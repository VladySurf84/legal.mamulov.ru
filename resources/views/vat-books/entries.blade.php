@extends('layouts.app', [
    'title' => 'Содержание книг бухгалтера',
    'titleAttribute' => 'Строки из книг покупок и продаж, загруженных из XML бухгалтера.',
])

@php
    $bookLabels = [
        'purchase' => 'Покупки',
        'sales' => 'Продажи',
    ];
@endphp

@section('page_actions')
    <x-ui.button href="{{ route('vat-books.index') }}" size="lg" wire:navigate>
        Импорт книг
    </x-ui.button>
@endsection

@section('content')
    <x-ui.table-filters
        :action="route('vat-book-entries.index')"
        rows-id="vat-book-entries-rows"
        loader-id="vat-book-entries-loader"
        table-selector="[data-vat-book-entries-table]"
        summary-selector="#vat-book-entries-summary"
        columns="lg:grid-cols-5"
    >
                <x-ui.select
                    label="Год"
                    name="year"
                    :value="$filters['year'] ?? ''"
                    :options="$years->mapWithKeys(fn ($year) => [$year => $year])->prepend('Все годы', '')"
                />

                <x-ui.select
                    label="Квартал"
                    name="quarter"
                    :value="$filters['quarter'] ?? ''"
                    :options="collect([
                        '' => 'Все кварталы',
                        1 => 'Q1',
                        2 => 'Q2',
                        3 => 'Q3',
                        4 => 'Q4',
                    ])"
                />

                <x-ui.select
                    label="Книга"
                    name="book_type"
                    :value="$filters['book_type'] ?? ''"
                    :options="collect(['' => 'Покупки и продажи'] + $bookLabels)"
                />

                <x-ui.select-with-secondary-text
                    label="Юрлицо"
                    name="legal_id"
                    :value="$filters['legal_id'] ?? ''"
                    :options="$legals->map(fn ($legal) => [
                        'value' => $legal->legal_id,
                        'label' => $legal->legal_name,
                        'secondary' => $legal->legal_inn ? 'ИНН ' . $legal->legal_inn : '',
                    ])->prepend([
                        'value' => '',
                        'label' => 'Все юрлица',
                        'secondary' => '',
                    ])->values()"
                />

                <x-ui.input
                    label="Поиск"
                    name="q"
                    :value="$filters['q'] ?? ''"
                    placeholder="ИНН, контрагент, счет-фактура, код"
                    autocomplete="off"
                    data-ui-table-filter-input
                />

            <x-slot:actions>
                <x-ui.button href="{{ route('vat-book-entries.index') }}" size="lg" wire:navigate>
                    Сбросить
                </x-ui.button>
                <x-ui.button type="submit" size="lg" variant="soft">
                    Показать
                </x-ui.button>
            </x-slot:actions>
    </x-ui.table-filters>

    <div class="mb-4 flex flex-wrap gap-2" id="vat-book-entries-summary">
        <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-200">Строк: {{ number_format((int) $summary->entries_count, 0, ',', ' ') }}</span>
        <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-200">Сумма: {{ number_format((float) $summary->amount_total, 2, ',', ' ') }}</span>
        <span class="inline-flex rounded-full bg-cyan-50 px-3 py-1 text-sm font-medium text-cyan-700 ring-1 ring-cyan-200">Без НДС: {{ number_format((float) $summary->amount_without_vat, 2, ',', ' ') }}</span>
        <span class="inline-flex rounded-full bg-indigo-50 px-3 py-1 text-sm font-medium text-indigo-700 ring-1 ring-indigo-200">НДС: {{ number_format((float) $summary->vat_amount, 2, ',', ' ') }}</span>
    </div>

    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :sticky-summary-enabled="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
        body-id="vat-book-entries-rows"
        data-vat-book-entries-table
    >
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th first>Период</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Книга</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Строка</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Юрлицо</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Счет-фактура</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Контрагент</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Платеж</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Сумма</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Без НДС</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last align="right">НДС</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @include('vat-books.partials.entry-rows', [
            'entries' => $entries,
            'bookLabels' => $bookLabels,
        ])

        @include('vat-books.partials.entries-loader-row', [
            'nextPage' => $nextPage,
        ])

        <x-slot:stickySummary>
            <tr>
                <th scope="row" class="sticky bottom-0 z-10 whitespace-nowrap border-t border-gray-300 bg-white/75 py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 backdrop-blur-sm backdrop-filter sm:pl-6 lg:pl-8 dark:border-white/15 dark:bg-gray-900/75 dark:text-white">
                    Итого строк: {{ number_format((int) $summary->entries_count, 0, ',', ' ') }}
                </th>
                <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
                <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
                <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
                <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
                <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
                <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
                <td class="sticky bottom-0 z-10 whitespace-nowrap border-t border-gray-300 bg-white/75 px-3 py-3.5 text-right text-sm font-semibold tabular-nums text-gray-900 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75 dark:text-white">
                    {{ number_format((float) $summary->amount_total, 2, ',', ' ') }}
                </td>
                <td class="sticky bottom-0 z-10 whitespace-nowrap border-t border-gray-300 bg-white/75 px-3 py-3.5 text-right text-sm font-semibold tabular-nums text-cyan-700 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75">
                    {{ number_format((float) $summary->amount_without_vat, 2, ',', ' ') }}
                </td>
                <td class="sticky bottom-0 z-10 whitespace-nowrap border-t border-gray-300 bg-white/75 py-3.5 pr-4 pl-3 text-right text-sm font-semibold tabular-nums text-indigo-700 backdrop-blur-sm backdrop-filter sm:pr-6 lg:pr-8 dark:border-white/15 dark:bg-gray-900/75">
                    {{ number_format((float) $summary->vat_amount, 2, ',', ' ') }}
                </td>
            </tr>
        </x-slot:stickySummary>
    </x-ui.sticky-table>
@endsection
