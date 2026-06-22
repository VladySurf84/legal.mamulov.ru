<tr id="vat-book-entries-loader-row" @class(['hidden' => ! $nextPage])>
    <td class="h-[20vh] py-3 text-center align-middle text-sm text-slate-500" colspan="10">
        <div id="vat-book-entries-loader" data-next-page="{{ $nextPage }}">
            <span data-loader-spinner @class(['hidden' => ! $nextPage])>
                <x-ui.loading :overlay="false" label="Загрузка строк книги" />
            </span>
            <span data-loader-error class="hidden text-rose-600">Не удалось загрузить следующую страницу.</span>
        </div>
    </td>
</tr>
