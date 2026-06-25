<tr id="kassa-loader-row" data-ui-sticky-table-loader-row @class(['hidden' => ! $nextPage])>
    <td class="h-[20vh] py-3 text-center align-middle text-sm text-slate-500" colspan="{{ $tableColspan }}">
        <div id="kassa-loader" data-ui-sticky-table-loader data-next-page="{{ $nextPage }}">
            <span data-loader-spinner @class(['hidden' => ! $nextPage])>
                <x-ui.loading :overlay="false" label="Загрузка кассовых операций" />
            </span>
            <span data-loader-error class="hidden text-rose-600">Не удалось загрузить следующую страницу.</span>
        </div>
    </td>
</tr>
