@extends('layouts.app', ['title' => 'VAT layer', 'titleAttribute' => 'Интерпретационный слой НДС из книг бухгалтера и банковских назначений платежа.'])

@section('page_actions')
    <div class="flex flex-wrap items-center gap-2">
            @if (\App\Support\UserAccess::canRebuildVatLayer(auth()->user()))
            <form method="post" action="{{ route('vat-layer.rebuild') }}">
                @csrf
                <x-ui.button type="submit" size="md" variant="ghost">Пересчитать книги</x-ui.button>
            </form>
            @endif
            @if (\App\Support\UserAccess::canRebuildBankVatLayer(auth()->user()))
            <form method="post" action="{{ route('vat-layer.rebuild-bank') }}">
                @csrf
                <x-ui.button type="submit" size="md" variant="ghost">Пересчитать банк</x-ui.button>
            </form>
            @endif
    </div>
@endsection

@section('content')
    @if (session('status'))
        <div class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
    @endif


        <x-ui.table-filters :action="route('vat-layer.index')" :ajax="false" columns="sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">

                <div class="min-w-0">
                    <x-ui.select id="source_system" name="source_system" label="Источник">
                        <option value="">Все источники</option>
                        <option value="accountant_vat_book" @selected(($filters['source_system'] ?? '') === 'accountant_vat_book')>Книги бухгалтера</option>
                        <option value="bank_payment_vat" @selected(($filters['source_system'] ?? '') === 'bank_payment_vat')>Наш расчет по банку</option>
                    </x-ui.select>
                </div>
                <div class="min-w-0">
                    <x-ui.select id="legal_id" name="legal_id" label="Наше юрлицо">
                        <option value="">Все юрлица</option>
                        @foreach ($legalEntities as $legalEntity)
                            <option value="{{ $legalEntity->legal_id }}" @selected((string) ($filters['legal_id'] ?? '') === (string) $legalEntity->legal_id)>
                                {{ $legalEntity->legal_name }}@if ($legalEntity->legal_inn) · ИНН {{ $legalEntity->legal_inn }}@endif
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div class="min-w-0">
                    <x-ui.input
                        id="contractor_inn"
                        name="contractor_inn"
                        label="ИНН контрагента"
                        :value="$filters['contractor_inn'] ?? ''"
                        inputmode="numeric"
                    />
                </div>
                <div class="min-w-0">
                    <div class="block text-sm/6 font-medium text-gray-900 dark:text-white">Период</div>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <x-ui.input
                            name="year" aria-label="Год"
                            :value="$filters['year'] ?? ''"
                            placeholder="2026"
                            inputmode="numeric"
                        />
                        <x-ui.select name="quarter" aria-label="Квартал">
                            <option value="">Все кварталы</option>
                            @foreach ([1, 2, 3, 4] as $quarter)
                                <option value="{{ $quarter }}" @selected((string) ($filters['quarter'] ?? '') === (string) $quarter)>Q{{ $quarter }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                </div>
                <div class="min-w-0">
                    <x-ui.select id="direction" name="direction" label="Направление">
                        <option value="">Все</option>
                        <option value="output" @selected(($filters['direction'] ?? '') === 'output')>Начисленный НДС</option>
                        <option value="input" @selected(($filters['direction'] ?? '') === 'input')>Вычет НДС</option>
                    </x-ui.select>
                </div>
            <x-slot:actions>
                <x-ui.button href="{{ route('vat-layer.index') }}" size="lg" variant="ghost" wire:navigate>Сбросить</x-ui.button>
                <x-ui.button type="submit" size="lg" variant="soft">Показать</x-ui.button>
            </x-slot:actions>
        </x-ui.table-filters>

    <div class="mb-4 flex flex-wrap gap-2">
        <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">Событий: {{ $summary['count'] }}</span>
        <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">Начислено: {{ number_format($summary['output_vat'], 2, ',', ' ') }}</span>
        <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">Вычет: {{ number_format($summary['input_vat'], 2, ',', ' ') }}</span>
        <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">Итого к уплате: {{ number_format($summary['vat_balance'], 2, ',', ' ') }}</span>
    </div>

    <x-ui.sticky-table :contained="false" :scrollable="true" :viewport-sticky="true" :bottom-scrollbar="true" scroll-class="overflow-x-auto overflow-y-visible">
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th first>Источник</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Период</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Дата</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Тип</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Юрлицо</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Контрагент</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Документ</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Сумма</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last align="right">НДС</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>
            @forelse ($events as $event)
                <tr class="align-top hover:bg-gray-50 dark:hover:bg-white/5">
                    <x-ui.sticky-table-td first nowrap>{{ $event->source_system === 'bank_payment_vat' ? 'Банк' : 'Книга' }}</x-ui.sticky-table-td>
                    <x-ui.sticky-table-td nowrap>{{ $event->year }} Q{{ $event->quarter }}</x-ui.sticky-table-td>
                    <x-ui.sticky-table-td nowrap>{{ $event->occurred_on ?? '—' }}</x-ui.sticky-table-td>
                    <x-ui.sticky-table-td :nowrap="false">{{ $event->vat_direction === 'input' ? 'Вычет' : 'Начисление' }}</x-ui.sticky-table-td>
                    <x-ui.sticky-table-td :nowrap="false">{{ $event->legal_name }}</x-ui.sticky-table-td>
                    <x-ui.sticky-table-td :nowrap="false">
                        <strong>{{ $event->contractor_name ?? '—' }}</strong>
                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $event->contractor_inn ?? '' }}</div>
                    </x-ui.sticky-table-td>
                    <x-ui.sticky-table-td :nowrap="false">
                        <span class="font-mono text-xs">{{ $event->invoice_number ?? '—' }}</span>
                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $event->invoice_date ?? '' }}</div>
                    </x-ui.sticky-table-td>
                    <x-ui.sticky-table-td align="right" nowrap class="tabular-nums">{{ $event->amount_total !== null ? number_format((float) $event->amount_total, 2, ',', ' ') : '—' }}</x-ui.sticky-table-td>
                    <x-ui.sticky-table-td align="right" nowrap last class="font-semibold tabular-nums text-gray-900 dark:text-white">{{ number_format((float) $event->signed_vat_amount, 2, ',', ' ') }}</x-ui.sticky-table-td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">VAT layer пока пуст. Пересчитай слой после импорта книг НДС или банковских документов.</td>
                </tr>
            @endforelse
    </x-ui.sticky-table>
@endsection
