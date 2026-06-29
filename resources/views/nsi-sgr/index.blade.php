@extends('layouts.app', [
    'title' => 'СГР',
    'titleDescription' => 'Единый реестр свидетельств о государственной регистрации НСИ ЕАЭС',
])

@php
    $date = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse((string) $value)->format('d.m.Y') : '—';
    $dateTime = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse((string) $value)->format('d.m.Y H:i') : '—';
    $count = static fn ($value) => number_format((int) $value, 0, ',', ' ');
@endphp

@section('content')
    <div class="mb-4 border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <form method="get" action="{{ route('nsi-sgr.index') }}">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
                <x-ui.input
                    label="Поиск"
                    name="q"
                    :value="$filters['q'] ?? ''"
                    placeholder="Номер СГР, продукция, изготовитель, получатель"
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
            </div>

            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <x-ui.button :href="route('nsi-sgr.index')" variant="ghost" wire:navigate>
                    Сбросить
                </x-ui.button>

                <x-ui.button type="submit" variant="soft">
                    Показать
                </x-ui.button>
            </div>
        </form>
    </div>

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
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
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

        @forelse ($records as $record)
            <tr class="align-top hover:bg-gray-50 dark:hover:bg-white/5">
                <x-ui.sticky-table-td first :nowrap="false" class="min-w-72">
                    <div class="font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ $record->sgr_number }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $record->serial_number ? 'серия '.$record->serial_number : 'серия —' }}
                        @if ($record->detail_payload)
                            · детализация
                        @endif
                    </div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false">
                    <span @class([
                        'inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1',
                        'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20' => $record->status_name === 'подписан и действует',
                        'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10' => $record->status_name !== 'подписан и действует',
                    ])>
                        {{ $record->status_name ?: '—' }}
                    </span>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="font-mono text-sm">
                    {{ $date($record->document_date) }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false" class="min-w-96 max-w-2xl">
                    <div class="font-medium text-gray-900 dark:text-white">{{ $record->product_name ?: '—' }}</div>
                    @if ($record->product_code)
                        <div class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $record->product_code }}</div>
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false" class="min-w-72 max-w-xl">
                    <div class="text-gray-900 dark:text-white">{{ $record->manufacturer_name ?: '—' }}</div>
                    @if ($record->manufacturer_address)
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $record->manufacturer_address }}</div>
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false" class="min-w-72 max-w-xl">
                    <div class="text-gray-900 dark:text-white">{{ $record->recipient_name ?: '—' }}</div>
                    @if ($record->recipient_inn || $record->recipient_address)
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $record->recipient_inn ?: 'ИНН —' }}
                            @if ($record->recipient_address)
                                · {{ $record->recipient_address }}
                            @endif
                        </div>
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false" class="min-w-80 max-w-xl">
                    {{ $record->use_area ?: '—' }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td last class="font-mono text-xs text-gray-500 dark:text-gray-400">
                    <div>{{ $dateTime($record->update_date_time) }}</div>
                    <div class="mt-1">список: {{ $dateTime($record->list_synced_at) }}</div>
                    <div class="mt-1">карточка: {{ $dateTime($record->detail_synced_at) }}</div>
                </x-ui.sticky-table-td>
            </tr>
        @empty
            <tr>
                <td class="py-12 text-center text-sm text-gray-500 dark:text-gray-400" colspan="8">
                    СГР пока не загружены.
                </td>
            </tr>
        @endforelse
    </x-ui.sticky-table>

    <div class="mt-4 px-4 sm:px-6 lg:px-8">
        {{ $records->links() }}
    </div>
@endsection
