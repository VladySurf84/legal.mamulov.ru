@forelse ($entries as $entry)
    <tr class="align-top hover:bg-gray-50 dark:hover:bg-white/5">
        <x-ui.sticky-table-td first nowrap>
            {{ $entry->year }} Q{{ $entry->quarter }}
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td nowrap>
            <span @class([
                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1',
                'bg-cyan-50 text-cyan-700 ring-cyan-200' => $entry->book_type === 'purchase',
                'bg-indigo-50 text-indigo-700 ring-indigo-200' => $entry->book_type === 'sales',
            ])>
                {{ $bookLabels[$entry->book_type] ?? $entry->book_type }}
            </span>
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td align="right" nowrap>
            {{ number_format((int) $entry->row_number, 0, ',', ' ') }}
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td :nowrap="false">
            <div class="whitespace-normal break-words font-medium text-gray-900 dark:text-white">{{ $entry->legal_name }}</div>
            <div class="mt-1 font-mono text-xs text-gray-400">ИНН {{ $entry->legal_inn }}</div>
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td :nowrap="false">
            <div class="whitespace-normal break-words font-medium text-gray-900 dark:text-white">{{ $entry->invoice_number ?: '—' }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ $entry->invoice_date ? \Illuminate\Support\Carbon::parse($entry->invoice_date)->format('d.m.Y') : '' }}
            </div>
            @if ($entry->correction_invoice_number)
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Корр. {{ $entry->correction_invoice_number }}</div>
            @endif
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td :nowrap="false">
            <div class="whitespace-normal break-words font-medium text-gray-900 dark:text-white">{{ $entry->contractor_name ?: '—' }}</div>
            <div class="mt-1 whitespace-normal break-words text-xs text-gray-500 dark:text-gray-400">
                ИНН {{ $entry->contractor_inn ?: '—' }}
                @if ($entry->contractor_kpp)
                    · КПП {{ $entry->contractor_kpp }}
                @endif
            </div>
            @if ($entry->operation_code)
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Код {{ $entry->operation_code }}</div>
            @endif
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td :nowrap="false">
            <div class="whitespace-normal break-words font-medium text-gray-900 dark:text-white">{{ $entry->payment_doc_number ?: '—' }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ $entry->payment_doc_date ? \Illuminate\Support\Carbon::parse($entry->payment_doc_date)->format('d.m.Y') : '' }}
            </div>
            @if ($entry->acceptance_date)
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Принят {{ \Illuminate\Support\Carbon::parse($entry->acceptance_date)->format('d.m.Y') }}</div>
            @endif
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td align="right" nowrap>
            {{ $entry->amount_total !== null ? number_format((float) $entry->amount_total, 2, ',', ' ') : '—' }}
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td align="right" nowrap>
            {{ $entry->amount_without_vat !== null ? number_format((float) $entry->amount_without_vat, 2, ',', ' ') : '—' }}
        </x-ui.sticky-table-td>

        <x-ui.sticky-table-td last align="right" nowrap>
            {{ $entry->vat_amount !== null ? number_format((float) $entry->vat_amount, 2, ',', ' ') : '—' }}
        </x-ui.sticky-table-td>
    </tr>
@empty
    <tr>
        <td class="py-8 text-center text-sm text-gray-500 dark:text-gray-400" colspan="10">
            По этим фильтрам строк нет.
        </td>
    </tr>
@endforelse
