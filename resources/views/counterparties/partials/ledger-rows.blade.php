@foreach ($ledgerEntries as $entry)
    @php
        $bankAmount = null;
        $ledgerAmount = null;
        $showLegalEntityColumn = empty($filters['legal_id']);

        if ($entry->source_type === 'bank') {
            $incomeAmount = (float) ($entry->income_amount ?? 0);
            $expenseAmount = (float) ($entry->expense_amount ?? 0);
            $bankAmount = $incomeAmount !== 0.0 ? $incomeAmount : -$expenseAmount;
            $ledgerAmount = $bankAmount;
        } elseif ($entry->source_type === 'purchase_book' && $entry->purchase_amount !== null) {
            $ledgerAmount = (float) $entry->purchase_amount;
        }

        $sourceLabel = match ($entry->source_type) {
            'bank' => 'Банк',
            'opening_balance' => 'Входящее',
            default => 'Книга покупок',
        };

        $sourceClasses = match ($entry->source_type) {
            'bank' => 'bg-slate-100 text-slate-700 ring-slate-200',
            'opening_balance' => 'bg-amber-50 text-amber-700 ring-amber-200',
            default => 'bg-cyan-50 text-cyan-700 ring-cyan-200',
        };

        $vatRateTitle = null;
        $displayVatAmount = $entry->vat_amount !== null
            ? (float) $entry->vat_amount
            : null;

        if ($entry->source_type === 'bank' && $displayVatAmount !== null) {
            $displayVatAmount = (float) $entry->vat_reconciliation_amount;
        }

        if ($entry->vat_amount !== null && $ledgerAmount !== null) {
            $vatAmount = abs((float) $entry->vat_amount);
            $amountWithoutVat = abs($ledgerAmount) - $vatAmount;

            if ($amountWithoutVat > 0.0) {
                $vatRateTitle = number_format($vatAmount / $amountWithoutVat * 100, 2, '.', ' ').' %';
            }
        }
    @endphp

    <tr @class([
        'align-top hover:bg-gray-50 dark:hover:bg-white/5',
        'bg-emerald-50/40 dark:bg-emerald-500/10' => (bool) $entry->is_linked,
    ])>
        <x-ui.sticky-table-td first nowrap>
            {{ $entry->event_date ? \Illuminate\Support\Carbon::parse($entry->event_date)->format('d.m.Y') : '—' }}
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td nowrap>
            <div class="flex flex-wrap gap-1.5">
                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 {{ $sourceClasses }}">
                    {{ $sourceLabel }}
                </span>
                @if ((bool) $entry->is_linked)
                    <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200">
                        связано
                    </span>
                @endif
            </div>
        </x-ui.sticky-table-td>

        @if ($showLegalEntityColumn)
            <x-ui.sticky-table-td :nowrap="false">
                <span class="block whitespace-normal break-words">{{ $entry->legal_name ?? '—' }}</span>
            </x-ui.sticky-table-td>
        @endif

        <x-ui.sticky-table-td :nowrap="false">
            <div class="whitespace-normal break-words font-medium text-gray-900 dark:text-white">{{ $entry->primary_ref ?: '—' }}</div>
            @if ($entry->secondary_ref)
                <div class="mt-1 whitespace-normal break-words font-mono text-xs text-gray-400">{{ $entry->secondary_ref }}</div>
            @endif
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td align="right" nowrap>
            {{ $ledgerAmount !== null ? number_format($ledgerAmount, 2, ',', ' ') : '—' }}
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td align="right" nowrap strong>
            {{ number_format((float) $entry->running_saldo, 2, ',', ' ') }}
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td align="right" nowrap>
            @if ($displayVatAmount !== null)
                <span @if ($vatRateTitle !== null) title="{{ $vatRateTitle }}" @endif>
                    {{ number_format($displayVatAmount, 2, ',', ' ') }}
                </span>
            @else
                —
            @endif
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td align="right" nowrap strong>
            {{ number_format((float) $entry->running_vat_saldo, 2, ',', ' ') }}
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td last :nowrap="false">
            <div class="whitespace-normal break-words">{{ $entry->description ?: '—' }}</div>

            @if ($entry->source_type === 'opening_balance')
                <form class="mt-2" method="post" action="{{ route('counterparties.opening-balances.destroy', ['contractorInn' => $contractorInn, 'openingBalanceId' => $entry->source_id]) }}">
                    @csrf
                    @method('delete')
                    <input type="hidden" name="legal_id" value="{{ $filters['legal_id'] ?? '' }}">
                    <input type="hidden" name="page" value="{{ $ledgerPagination['page'] }}">
                    <button
                        class="rounded-md bg-rose-50 px-2.5 py-1.5 text-sm font-semibold text-rose-700 shadow-xs hover:bg-rose-100"
                        type="submit"
                    >
                        Удалить
                    </button>
                </form>
            @endif
        </x-ui.sticky-table-td>
    </tr>
@endforeach
