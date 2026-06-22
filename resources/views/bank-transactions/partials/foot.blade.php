<tr>
    <th scope="row" colspan="{{ $showAccountColumn ? 3 : 2 }}" class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 backdrop-blur-sm backdrop-filter sm:pl-6 lg:pl-8 dark:border-white/15 dark:bg-gray-900/75 dark:text-white">Итого операций: {{ $summary['count'] }}</th>
    <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-[var(--bank-income-bg)] px-3 py-3.5 text-right text-sm font-semibold tabular-nums text-emerald-700 backdrop-blur-sm backdrop-filter dark:border-white/15">
        {{ number_format($summary['income'], 2, ',', ' ') }}
    </td>
    <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-[var(--bank-expense-bg)] px-3 py-3.5 text-right text-sm font-semibold tabular-nums text-rose-700 backdrop-blur-sm backdrop-filter dark:border-white/15">
        {{ number_format($summary['expense'], 2, ',', ' ') }}
    </td>
    <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
    <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 py-3.5 pr-4 pl-3 backdrop-blur-sm backdrop-filter sm:pr-6 lg:pr-8 dark:border-white/15 dark:bg-gray-900/75"></td>
</tr>
