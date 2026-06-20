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

        $vatRateTitle = null;
        if ($entry->vat_amount !== null && $ledgerAmount !== null) {
            $vatAmount = abs((float) $entry->vat_amount);
            $amountWithoutVat = abs($ledgerAmount) - $vatAmount;

            if ($amountWithoutVat > 0.0) {
                $vatRateTitle = number_format($vatAmount / $amountWithoutVat * 100, 2, '.', ' ').' %';
            }
        }
    @endphp
    <tr @class(['linked-ledger-row' => (bool) $entry->is_linked])>
        <td>{{ $entry->event_date ? \Illuminate\Support\Carbon::parse($entry->event_date)->format('d.m.Y') : '—' }}</td>
        <td>
            <span class="badge">
                @if ($entry->source_type === 'bank')
                    Банк
                @elseif ($entry->source_type === 'opening_balance')
                    Входящее
                @else
                    Книга покупок
                @endif
            </span>
            @if ((bool) $entry->is_linked)
                <span class="badge linked-ledger-badge">связано</span>
            @endif
        </td>
        @if ($showLegalEntityColumn)
            <td>{{ $entry->legal_name ?? '—' }}</td>
        @endif
        <td>
            <div>{{ $entry->primary_ref ?: '—' }}</div>
            @if ($entry->secondary_ref)
                <div class="subtle code">{{ $entry->secondary_ref }}</div>
            @endif
        </td>
        <td class="money">{{ $ledgerAmount !== null ? number_format($ledgerAmount, 2, ',', ' ') : '—' }}</td>
        <td class="money">{{ number_format((float) $entry->reconciliation_amount, 2, ',', ' ') }}</td>
        <td class="money">{{ number_format((float) $entry->running_saldo, 2, ',', ' ') }}</td>
        <td class="money">
            @if ($entry->vat_amount !== null)
                <span @if ($vatRateTitle !== null) title="{{ $vatRateTitle }}" @endif>
                    {{ number_format((float) $entry->vat_amount, 2, ',', ' ') }}
                </span>
            @else
                —
            @endif
        </td>
        <td class="money">{{ number_format((float) $entry->running_vat_saldo, 2, ',', ' ') }}</td>
        <td>
            <div>{{ $entry->description ?: '—' }}</div>
            @if ($entry->source_type === 'opening_balance')
                <form method="post" action="{{ route('counterparties.opening-balances.destroy', ['contractorInn' => $contractorInn, 'openingBalanceId' => $entry->source_id]) }}" style="margin-top: 8px;">
                    @csrf
                    @method('delete')
                    <input type="hidden" name="legal_id" value="{{ $filters['legal_id'] ?? '' }}">
                    <input type="hidden" name="page" value="{{ $ledgerPagination['page'] }}">
                    <button class="danger" type="submit">Удалить</button>
                </form>
            @endif
        </td>
    </tr>
@endforeach
