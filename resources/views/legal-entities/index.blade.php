@extends('layouts.app', ['title' => 'Наши юридические лица'])

@section('content')
    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
    >
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th first>Юрлицо</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>ИНН</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>ОГРН</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Налоговый режим</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Налог</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">НДС</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last align="right">Счетов</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @forelse ($legalEntities as $legalEntity)
            <tr class="align-top hover:bg-gray-50">
                <x-ui.sticky-table-td first :nowrap="false">
                    <div class="flex items-start gap-3">
                        <span
                            class="mt-1 inline-flex size-3 shrink-0 rounded-full ring-1 ring-black/10"
                            style="background-color: {{ $legalEntity->legal_color ?: '#e5e7eb' }}"
                        ></span>
                        <div class="min-w-0">
                            <div class="font-medium text-gray-900">{{ $legalEntity->legal_name }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $legalEntity->legal_fullname }}</div>
                            @if ($legalEntity->legal_comment)
                                <div class="mt-1 text-xs text-gray-400">{{ $legalEntity->legal_comment }}</div>
                            @endif
                        </div>
                    </div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="font-mono tabular-nums">
                    {{ $legalEntity->legal_inn ?: $legalEntity->legal_id }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="font-mono tabular-nums">
                    {{ $legalEntity->legal_ogrn ?: '—' }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    {{ $legalEntity->tax_system ?: '—' }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="tabular-nums" align="right">
                    {{ $legalEntity->tax_rate !== null ? number_format((float) $legalEntity->tax_rate, 2, ',', ' ') . ' %' : '—' }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="tabular-nums" align="right">
                    {{ $legalEntity->vat_rate !== null ? number_format((float) $legalEntity->vat_rate, 0, ',', ' ') . ' %' : '—' }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="font-semibold tabular-nums" last align="right" strong>
                    {{ $legalEntity->bank_accounts_count }}
                </x-ui.sticky-table-td>
            </tr>
        @empty
            <tr>
                <td class="py-8 text-center text-sm text-gray-500 dark:text-gray-400" colspan="7">
                    Наши юридические лица пока не загружены.
                </td>
            </tr>
        @endforelse
    </x-ui.sticky-table>
@endsection
