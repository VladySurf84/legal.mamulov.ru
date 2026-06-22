<tr id="bank-transactions-loader-row">
    <td class="h-[20vh] py-3 text-center align-middle text-sm text-slate-500" colspan="{{ $tableColspan }}">
        <div id="bank-transactions-loader" data-next-page="{{ $nextPage }}">
            @if ($nextPage)
                Загрузка при прокрутке...
            @endif
        </div>
    </td>
</tr>
