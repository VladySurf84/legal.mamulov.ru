@foreach ($transactions as $transaction)
    <tr>
        <td>
            {{ optional(\Illuminate\Support\Carbon::parse($transaction->date))->format('d.m.Y') }}
            <div class="subtle code">#{{ $transaction->bank_transaction_id }}</div>
        </td>
        <td>
            <strong>{{ $transaction->legal_name ?? 'Юрлицо #' . $transaction->legal_id }}</strong>
            <div class="subtle">{{ $transaction->bank_account_name }}</div>
            <div class="subtle code">{{ $transaction->account_number }} · {{ $transaction->bank_id }}</div>
        </td>
        <td>
            {{ $transaction->name ?: '—' }}
            @if ($transaction->contractor_inn)
                <div class="subtle">ИНН {{ $transaction->contractor_inn }}</div>
            @endif
            @if ($transaction->contractor_bank_account)
                <div class="subtle code">{{ $transaction->contractor_bank_account }}</div>
            @endif
            <div class="badges" style="margin-top: 6px;">
                @if ((int) $transaction->has_vat === 1)<span class="badge">НДС</span>@endif
                @if ((int) $transaction->dohras === 1)<span class="badge">дох/рас</span>@endif
                @if ($transaction->type_alias)<span class="badge">{{ $transaction->type_alias }}</span>@endif
                @if ($transaction->k_id)<span class="badge">касса</span>@endif
            </div>
        </td>
        <td class="money">{{ $transaction->amount_p !== null ? number_format((float) $transaction->amount_p, 2, ',', ' ') : '' }}</td>
        <td class="money">{{ $transaction->amount_m !== null ? number_format((float) $transaction->amount_m, 2, ',', ' ') : '' }}</td>
        <td>
            {{ $transaction->payment_purpose }}
            <div class="subtle code">{{ $transaction->order_intraday }}</div>
        </td>
        <td class="money">{{ number_format((float) $transaction->total, 2, ',', ' ') }}</td>
    </tr>
@endforeach
