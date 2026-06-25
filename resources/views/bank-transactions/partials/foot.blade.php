<tr>
    @php
        $signedSummary = $summary['income'] - $summary['expense'];
    @endphp

    <x-ui.sticky-table-summary-label first :columns="$showAccountColumn ? 2 : 1">
        Всего: {{ $summary['count'] }}
    </x-ui.sticky-table-summary-label>
    <x-ui.money-columns
        :amount="$signedSummary"
        :income="$summary['income']"
        :expense="$summary['expense']"
        cell-padding="px-3 py-3.5"
        summary
    />
    <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
    <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
    <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 py-3.5 pr-4 pl-3 backdrop-blur-sm backdrop-filter sm:pr-6 lg:pr-8 dark:border-white/15 dark:bg-gray-900/75"></td>
</tr>
