@php
    $money = static fn ($value) => number_format((float) $value, 0, ',', ' ');
@endphp

<tr>
    <x-ui.sticky-table-summary-label first :columns="3">
        Всего: {{ number_format((int) $summary->operations_count, 0, ',', ' ') }}
    </x-ui.sticky-table-summary-label>
    <x-ui.money-columns
        :amount="$summary->saldo_amount"
        :income="$summary->income_amount"
        :expense="$summary->expense_amount"
        summary
        :decimals="0"
    />
    <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
    <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
    <td @class([
        'sticky bottom-0 z-10 whitespace-nowrap border-t border-gray-300 bg-white/75 px-3 py-3.5 text-right text-sm font-semibold tabular-nums backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75',
        'text-emerald-700' => (float) $summary->saldo_amount > 0,
        'text-rose-700' => (float) $summary->saldo_amount < 0,
        'text-gray-900 dark:text-white' => (float) $summary->saldo_amount === 0.0,
    ])>
        {{ $money($summary->saldo_amount) }}
    </td>
    <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 py-3.5 pr-4 pl-3 backdrop-blur-sm backdrop-filter sm:pr-6 lg:pr-8 dark:border-white/15 dark:bg-gray-900/75"></td>
</tr>
