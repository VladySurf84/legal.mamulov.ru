@extends('layouts.app', [
    'title' => 'Money layer',
    'titleDescription' => 'Интерпретационный слой денежных ребер: нормализованные документы превращаются в движение денег между участниками графа.',
])

@php
    $legalEntityOptions = $legalEntities->map(fn ($legalEntity) => [
        'value' => (string) $legalEntity->legal_id,
        'label' => $legalEntity->legal_name,
        'secondary' => 'ИНН ' . ($legalEntity->legal_inn ?: $legalEntity->legal_id),
    ]);
@endphp

@section('page_actions')
    @if (\App\Support\UserAccess::canRebuildMoneyLayer(auth()->user()))
    <form method="post" action="{{ route('money-layer.rebuild') }}">
        @csrf
        <x-ui.button type="submit" size="lg">
            Пересчитать слой
        </x-ui.button>
    </form>
    @endif
@endsection

@section('content')
    @if (session('status'))
        <div class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-800 ring-1 ring-green-600/20 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-500/20">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 rounded-lg bg-white px-4 py-5 shadow-sm ring-1 ring-gray-900/5 sm:px-6 dark:bg-gray-800 dark:ring-white/10">
        <form method="get" action="{{ route('money-layer.index') }}" class="grid gap-4 lg:grid-cols-12 lg:items-end">
            <div class="lg:col-span-3">
                <x-ui.select-with-secondary-text
                    name="legal_id"
                    label="Наше юрлицо"
                    placeholder="Все юрлица"
                    :value="$filters['legal_id'] ?? ''"
                    :options="$legalEntityOptions"
                    selected-layout="stacked"
                />
            </div>

            <div class="lg:col-span-2">
                <x-ui.input
                    id="contractor_inn"
                    name="contractor_inn"
                    label="ИНН контрагента"
                    :value="$filters['contractor_inn'] ?? ''"
                    inputmode="numeric"
                />
            </div>

            <div class="lg:col-span-3">
                <x-ui.input
                    id="party"
                    name="party"
                    label="Участник / ИНН"
                    :value="$filters['party'] ?? ''"
                />
            </div>

            <div class="lg:col-span-3">
                <x-ui.airdatepicker.date-range
                    label="Период"
                    :value-from="$filters['date_from'] ?? null"
                    :value-to="$filters['date_to'] ?? null"
                />
            </div>

            <div class="flex gap-2 lg:col-span-1 lg:justify-end">
                <x-ui.button href="{{ route('money-layer.index') }}" size="lg" variant="ghost" wire:navigate>
                    Сбросить
                </x-ui.button>
                <x-ui.button type="submit" size="lg" variant="soft">
                    Показать
                </x-ui.button>
            </div>
        </form>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg bg-white px-4 py-4 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">Ребер</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ number_format($summary['count'], 0, ',', ' ') }}</div>
        </div>

        <div class="rounded-lg bg-white px-4 py-4 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">Сумма</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ number_format($summary['total_amount'], 2, ',', ' ') }}</div>
        </div>

        <div class="rounded-lg bg-white px-4 py-4 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">Период</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">
                {{ $summary['min_date'] ?? '—' }} → {{ $summary['max_date'] ?? '—' }}
            </div>
        </div>
    </div>

    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
    >
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th first>Дата</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Откуда</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Куда</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Сумма</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Счет / операция</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last>Назначение</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @forelse ($edges as $edge)
            <tr class="align-top hover:bg-gray-50 dark:hover:bg-white/5">
                <x-ui.sticky-table-td first nowrap>
                    {{ $edge->occurred_on }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false">
                    <div class="font-medium text-gray-900 dark:text-white">{{ $edge->payer_name_snapshot ?: '—' }}</div>
                    <div class="mt-0.5 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $edge->payer_inn_snapshot ?: '' }}</div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false">
                    <div class="font-medium text-gray-900 dark:text-white">{{ $edge->recipient_name_snapshot ?: '—' }}</div>
                    <div class="mt-0.5 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $edge->recipient_inn_snapshot ?: '' }}</div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td align="right" class="font-semibold tabular-nums text-gray-900 dark:text-white" nowrap>
                    {{ number_format((float) $edge->amount, 2, ',', ' ') }} {{ $edge->currency }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    <div class="font-mono text-xs text-gray-900 dark:text-white">{{ $edge->account_number ?: $edge->algorithm }}</div>
                    <div class="mt-0.5 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $edge->external_operation_id ?: 'document #' . $edge->source_document_id }}</div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td last :nowrap="false">
                    {{ $edge->payment_purpose ?: '—' }}
                </x-ui.sticky-table-td>
            </tr>
        @empty
            <tr>
                <td class="py-8 text-center text-sm text-gray-500 dark:text-gray-400" colspan="6">
                    Денежный слой пока пуст. Пересчитай слой после загрузки банковских документов.
                </td>
            </tr>
        @endforelse
    </x-ui.sticky-table>
@endsection
