@foreach ($ledgerEntries as $entry)
    @php
        $bankAmount = null;

        if ($entry->source_type === 'bank') {
            $incomeAmount = (float) ($entry->income_amount ?? 0);
            $expenseAmount = (float) ($entry->expense_amount ?? 0);
            $bankAmount = $incomeAmount !== 0.0 ? $incomeAmount : -$expenseAmount;
        }
    @endphp
    <tr>
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
        </td>
        <td>{{ $entry->legal_name ?? '—' }}</td>
        <td>
            <div>{{ $entry->primary_ref ?: '—' }}</div>
            @if ($entry->secondary_ref)
                <div class="subtle code">{{ $entry->secondary_ref }}</div>
            @endif
        </td>
        <td class="money">{{ $bankAmount !== null ? number_format($bankAmount, 2, ',', ' ') : '—' }}</td>
        <td class="money">{{ $entry->purchase_amount !== null ? number_format((float) $entry->purchase_amount, 2, ',', ' ') : '—' }}</td>
        <td class="money">{{ $entry->vat_amount !== null ? number_format((float) $entry->vat_amount, 2, ',', ' ') : '—' }}</td>
        <td class="money">{{ number_format((float) $entry->reconciliation_amount, 2, ',', ' ') }}</td>
        <td class="money">{{ number_format((float) $entry->running_saldo, 2, ',', ' ') }}</td>
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
