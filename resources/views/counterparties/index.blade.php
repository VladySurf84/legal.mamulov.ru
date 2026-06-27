@extends('layouts.app', ['title' => 'Контрагенты'])

@section('page_actions')
    @if (\App\Support\UserAccess::canRebuildCounterpartyLinks(auth()->user()))
    <form method="post" action="{{ route('counterparties.rebuild-links') }}" data-counterparties-rebuild-form>
        @csrf
        <input type="hidden" name="legal_id" value="{{ $filters['legal_id'] ?? '' }}" data-rebuild-legal-id>
        <input type="hidden" name="contractor_inn" value="{{ $filters['contractor_inn'] ?? '' }}" data-rebuild-contractor-inn>
        <input type="hidden" name="only_negative_diff" value="{{ ! empty($filters['only_negative_diff']) ? 1 : '' }}" data-rebuild-only-negative-diff>
        <x-ui.button type="submit" size="lg">
            Пересчитать связи
        </x-ui.button>
    </form>
    @endif
@endsection

@section('content')
    @if (session('status'))
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <x-ui.table-filters
        :action="route('counterparties.index')"
        rows-id="counterparties-rows"
        loader-id="counterparties-loader"
        table-selector="[data-counterparties-table]"
        summary-selector="#counterparties-summary"
        columns="lg:grid-cols-3"
        form-id="counterparties-filters"
    >
                <x-ui.select-with-secondary-text
                    label="Наше юрлицо"
                    name="legal_id"
                    :value="$filters['legal_id'] ?? ''"
                    :options="$legalEntities->map(fn ($legalEntity) => [
                        'value' => $legalEntity->legal_id,
                        'label' => $legalEntity->legal_name,
                        'secondary' => $legalEntity->legal_inn ? 'ИНН ' . $legalEntity->legal_inn : '',
                    ])->prepend([
                        'value' => '',
                        'label' => 'Все юрлица',
                        'secondary' => '',
                    ])->values()"
                />

                <x-ui.input
                    label="ИНН контрагента"
                    name="contractor_inn"
                    :value="$filters['contractor_inn'] ?? ''"
                    inputmode="numeric"
                    autocomplete="off"
                    data-ui-table-filter-input
                />

                <label class="flex items-end gap-3 pb-1 text-sm font-medium text-gray-900 dark:text-white">
                    <span class="grid size-5 shrink-0 place-items-center">
                        <input
                            type="checkbox"
                            name="only_negative_diff"
                            value="1"
                            @checked((bool) ($filters['only_negative_diff'] ?? false))
                            class="peer col-start-1 row-start-1 size-4 appearance-none rounded-sm border border-gray-300 bg-white checked:border-indigo-600 checked:bg-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:border-white/10 dark:bg-white/5 dark:checked:border-indigo-500 dark:checked:bg-indigo-500"
                        >
                        <svg viewBox="0 0 14 14" fill="none" class="pointer-events-none col-start-1 row-start-1 size-3.5 stroke-white opacity-0 peer-checked:opacity-100">
                            <path d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    Только с отрицательной разницей
                </label>
    </x-ui.table-filters>

    <div class="mb-4 flex flex-wrap gap-2" id="counterparties-summary">
        @include('counterparties.partials.summary', ['summary' => $summary])
    </div>

    @once
        <script>
            (() => {
                const syncCounterpartiesRebuildForm = () => {
                    const filterForm = document.getElementById('counterparties-filters');
                    const rebuildForm = document.querySelector('[data-counterparties-rebuild-form]');

                    if (!filterForm || !rebuildForm) {
                        return;
                    }

                    rebuildForm.querySelector('[data-rebuild-legal-id]').value = filterForm.querySelector('[name="legal_id"]')?.value || '';
                    rebuildForm.querySelector('[data-rebuild-contractor-inn]').value = filterForm.querySelector('[name="contractor_inn"]')?.value || '';
                    rebuildForm.querySelector('[data-rebuild-only-negative-diff]').value = filterForm.querySelector('[name="only_negative_diff"]')?.checked ? '1' : '';
                };

                document.addEventListener('change', (event) => {
                    if (event.target.closest('#counterparties-filters')) {
                        syncCounterpartiesRebuildForm();
                    }
                });

                document.addEventListener('input', (event) => {
                    if (event.target.closest('#counterparties-filters')) {
                        syncCounterpartiesRebuildForm();
                    }
                });

                document.addEventListener('ui:table-filters:updated', syncCounterpartiesRebuildForm);

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', syncCounterpartiesRebuildForm);
                } else {
                    syncCounterpartiesRebuildForm();
                }
            })();
        </script>
    @endonce

    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :sticky-summary-enabled="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
        body-id="counterparties-rows"
        data-counterparties-table
    >
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th first>Контрагент</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>ИНН</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Входящее</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Наше сальдо</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Книги покупок</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Разница</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Разница НДС</x-ui.sticky-table-th>
                <x-ui.money-columns-head />
                <x-ui.sticky-table-th align="right">Операций</x-ui.sticky-table-th>
                @if ($showLegalEntitiesCount)
                    <x-ui.sticky-table-th align="right">Наших юрлиц</x-ui.sticky-table-th>
                @endif
                <x-ui.sticky-table-th last align="right"></x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @include('counterparties.partials.rows', [
            'counterparties' => $counterparties,
            'filters' => $filters,
            'showLegalEntitiesCount' => $showLegalEntitiesCount,
            'emptyColspan' => $emptyColspan,
        ])

        @include('counterparties.partials.loader-row', [
            'nextPage' => $nextPage,
            'emptyColspan' => $emptyColspan,
        ])

        <x-slot:stickySummary>
            @include('counterparties.partials.foot', [
                'summary' => $summary,
                'showLegalEntitiesCount' => $showLegalEntitiesCount,
            ])
        </x-slot:stickySummary>
    </x-ui.sticky-table>

@endsection
