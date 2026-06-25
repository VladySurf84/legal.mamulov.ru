@forelse ($counterparties as $counterparty)
    <tr class="align-top hover:bg-gray-50">
        <x-ui.sticky-table-td first strong :nowrap="false">
            <span class="block whitespace-normal break-words">
                {{ $counterparty->contractor_name }}
            </span>
        </x-ui.sticky-table-td>
        <x-ui.sticky-table-td nowrap>
            <span class="font-mono text-xs">{{ $counterparty->contractor_inn }}</span>
        </x-ui.sticky-table-td>
        <x-ui.sticky-table-td align="right" nowrap>
            {{ number_format((float) $counterparty->opening_amount, 2, ',', ' ') }}
        </x-ui.sticky-table-td>
        <x-ui.sticky-table-td align="right" nowrap>
            {{ number_format((float) $counterparty->saldo, 2, ',', ' ') }}
        </x-ui.sticky-table-td>
        <x-ui.sticky-table-td align="right" nowrap>
            {{ number_format((float) $counterparty->buh_saldo, 2, ',', ' ') }}
        </x-ui.sticky-table-td>
        <x-ui.sticky-table-td align="right" nowrap>
            <span @class([
                'font-semibold',
                'text-rose-700' => (float) $counterparty->saldo_diff < 0,
                'text-emerald-700' => (float) $counterparty->saldo_diff > 0,
            ])>
                {{ number_format((float) $counterparty->saldo_diff, 2, ',', ' ') }}
            </span>
        </x-ui.sticky-table-td>
        <x-ui.sticky-table-td align="right" nowrap>
            <span @class([
                'font-semibold',
                'text-rose-700' => (float) $counterparty->vat_diff < 0,
                'text-emerald-700' => (float) $counterparty->vat_diff > 0,
            ])>
                {{ number_format((float) $counterparty->vat_diff, 2, ',', ' ') }}
            </span>
        </x-ui.sticky-table-td>
        <x-ui.sticky-table-td align="right" nowrap>
            {{ number_format((float) $counterparty->income_amount, 2, ',', ' ') }}
        </x-ui.sticky-table-td>
        <x-ui.sticky-table-td align="right" nowrap>
            {{ number_format((float) $counterparty->expense_amount, 2, ',', ' ') }}
        </x-ui.sticky-table-td>
        <x-ui.sticky-table-td align="right" nowrap>
            {{ number_format((int) $counterparty->operations_count, 0, ',', ' ') }}
        </x-ui.sticky-table-td>
        @if ($showLegalEntitiesCount)
            <x-ui.sticky-table-td align="right" nowrap>
                {{ number_format((int) $counterparty->legal_entities_count, 0, ',', ' ') }}
            </x-ui.sticky-table-td>
        @endif
        <x-ui.sticky-table-td last align="right" nowrap>
            <x-ui.button href="{{ route('counterparties.show', ['contractorInn' => $counterparty->contractor_inn, 'legal_id' => $filters['legal_id'] ?? null]) }}" size="md" wire:navigate>
                Детализация
            </x-ui.button>
        </x-ui.sticky-table-td>
    </tr>
@empty
    <tr>
        <td class="py-8 text-center text-sm text-gray-500 dark:text-gray-400" colspan="{{ $emptyColspan }}">По этим фильтрам контрагентов нет.</td>
    </tr>
@endforelse
