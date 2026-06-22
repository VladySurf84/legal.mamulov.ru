<tr id="counterparties-loader-row">
    <td class="h-[20vh] py-3 text-center align-middle text-sm text-slate-500" colspan="{{ $emptyColspan }}">
        <div id="counterparties-loader" data-next-page="{{ $nextPage }}">
            @if ($nextPage)
                Загрузка при прокрутке...
            @endif
        </div>
    </td>
</tr>
