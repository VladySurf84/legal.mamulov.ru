@php
    $date = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse((string) $value)->format('d.m.Y') : '—';
    $dateTime = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse((string) $value)->format('d.m.Y H:i') : '—';
    $tableColspan = $tableColspan ?? 8;
@endphp

@forelse ($records as $record)
    <tr
        class="cursor-pointer align-top hover:bg-gray-50 dark:hover:bg-white/5"
        data-nsi-sgr-context-row
        data-nsi-sgr-detail-url="{{ route('nsi-sgr.show', ['recordId' => $record->nsi_sgr_record_id]) }}"
        data-nsi-sgr-number="{{ $record->sgr_number }}"
        data-nsi-sgr-product="{{ $record->product_name }}"
    >
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
        <td class="py-12 text-center text-sm text-gray-500 dark:text-gray-400" colspan="{{ $tableColspan }}">
            СГР пока не загружены.
        </td>
    </tr>
@endforelse
