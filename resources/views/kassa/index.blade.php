@extends('layouts.app', [
    'title' => 'Касса',
    'titleDescription' => 'Кассовый слой из ручных записей и банковских операций, попавших под правила интерпретации.',
])

@php
    $displayTimezone = config('app.display_timezone', 'Europe/Moscow');
    $money = static fn ($value) => number_format((float) $value, 0, ',', ' ');
    $date = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse((string) $value, 'UTC')->timezone($displayTimezone)->format('d.m.Y H:i') : '—';
    $defaultKassaTime = now($displayTimezone)->format('Y-m-d\TH:i');
    $shouldOpenKassaCreateDialog = session('open_modal') === 'kassa-create-dialog' || (($errors ?? null)?->any() && old('_form') === 'kassa-create');
@endphp

@section('page_actions')
    <div class="flex flex-wrap items-center gap-2">
        <form method="post" action="{{ route('kassa.rebuild') }}">
            @csrf
            <x-ui.button type="submit" size="md" variant="ghost">
                Пересчитать слой
            </x-ui.button>
        </form>

        <x-ui.button type="button" size="md" variant="ghost" data-ui-modal-open="kassa-create-dialog">
            Добавить запись
        </x-ui.button>
    </div>
@endsection

@section('content')
    <x-ui.modal
        id="kassa-create-dialog"
        title="Добавить запись кассы"
        description="Ручная денежная операция будет сохранена в legal.kassa и автоматически отражена как документ manual_cash_operation."
        size="xl"
        :open="$shouldOpenKassaCreateDialog"
    >
        <form method="post" action="{{ route('kassa.store') }}">
            @csrf
            <input type="hidden" name="_form" value="kassa-create">

            <div class="space-y-5 px-6 py-5">
                @if (($errors ?? null)?->any() && old('_form') === 'kassa-create')
                    <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select
                        label="Статья"
                        name="article_id"
                        :value="old('article_id', $filters['article_id'] ?? '')"
                        :options="$articles->pluck('article', 'article_id')->all()"
                        placeholder="Выберите статью"
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="grid gap-1.5 text-sm font-medium text-gray-900 dark:text-white">
                        <span>Дата и время</span>
                        <input
                            type="datetime-local"
                            name="time"
                            value="{{ old('time', $defaultKassaTime) }}"
                            class="h-10 rounded-md bg-white px-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500"
                            required
                        >
                    </label>

                    <x-ui.select
                        label="Тип"
                        name="direction"
                        :value="old('direction', 'expense')"
                        :options="['income' => 'Приход', 'expense' => 'Расход']"
                    />

                    <label class="grid gap-1.5 text-sm font-medium text-gray-900 dark:text-white">
                        <span>Сумма</span>
                        <input
                            type="number"
                            name="amount"
                            value="{{ old('amount') }}"
                            min="1"
                            step="1"
                            class="h-10 rounded-md bg-white px-3 text-right text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500"
                            required
                        >
                    </label>
                </div>

                <label class="grid gap-1.5 text-sm font-medium text-gray-900 dark:text-white">
                    <span>Описание</span>
                    <textarea
                        name="description"
                        rows="4"
                        maxlength="2000"
                        class="rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500"
                        required
                    >{{ old('description') }}</textarea>
                </label>
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 px-6 py-4 dark:border-white/10">
                <x-ui.button type="button" variant="ghost" data-ui-modal-close>
                    Отмена
                </x-ui.button>

                <x-ui.button type="submit" variant="soft">
                    Добавить
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ session('error') }}
        </div>
    @endif

    <x-ui.table-filters
        :action="route('kassa.index')"
        rows-id="kassa-rows"
        loader-id="kassa-loader"
        table-selector="[data-kassa-table]"
        columns="lg:grid-cols-6"
    >
                <x-ui.select-with-secondary-text
                    label="Юрлицо"
                    name="legal_id"
                    :value="$filters['legal_id'] ?? ''"
                    :options="$legalEntities->map(fn ($legal) => [
                        'value' => $legal->legal_id,
                        'label' => $legal->legal_name,
                        'secondary' => 'ИНН ' . $legal->legal_inn,
                    ])->values()"
                    placeholder="Все юрлица"
                />

                <x-ui.select
                    label="Статья"
                    name="article_id"
                    :value="$filters['article_id'] ?? ''"
                    :options="$articles->pluck('article', 'article_id')->all()"
                    placeholder="Все статьи"
                />

                <x-ui.select
                    label="Источник"
                    name="source_type"
                    :value="$filters['source_type'] ?? ''"
                    :options="[
                        'manual_kassa' => 'Ручной ввод',
                        'bank_rule' => 'Банк по правилу',
                    ]"
                    placeholder="Все источники"
                />

                <x-ui.airdatepicker.date-range
                    label="Период"
                    name-from="date_from"
                    name-to="date_to"
                    :value-from="$filters['date_from'] ?? null"
                    :value-to="$filters['date_to'] ?? null"
                />

                <label class="grid gap-1.5 text-sm font-medium text-gray-900 dark:text-white">
                    <span>Поиск</span>
                    <input
                        class="h-10 rounded-md bg-white px-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500"
                        name="q"
                        data-ui-table-filter-input
                        value="{{ $filters['q'] ?? '' }}"
                        placeholder="Описание, статья, юрлицо или ИНН"
                    >
                </label>
                <div class="flex flex-wrap items-end justify-end gap-2">
                <x-ui.button :href="route('kassa.index')" variant="ghost" wire:navigate>
                    Сбросить
                </x-ui.button>

                <x-ui.button type="submit" variant="soft">
                    Показать
                </x-ui.button>
                </div>
    </x-ui.table-filters>

    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :sticky-summary-enabled="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
        body-id="kassa-rows"
        data-kassa-table
    >
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th first>Дата</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Источник</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Статья</x-ui.sticky-table-th>
                <x-ui.money-columns-head />
                <x-ui.sticky-table-th>Описание</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Документ</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Итог</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last align="right">ID</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @if ($operations->isNotEmpty())
            @include('kassa.partials.rows', ['operations' => $operations, 'displayTimezone' => $displayTimezone])
        @else
            <tr>
                <td class="py-12 text-center text-sm text-gray-500 dark:text-gray-400" colspan="10">
                    Кассовые операции пока не найдены.
                </td>
            </tr>
        @endif

        @include('kassa.partials.loader-row', [
            'nextPage' => $nextPage,
            'tableColspan' => 10,
        ])

        <x-slot:stickySummary>
            <tr>
                <x-ui.sticky-table-summary-label first :columns="3">
                    Итого операций: {{ number_format((int) $summary->operations_count, 0, ',', ' ') }}
                </x-ui.sticky-table-summary-label>
                <x-ui.money-columns
                    :amount="$summary->saldo_amount"
                    :income="$summary->income_amount"
                    :expense="$summary->expense_amount"
                    summary
                    :decimals="0"
                />
                <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
                <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
                <td @class([
                    'sticky bottom-0 z-10 whitespace-nowrap border-t border-gray-300 bg-white/75 px-3 py-3.5 text-right text-sm font-semibold tabular-nums backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75',
                    'text-emerald-700' => (float) $summary->saldo_amount > 0,
                    'text-rose-700' => (float) $summary->saldo_amount < 0,
                    'text-gray-900 dark:text-white' => (float) $summary->saldo_amount === 0.0,
                ])>
                    {{ $money($summary->saldo_amount) }}
                </td>
                <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 py-3.5 pr-4 pl-3 backdrop-blur-sm backdrop-filter sm:pr-6 lg:pr-8 dark:border-white/15 dark:bg-gray-900/75"></td>
            </tr>
        </x-slot:stickySummary>
    </x-ui.sticky-table>
@endsection
