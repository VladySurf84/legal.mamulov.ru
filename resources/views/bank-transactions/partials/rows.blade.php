@foreach ($transactions as $transaction)
    <tr class="align-top hover:bg-slate-50">
        <td class="whitespace-nowrap px-4 py-3">
            <div class="font-medium tabular-nums text-slate-900">
                {{ optional(\Illuminate\Support\Carbon::parse($transaction->date))->format('d.m.Y') }}
            </div>
            <div class="mt-1 font-mono text-xs text-slate-400">#{{ $transaction->bank_transaction_id }}</div>
        </td>
        <td class="min-w-64 px-4 py-3">
            <div class="font-semibold text-slate-900">{{ $transaction->legal_name ?? 'Юрлицо #' . $transaction->legal_id }}</div>
            <div class="mt-1 text-sm text-slate-500">{{ $transaction->bank_account_name }}</div>
            <div class="mt-1 font-mono text-xs text-slate-400">{{ $transaction->account_number }} · {{ $transaction->bank_id }}</div>
        </td>
        <td class="min-w-64 px-4 py-3">
            <div class="font-medium text-slate-900">{{ $transaction->name ?: '—' }}</div>
            @if ($transaction->contractor_inn)
                <div class="mt-1 text-xs text-slate-500">ИНН {{ $transaction->contractor_inn }}</div>
            @endif
            @if ($transaction->contractor_bank_account)
                <div class="mt-1 font-mono text-xs text-slate-400">{{ $transaction->contractor_bank_account }}</div>
            @endif
            <div class="mt-2 flex flex-wrap gap-1.5">
                @if ((int) $transaction->has_vat === 1)
                    <span class="inline-flex rounded-full bg-cyan-50 px-2 py-0.5 text-xs font-medium text-cyan-700 ring-1 ring-cyan-200">НДС</span>
                @endif
                @if ((int) $transaction->dohras === 1)
                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-slate-200">дох/рас</span>
                @endif
                @if ($transaction->type_alias)
                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-slate-200">{{ $transaction->type_alias }}</span>
                @endif
                @if ($transaction->k_id)
                    <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-amber-200">касса</span>
                @endif
            </div>
        </td>
        <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums text-emerald-700">
            {{ $transaction->amount_m !== null ? number_format((float) $transaction->amount_m, 2, ',', ' ') : '' }}
        </td>
        <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums text-rose-700">
            {{ $transaction->amount_p !== null ? number_format((float) $transaction->amount_p, 2, ',', ' ') : '' }}
        </td>
        <td class="min-w-[28rem] px-4 py-3">
            <div class="text-slate-700">{{ $transaction->payment_purpose }}</div>
            <div class="mt-1 font-mono text-xs text-slate-400">{{ $transaction->order_intraday }}</div>
        </td>
        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-slate-900">
            {{ number_format((float) $transaction->total, 2, ',', ' ') }}
        </td>
    </tr>
@endforeach
