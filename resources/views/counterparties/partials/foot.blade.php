<tr>
    <th scope="row" class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 backdrop-blur-sm backdrop-filter sm:pl-6 lg:pl-8 dark:border-white/15 dark:bg-gray-900/75 dark:text-white">Итого: {{ number_format($summary['count'], 0, ',', ' ') }}</th>
    <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
    <td class="sticky bottom-0 z-10 whitespace-nowrap border-t border-gray-300 bg-white/75 px-3 py-3.5 text-right text-sm font-semibold tabular-nums text-gray-900 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75">{{ number_format($summary['opening_amount'], 2, ',', ' ') }}</td>
    <td class="sticky bottom-0 z-10 whitespace-nowrap border-t border-gray-300 bg-white/75 px-3 py-3.5 text-right text-sm font-semibold tabular-nums text-emerald-700 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75">{{ number_format($summary['saldo'], 2, ',', ' ') }}</td>
    <td class="sticky bottom-0 z-10 whitespace-nowrap border-t border-gray-300 bg-white/75 px-3 py-3.5 text-right text-sm font-semibold tabular-nums text-cyan-700 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75">{{ number_format($summary['buh_saldo'], 2, ',', ' ') }}</td>
    <td class="sticky bottom-0 z-10 whitespace-nowrap border-t border-gray-300 bg-white/75 px-3 py-3.5 text-right text-sm font-semibold tabular-nums text-indigo-700 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75">{{ number_format($summary['saldo_diff'], 2, ',', ' ') }}</td>
    <td class="sticky bottom-0 z-10 whitespace-nowrap border-t border-gray-300 bg-white/75 px-3 py-3.5 text-right text-sm font-semibold tabular-nums text-violet-700 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75">{{ number_format($summary['vat_diff'], 2, ',', ' ') }}</td>
    <x-ui.sticky-table-td summary align="right" nowrap money-tone="income" class="tabular-nums">{{ number_format($summary['income_amount'] ?? 0, 2, ',', ' ') }}</x-ui.sticky-table-td>
    <x-ui.sticky-table-td summary align="right" nowrap money-tone="expense" class="tabular-nums">{{ number_format($summary['expense_amount'] ?? 0, 2, ',', ' ') }}</x-ui.sticky-table-td>
    <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
    @if ($showLegalEntitiesCount)
        <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
    @endif
    <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 py-3.5 pr-4 pl-3 backdrop-blur-sm backdrop-filter sm:pr-6 lg:pr-8 dark:border-white/15 dark:bg-gray-900/75"></td>
</tr>
