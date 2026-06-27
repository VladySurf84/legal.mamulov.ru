@extends('layouts.app', [
    'title' => 'Документы',
    'titleDescription' => 'Канонический слой документов: нормализованные банковские операции, бухгалтерские документы, ЭДО и ручные записи после распознавания источников.',
])

@php
    $money = static fn ($value) => $value !== null ? number_format((float) $value, 2, ',', ' ') : '—';

    $documentTypeOptions = $documentTypes
        ->mapWithKeys(fn ($type) => [
            (string) $type->document_type_id => trim(($type->document_group ? $type->document_group . ' · ' : '') . $type->name),
        ])
        ->all();

    $sourceSystemOptions = $sourceSystems
        ->mapWithKeys(fn ($sourceSystem) => [(string) $sourceSystem => (string) $sourceSystem])
        ->all();

    $statusOptions = $statuses
        ->mapWithKeys(fn ($status) => [(string) $status => (string) $status])
        ->all();
@endphp

@section('content')
    <div class="mb-4 border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <form method="get" action="{{ route('documents.index') }}">
            <div class="grid gap-4 lg:grid-cols-4">
                <x-ui.select
                    label="Тип документа"
                    name="document_type_id"
                    :value="$filters['document_type_id'] ?? ''"
                    :options="$documentTypeOptions"
                    placeholder="Все типы"
                />

                <x-ui.select
                    label="Источник"
                    name="source_system"
                    :value="$filters['source_system'] ?? ''"
                    :options="$sourceSystemOptions"
                    placeholder="Все источники"
                />

                <x-ui.select
                    label="Статус"
                    name="status"
                    :value="$filters['status'] ?? ''"
                    :options="$statusOptions"
                    placeholder="Все статусы"
                />

                <x-ui.input
                    label="Поиск"
                    name="q"
                    :value="$filters['q'] ?? ''"
                    placeholder="Номер, название, ИНН, контрагент"
                />
            </div>

            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <x-ui.button :href="route('documents.index')" variant="ghost" wire:navigate>
                    Сбросить
                </x-ui.button>

                <x-ui.button type="submit" variant="soft">
                    Показать
                </x-ui.button>
            </div>
        </form>
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
                <x-ui.sticky-table-th first>Документ</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Дата</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Тип</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Стороны</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Источник</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Статус</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Сумма</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last>Детали</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @forelse ($documents as $document)
            @php
                $bankTransaction = $document->bankTransactions->first();
            @endphp

            <tr class="align-top hover:bg-gray-50 dark:hover:bg-white/5">
                <x-ui.sticky-table-td first :nowrap="false" strong>
                    <div>{{ $document->title ?: 'Документ #' . $document->document_id }}</div>
                    <div class="mt-1 flex flex-wrap gap-2 text-xs font-normal text-gray-500 dark:text-gray-400">
                        <span>ID {{ $document->document_id }}</span>
                        @if ($document->document_number)
                            <span>№ {{ $document->document_number }}</span>
                        @endif
                        @if ($document->external_id)
                            <span class="font-mono">{{ $document->external_id }}</span>
                        @endif
                    </div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="tabular-nums">
                    {{ $document->document_date?->format('d.m.Y') ?? '—' }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false">
                    <div class="font-medium text-gray-900 dark:text-white">{{ $document->type?->name ?? '—' }}</div>
                    @if ($document->type?->code)
                        <div class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $document->type->code }}</div>
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false">
                    <div class="space-y-2">
                        @forelse ($document->parties as $party)
                            <div>
                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                    {{ $party->roleDefinition?->name ?? $party->roleDefinition?->code ?? 'Участник' }}
                                </div>
                                <div class="font-medium text-gray-900 dark:text-white">{{ $party->name_snapshot }}</div>
                                @if ($party->inn_snapshot || $party->kpp_snapshot)
                                    <div class="mt-0.5 font-mono text-xs text-gray-500 dark:text-gray-400">
                                        @if ($party->inn_snapshot) ИНН {{ $party->inn_snapshot }} @endif
                                        @if ($party->kpp_snapshot) КПП {{ $party->kpp_snapshot }} @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <span class="text-gray-400">—</span>
                        @endforelse
                    </div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    <div>{{ $document->source_system ?: '—' }}</div>
                    @if ($document->imported_at)
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $document->imported_at->format('d.m.Y H:i') }}</div>
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    {{ $document->status }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="font-semibold tabular-nums" align="right" strong>
                    {{ $money($document->amount) }}
                    <span class="ml-1 text-xs font-medium text-gray-500">{{ $document->currency }}</span>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td last :nowrap="false">
                    @if ($bankTransaction)
                        <div class="font-medium text-gray-900 dark:text-white">
                            {{ $bankTransaction->payment_purpose ?: 'Банковская операция' }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ trim($bankTransaction->account_number) }}
                            @if ($bankTransaction->order_intraday)
                                · {{ $bankTransaction->order_intraday }}
                            @endif
                        </div>
                    @else
                        <div class="text-gray-400">—</div>
                    @endif
                </x-ui.sticky-table-td>
            </tr>
        @empty
            <tr>
                <td class="py-12 text-center text-sm text-gray-500 dark:text-gray-400" colspan="8">
                    Документы пока не найдены.
                </td>
            </tr>
        @endforelse
    </x-ui.sticky-table>

    @if ($documents->count() === 300)
        <p class="mt-3 px-4 text-xs text-gray-500 sm:px-6 lg:px-8">
            Показаны первые 300 документов по текущему фильтру.
        </p>
    @endif
@endsection
