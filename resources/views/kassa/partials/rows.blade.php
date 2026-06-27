@php
    $displayTimezone = $displayTimezone ?? config('app.display_timezone', 'Europe/Moscow');
    $money = static fn ($value) => number_format((float) $value, 0, ',', ' ');
    $date = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse((string) $value, 'UTC')->timezone($displayTimezone)->format('d.m.Y H:i') : '—';
@endphp

@foreach ($operations as $operation)
    @php
        $operationDate = $date($operation->time);
        $createdDate = $date($operation->created);
        $isFreshManualOperation = $operation->kassa_id
            && $operation->kassa_created
            && \Illuminate\Support\Carbon::parse((string) $operation->kassa_created, 'UTC')->greaterThanOrEqualTo(now('UTC')->subDays($freshEntryDays ?? 7));
        $canEditOperation = ($canCreateCashEntry ?? false)
            && $operation->kassa_id
            && (($canEditAnyCashEntry ?? false) || $isFreshManualOperation);
        $canDeleteOperation = $operation->kassa_id
            && (($canDeleteAnyCashEntry ?? false) || (($canDeleteFreshCashEntry ?? false) && $isFreshManualOperation));
        $incomeAmount = (float) $operation->amount > 0 ? $money($operation->amount) : '';
        $expenseAmount = (float) $operation->amount < 0 ? $money(abs((float) $operation->amount)) : '';
        $sourceRecord = $operation->source_document_bank_transaction_id
            ? 'bank transaction #' . $operation->source_document_bank_transaction_id
            : ($operation->kassa_id ? 'kassa #' . $operation->kassa_id : '—');
        $documentLabel = $operation->document_id
            ? trim('document #' . $operation->document_id . ' ' . ($operation->document_external_id ?: $operation->document_title ?: ''))
            : '—';
    @endphp

    <tr
        class="align-top hover:bg-gray-50 dark:hover:bg-white/5"
        data-kassa-context-row
        data-kassa-cash-entry-id="{{ $operation->cash_entry_id }}"
        @if ($canEditOperation)
            data-kassa-edit-url="{{ route('kassa.update', ['kassaId' => $operation->kassa_id]) }}"
            data-kassa-edit-direction="{{ (float) $operation->amount > 0 ? 'income' : 'expense' }}"
            data-kassa-edit-amount="{{ abs((int) $operation->amount) }}"
            data-kassa-edit-article-id="{{ $operation->article_id ?: '' }}"
            data-kassa-edit-description="{{ $operation->description }}"
        @endif
        @if ($canDeleteOperation)
            data-kassa-delete-url="{{ route('kassa.destroy', ['kassaId' => $operation->kassa_id]) }}"
        @endif
        data-kassa-source-type="{{ $operation->source_type }}"
        data-kassa-source-label="{{ $operation->source_label }}"
        data-kassa-operation-date="{{ $operationDate }}"
        data-kassa-created-date="{{ $operation->created ? $createdDate : '—' }}"
        data-kassa-article="{{ $operation->article ?: 'Без статьи' }}"
        data-kassa-article-id="{{ $operation->article_id ?: '—' }}"
        data-kassa-income="{{ $incomeAmount ?: '—' }}"
        data-kassa-expense="{{ $expenseAmount ?: '—' }}"
        data-kassa-amount="{{ $money($operation->amount) }}"
        data-kassa-running-total="{{ $money($operation->running_total) }}"
        data-kassa-description="{{ $operation->description ?: '—' }}"
        data-kassa-document="{{ $documentLabel }}"
        data-kassa-document-id="{{ $operation->document_id ?: '—' }}"
        data-kassa-document-external-id="{{ $operation->document_external_id ?: '—' }}"
        data-kassa-source-record="{{ $sourceRecord }}"
        data-kassa-bank-transaction-id="{{ $operation->source_document_bank_transaction_id ?: '—' }}"
        data-kassa-kassa-id="{{ $operation->kassa_id ?: '—' }}"
        data-kassa-rule-id="{{ $operation->cash_operation_rule_id ?: '—' }}"
        data-kassa-legal="{{ $operation->legal_name ?: '—' }}"
        data-kassa-legal-id="{{ $operation->legal_id ?: '—' }}"
    >
        <x-ui.sticky-table-td first nowrap class="tabular-nums">
            {{ $operationDate }}
        </x-ui.sticky-table-td>

        <x-ui.money-columns
            :amount="$operation->amount"
            :income="(float) $operation->amount > 0 ? (float) $operation->amount : null"
            :expense="(float) $operation->amount < 0 ? (float) $operation->amount : null"
            :decimals="0"
            cell-class="font-semibold"
        />

        <x-ui.sticky-table-td :nowrap="false">
            <div class="whitespace-normal break-words text-gray-900 dark:text-white">{{ $operation->article ?: 'Без статьи' }}</div>
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td :nowrap="false">
            <div class="whitespace-normal break-words">{{ $operation->description }}</div>
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
