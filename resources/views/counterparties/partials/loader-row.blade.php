<tr id="counterparties-loader-row" @class(['hidden' => ! $nextPage])>
    <td class="h-[20vh] py-3 text-center align-middle text-sm text-slate-500" colspan="{{ $emptyColspan }}">
        <div id="counterparties-loader" data-next-page="{{ $nextPage }}">
            <span data-loader-spinner @class(['hidden' => ! $nextPage])>
                <x-ui.loading :overlay="false" label="Загрузка контрагентов" />
            </span>
            <span data-loader-error class="hidden text-rose-600">Не удалось загрузить следующую страницу.</span>
        </div>
    </td>
</tr>
