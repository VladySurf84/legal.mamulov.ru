        @forelse ($currencies as $currency)
            <tr class="align-top hover:bg-gray-50">
                <x-ui.sticky-table-td class="font-mono text-base font-semibold tracking-wide text-gray-900" first>
                    {{ $currency->currency_code }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="font-mono text-base font-semibold tracking-wide text-gray-900">
                    {{ $currency->alpha_code }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false">
                    <div class="font-medium text-gray-900">{{ $currency->name_ru }}</div>
                    <div class="mt-1 text-xs text-gray-500">{{ $currency->name_en }}</div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="tabular-nums" align="right">
                    {{ $currency->minor_units ?? '—' }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="font-semibold tabular-nums" align="right" strong>
                    {{ number_format((int) $currency->usage_count, 0, ',', ' ') }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="tabular-nums" align="right">
                    {{ number_format((int) $currency->bank_accounts_count, 0, ',', ' ') }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="tabular-nums" align="right">
                    {{ number_format((int) $currency->documents_count, 0, ',', ' ') }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="tabular-nums" align="right">
                    {{ number_format((int) $currency->bank_transactions_count, 0, ',', ' ') }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="tabular-nums" align="right">
                    {{ number_format((int) $currency->money_edges_count, 0, ',', ' ') }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="tabular-nums" last align="right">
                    {{ number_format((int) $currency->vat_book_entries_count, 0, ',', ' ') }}
                </x-ui.sticky-table-td>
            </tr>
        @empty
            <tr>
                <td class="py-8 text-center text-sm text-gray-500 dark:text-gray-400" colspan="10">
                    {{ $search !== '' ? 'Валюты не найдены.' : 'Справочник валют пока не заполнен.' }}
                </td>
            </tr>
        @endforelse
