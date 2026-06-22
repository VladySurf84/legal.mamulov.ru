@if (count($transactions) > 0)
    @include('bank-transactions.partials.rows', [
        'transactions' => $transactions,
        'showAccountColumn' => $showAccountColumn,
    ])
@else
    <tr>
        <td class="py-8 text-center text-sm text-gray-500 dark:text-gray-400" colspan="{{ $tableColspan }}">Банковские транзакции по выбранным фильтрам не найдены.</td>
    </tr>
@endif
