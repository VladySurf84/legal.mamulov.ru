<tr id="vat-book-entries-loader-row">
    <td class="h-[20vh] py-3 text-center align-middle text-sm text-slate-500" colspan="10">
        <div id="vat-book-entries-loader" data-next-page="{{ $nextPage }}">
            @if ($nextPage)
                Загрузка при прокрутке...
            @endif
        </div>
    </td>
</tr>
