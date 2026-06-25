@foreach ($transactions as $transaction)
    @php
        $transactionDate = optional(\Illuminate\Support\Carbon::parse($transaction->date))->format('d.m.Y');
        $operationAmount = (float) ($transaction->amount ?? 0);
        $incomeAmountValue = $transaction->amount_m !== null ? (float) $transaction->amount_m : null;
        $expenseAmountValue = $transaction->amount_p !== null ? (float) $transaction->amount_p : null;
        $incomeAmount = $incomeAmountValue !== null ? number_format($incomeAmountValue, 2, ',', ' ') : '';
        $expenseAmount = $expenseAmountValue !== null ? number_format($expenseAmountValue, 2, ',', ' ') : '';
        $signedAmount = number_format($operationAmount, 2, ',', ' ');
        $totalAmount = number_format((float) $transaction->total, 2, ',', ' ');
        $operationTypeLabel = $transaction->type_alias
            ? trim($transaction->type_alias . ' · ' . ($transaction->operation_type_name ?: 'Неизвестный тип'))
            : '—';
        $contractorTitle = $transaction->contractor_inn ? 'ИНН ' . $transaction->contractor_inn : null;
        $hasBadges = (int) $transaction->has_vat === 1 || (int) $transaction->dohras === 1 || (bool) $transaction->k_id;
    @endphp

    <tr
        class="align-top hover:bg-gray-50"
        data-bank-transaction-context-row
        data-bank-transaction-id="{{ $transaction->bank_transaction_id }}"
        data-bank-transaction-reconciliation-id="{{ $transaction->reconciliation_id }}"
        data-bank-transaction-date="{{ $transactionDate }}"
        data-bank-transaction-legal="{{ $transaction->legal_name ?? 'Юрлицо #' . $transaction->legal_id }}"
        data-bank-transaction-legal-id="{{ $transaction->legal_id }}"
        data-bank-transaction-account-name="{{ $transaction->bank_account_name }}"
        data-bank-transaction-account-number="{{ $transaction->account_number }}"
        data-bank-transaction-bank-id="{{ $transaction->bank_id }}"
        data-bank-transaction-contractor="{{ $transaction->name ?: '—' }}"
        data-bank-transaction-contractor-inn="{{ $transaction->contractor_inn ?: '—' }}"
        data-bank-transaction-contractor-account="{{ $transaction->contractor_bank_account ?: '—' }}"
        data-bank-transaction-income="{{ $incomeAmount ?: '—' }}"
        data-bank-transaction-expense="{{ $expenseAmount ?: '—' }}"
        data-bank-transaction-amount="{{ $signedAmount }}"
        data-bank-transaction-total="{{ $totalAmount }}"
        data-bank-transaction-payment-purpose="{{ $transaction->payment_purpose ?: '—' }}"
        data-bank-transaction-order-intraday="{{ $transaction->order_intraday ?: '—' }}"
        data-bank-transaction-type="{{ $transaction->type_alias ?: '—' }}"
        data-bank-transaction-type-label="{{ $operationTypeLabel }}"
        data-bank-transaction-type-name="{{ $transaction->operation_type_name ?: '—' }}"
        data-bank-transaction-type-description="{{ $transaction->operation_type_description ?: '—' }}"
        data-bank-transaction-vat="{{ (int) $transaction->has_vat === 1 ? 'Да' : 'Нет' }}"
        data-bank-transaction-kassa="{{ $transaction->k_id ?: '—' }}"
    >
        <x-ui.sticky-table-td first nowrap>
            <div class="font-medium tabular-nums text-gray-900 dark:text-white">
                {{ $transactionDate }}
            </div>
        </x-ui.sticky-table-td>
        @if ($showAccountColumn)
            <x-ui.sticky-table-td :nowrap="false">
                <div class="font-semibold text-gray-900 dark:text-white">{{ $transaction->legal_name ?? 'Юрлицо #' . $transaction->legal_id }}</div>
                <div class="mt-1 text-sm text-gray-500">{{ $transaction->bank_account_name }}</div>
                <div class="mt-1 font-mono text-xs text-gray-400">{{ $transaction->account_number }} · {{ $transaction->bank_id }}</div>
            </x-ui.sticky-table-td>
        @endif
        <x-ui.money-columns
            :amount="$operationAmount"
            :income="$incomeAmountValue"
            :expense="$expenseAmountValue"
            cell-class="font-medium"
        />
        <x-ui.sticky-table-td :nowrap="false">
            <div class="flex items-start gap-1.5 font-medium text-gray-900 dark:text-white">
                @if ($transaction->k_id)
                    <span class="mt-0.5 inline-flex size-4 shrink-0 items-center justify-center text-amber-600" title="Связано с кассой #{{ $transaction->k_id }}">
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                            <path d="M12.232 4.232a3.5 3.5 0 0 1 4.95 4.95l-2.5 2.5a3.5 3.5 0 0 1-5.305-.43.75.75 0 0 1 1.245-.838 2 2 0 0 0 3 .207l2.5-2.5a2 2 0 1 0-2.829-2.828l-1.088 1.088a.75.75 0 0 1-1.06-1.06l1.087-1.089Z" />
                            <path d="M10.623 8.748a.75.75 0 0 1-1.246.838 2 2 0 0 0-3-.207l-2.5 2.5a2 2 0 1 0 2.829 2.828l1.088-1.088a.75.75 0 0 1 1.06 1.06l-1.087 1.089a3.5 3.5 0 0 1-4.95-4.95l2.5-2.5a3.5 3.5 0 0 1 5.306.43Z" />
                        </svg>
                    </span>
                @endif

                <span @if ($contractorTitle) title="{{ $contractorTitle }}" @endif>{{ $transaction->name ?: '—' }}</span>
            </div>
            @if ($hasBadges)
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @if ((int) $transaction->has_vat === 1)
                        <span class="inline-flex rounded-full bg-cyan-50 px-2 py-0.5 text-xs font-medium text-cyan-700 ring-1 ring-cyan-200">НДС</span>
                    @endif
                    @if ((int) $transaction->dohras === 1)
                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-gray-200">дох/рас</span>
                    @endif
                    @if ($transaction->k_id)
                        <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-amber-200">касса</span>
                    @endif
                </div>
            @endif
        </x-ui.sticky-table-td>
        <x-ui.sticky-table-td :nowrap="false">
            <div class="text-gray-700 dark:text-gray-300">{{ $transaction->payment_purpose }}</div>
        </x-ui.sticky-table-td>
        <x-ui.sticky-table-td last align="right" nowrap strong class="tabular-nums">
            {{ $totalAmount }}
        </x-ui.sticky-table-td>
    </tr>
@endforeach
