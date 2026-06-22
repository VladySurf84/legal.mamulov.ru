@foreach ($transactions as $transaction)
    <tr class="align-top hover:bg-gray-50" data-bank-transaction-context-row data-bank-transaction-id="{{ $transaction->bank_transaction_id }}">
        <td class="whitespace-nowrap border-b border-gray-200 py-4 pr-3 pl-4 text-sm text-gray-500 sm:pl-6 lg:pl-8 dark:border-white/10 dark:text-gray-400">
            <div class="font-medium tabular-nums text-gray-900 dark:text-white">
                {{ optional(\Illuminate\Support\Carbon::parse($transaction->date))->format('d.m.Y') }}
            </div>
            <div class="mt-1 font-mono text-xs text-gray-400">#{{ $transaction->bank_transaction_id }}</div>
        </td>
        @if ($showAccountColumn)
            <td class="min-w-64 border-b border-gray-200 px-3 py-4 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                <div class="font-semibold text-gray-900 dark:text-white">{{ $transaction->legal_name ?? 'Юрлицо #' . $transaction->legal_id }}</div>
                <div class="mt-1 text-sm text-gray-500">{{ $transaction->bank_account_name }}</div>
                <div class="mt-1 font-mono text-xs text-gray-400">{{ $transaction->account_number }} · {{ $transaction->bank_id }}</div>
            </td>
        @endif
        <td class="min-w-64 border-b border-gray-200 px-3 py-4 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
            <div class="font-medium text-gray-900 dark:text-white">{{ $transaction->name ?: '—' }}</div>
            @if ($transaction->contractor_inn)
                <div class="mt-1 text-xs text-gray-500">ИНН {{ $transaction->contractor_inn }}</div>
            @endif
            @if ($transaction->contractor_bank_account)
                <div class="mt-1 font-mono text-xs text-gray-400">{{ $transaction->contractor_bank_account }}</div>
            @endif
            <div class="mt-2 flex flex-wrap gap-1.5">
                @if ((int) $transaction->has_vat === 1)
                    <span class="inline-flex rounded-full bg-cyan-50 px-2 py-0.5 text-xs font-medium text-cyan-700 ring-1 ring-cyan-200">НДС</span>
                @endif
                @if ((int) $transaction->dohras === 1)
                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-gray-200">дох/рас</span>
                @endif
                @if ($transaction->type_alias)
                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-gray-200">{{ $transaction->type_alias }}</span>
                @endif
                @if ($transaction->k_id)
                    <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-amber-200">касса</span>
                @endif
            </div>
        </td>
        <td class="whitespace-nowrap border-b border-gray-200 bg-[var(--bank-income-bg)] px-3 py-4 text-right text-sm font-medium tabular-nums text-emerald-700 dark:border-white/10">
            {{ $transaction->amount_m !== null ? number_format((float) $transaction->amount_m, 2, ',', ' ') : '' }}
        </td>
        <td class="whitespace-nowrap border-b border-gray-200 bg-[var(--bank-expense-bg)] px-3 py-4 text-right text-sm font-medium tabular-nums text-rose-700 dark:border-white/10">
            {{ $transaction->amount_p !== null ? number_format((float) $transaction->amount_p, 2, ',', ' ') : '' }}
        </td>
        <td class="min-w-[28rem] border-b border-gray-200 px-3 py-4 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
            <div class="text-gray-700 dark:text-gray-300">{{ $transaction->payment_purpose }}</div>
            <div class="mt-1 font-mono text-xs text-gray-400">{{ $transaction->order_intraday }}</div>
        </td>
        <td class="whitespace-nowrap border-b border-gray-200 py-4 pr-4 pl-3 text-right text-sm font-semibold tabular-nums text-gray-900 sm:pr-8 lg:pr-8 dark:border-white/10 dark:text-white">
            {{ number_format((float) $transaction->total, 2, ',', ' ') }}
        </td>
    </tr>
@endforeach
