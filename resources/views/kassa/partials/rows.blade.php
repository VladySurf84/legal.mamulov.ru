@php
    $displayTimezone = $displayTimezone ?? config('app.display_timezone', 'Europe/Moscow');
    $money = static fn ($value) => number_format((float) $value, 0, ',', ' ');
    $date = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse((string) $value, 'UTC')->timezone($displayTimezone)->format('d.m.Y H:i') : '—';
@endphp

@foreach ($operations as $operation)
    <tr class="align-top hover:bg-gray-50 dark:hover:bg-white/5">
        <x-ui.sticky-table-td first nowrap class="tabular-nums">
            {{ $date($operation->time) }}
            @if ($operation->created)
                <div class="mt-1 text-xs text-gray-400">создано: {{ $date($operation->created) }}</div>
            @endif
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td :nowrap="false">
            <div @class([
                'inline-flex rounded-full px-2 py-1 text-xs font-medium ring-1',
                'bg-gray-50 text-gray-700 ring-gray-200' => $operation->source_type === 'manual_kassa',
                'bg-indigo-50 text-indigo-700 ring-indigo-200' => $operation->source_type === 'bank_rule',
            ])>
                {{ $operation->source_label }}
            </div>

            @if ($operation->cash_operation_rule_id)
                <div class="mt-1 font-mono text-xs text-gray-400">rule #{{ $operation->cash_operation_rule_id }}</div>
            @endif
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td :nowrap="false">
            <div class="whitespace-normal break-words text-gray-900 dark:text-white">{{ $operation->article ?: 'Без статьи' }}</div>
            @if ($operation->article_id)
                <div class="mt-1 font-mono text-xs text-gray-400">#{{ $operation->article_id }}</div>
            @endif
        </x-ui.sticky-table-td>

        <x-ui.money-columns
            :amount="$operation->amount"
            :income="(float) $operation->amount > 0 ? (float) $operation->amount : null"
            :expense="(float) $operation->amount < 0 ? (float) $operation->amount : null"
            :decimals="0"
            cell-class="font-semibold"
        />

        <x-ui.sticky-table-td :nowrap="false">
            <div class="whitespace-normal break-words">{{ $operation->description }}</div>
            @if ($operation->source_document_bank_transaction_id)
                <div class="mt-1 font-mono text-xs text-gray-400">bank transaction #{{ $operation->source_document_bank_transaction_id }}</div>
            @elseif ($operation->kassa_id)
                <div class="mt-1 font-mono text-xs text-gray-400">kassa #{{ $operation->kassa_id }}</div>
            @endif
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td :nowrap="false">
            @if ($operation->document_id)
                <div class="font-mono text-xs text-gray-500 dark:text-gray-400">document #{{ $operation->document_id }}</div>
                <div class="mt-1 whitespace-normal break-words text-xs text-gray-500 dark:text-gray-400">
                    {{ $operation->document_external_id ?: $operation->document_title }}
                </div>
            @else
                —
            @endif
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td align="right" nowrap strong class="tabular-nums">
            <span @class([
                'text-emerald-700' => (float) $operation->running_total > 0,
                'text-rose-700' => (float) $operation->running_total < 0,
            ])>
                {{ $money($operation->running_total) }}
            </span>
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td last align="right" nowrap class="font-mono text-xs">
            #{{ $operation->cash_entry_id }}
        </x-ui.sticky-table-td>
    </tr>
@endforeach
