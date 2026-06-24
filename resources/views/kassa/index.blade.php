@extends('layouts.app', [
    'title' => 'Касса',
    'titleDescription' => 'Ручные денежные операции из legal.kassa. Эти записи автоматически отражаются как manual_cash_operation в документах и Money layer.',
])

@php
    $displayTimezone = config('app.display_timezone', 'Europe/Moscow');
    $money = static fn ($value) => number_format((float) $value, 2, ',', ' ');
    $date = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse((string) $value, 'UTC')->timezone($displayTimezone)->format('d.m.Y H:i') : '—';
@endphp

@section('content')
    <div class="mb-4 border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <form method="get" action="{{ route('kassa.index') }}">
            <div class="grid gap-4 lg:grid-cols-5">
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

                <x-ui.airdatepicker.date-range
                    label="Период"
                    name-from="date_from"
                    name-to="date_to"
                    :value-from="$filters['date_from'] ?? null"
                    :value-to="$filters['date_to'] ?? null"
                />

                <label class="grid gap-1.5 text-sm font-medium text-gray-900 dark:text-white lg:col-span-2">
                    <span>Поиск</span>
                    <input
                        class="h-10 rounded-md bg-white px-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500"
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        placeholder="Описание, статья, юрлицо или ИНН"
                    >
                </label>
            </div>

            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <x-ui.button :href="route('kassa.index')" variant="ghost" wire:navigate>
                    Сбросить
                </x-ui.button>

                <x-ui.button type="submit" variant="soft">
                    Показать
                </x-ui.button>
            </div>
        </form>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        <span class="inline-flex rounded-full bg-gray-50 px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-200">
            Операций: {{ number_format((int) $summary->operations_count, 0, ',', ' ') }}
        </span>
        <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700 ring-1 ring-emerald-200">
            Приход: {{ $money($summary->income_amount) }}
        </span>
        <span class="inline-flex rounded-full bg-rose-50 px-3 py-1 text-sm font-medium text-rose-700 ring-1 ring-rose-200">
            Расход: {{ $money($summary->expense_amount) }}
        </span>
        <span @class([
            'inline-flex rounded-full px-3 py-1 text-sm font-medium ring-1',
            'bg-emerald-50 text-emerald-700 ring-emerald-200' => (float) $summary->saldo_amount > 0,
            'bg-rose-50 text-rose-700 ring-rose-200' => (float) $summary->saldo_amount < 0,
            'bg-gray-50 text-gray-700 ring-gray-200' => (float) $summary->saldo_amount === 0.0,
        ])>
            Итого: {{ $money($summary->saldo_amount) }}
        </span>
    </div>

    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
        table-class="!min-w-[1300px]"
    >
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th first>Дата</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Юрлицо</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Статья</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Приход</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Расход</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Описание</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Документ</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last align="right">ID</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @forelse ($operations as $operation)
            <tr class="align-top hover:bg-gray-50 dark:hover:bg-white/5">
                <x-ui.sticky-table-td first nowrap class="tabular-nums">
                    {{ $date($operation->time) }}
                    @if ($operation->created)
                        <div class="mt-1 text-xs text-gray-400">создано: {{ $date($operation->created) }}</div>
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false" class="min-w-72">
                    <div class="font-medium text-gray-900 dark:text-white">{{ $operation->legal_name ?? 'Юрлицо #' . $operation->legal_id }}</div>
                    <div class="mt-1 font-mono text-xs text-gray-400">ИНН {{ $operation->legal_inn ?? $operation->legal_id }}</div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false" class="min-w-56">
                    <div class="whitespace-normal break-words text-gray-900 dark:text-white">{{ $operation->article ?: 'Без статьи' }}</div>
                    @if ($operation->article_id)
                        <div class="mt-1 font-mono text-xs text-gray-400">#{{ $operation->article_id }}</div>
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td align="right" nowrap class="tabular-nums">
                    @if ((float) $operation->amount > 0)
                        <span class="font-semibold text-emerald-700">{{ $money($operation->amount) }}</span>
                    @else
                        —
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td align="right" nowrap class="tabular-nums">
                    @if ((float) $operation->amount < 0)
                        <span class="font-semibold text-rose-700">{{ $money(abs((float) $operation->amount)) }}</span>
                    @else
                        —
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false" class="min-w-96">
                    <div class="whitespace-normal break-words">{{ $operation->description }}</div>
                    @if ($operation->reconciliation_id)
                        <div class="mt-1 font-mono text-xs text-gray-400">reconciliation #{{ $operation->reconciliation_id }}</div>
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false" class="min-w-72">
                    @if ($operation->document_id)
                        <div class="font-mono text-xs text-gray-500 dark:text-gray-400">document #{{ $operation->document_id }}</div>
                        <div class="mt-1 whitespace-normal break-words text-xs text-gray-500 dark:text-gray-400">
                            {{ $operation->document_external_id ?: $operation->document_title }}
                        </div>
                    @else
                        —
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td last align="right" nowrap class="font-mono text-xs">
                    #{{ $operation->kassa_id }}
                </x-ui.sticky-table-td>
            </tr>
        @empty
            <tr>
                <td class="py-12 text-center text-sm text-gray-500 dark:text-gray-400" colspan="8">
                    Кассовые операции пока не найдены.
                </td>
            </tr>
        @endforelse
    </x-ui.sticky-table>

    @if ($operations->count() === 500)
        <p class="mt-3 px-4 text-xs text-gray-500 sm:px-6 lg:px-8">
            Показаны первые 500 операций по текущему фильтру.
        </p>
    @endif
@endsection
