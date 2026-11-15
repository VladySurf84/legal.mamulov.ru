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
        head-selector="[data-counterparties-table] thead"
        summary-selector="#counterparties-summary"
        columns="lg:grid-cols-[max-content_minmax(180px,1fr)_max-content]"
        form-id="counterparties-filters"
    >
                <label for="counterparties-legal-id" class="sr-only">Наше юрлицо</label>
                <x-ui.animated-multi-select
                    id="counterparties-legal-id"
                    name="legal_id"
                    :multiple="false"
                    placeholder="Все юрлица"
                    :value="$filters['legal_id'] ?? ''"
                    :options="$legalEntities->map(fn ($legalEntity) => [
                        'value' => $legalEntity->legal_id,
                        'label' => $legalEntity->legal_name,
                        'secondary' => $legalEntity->legal_inn ? 'ИНН ' . $legalEntity->legal_inn : '',
                    ])->values()"
                />

                <x-ui.input
                    placeholder="ИНН контрагента"
                    aria-label="ИНН контрагента"
                    name="contractor_inn"
                    :value="$filters['contractor_inn'] ?? ''"
                    inputmode="numeric"
                    autocomplete="off"
                    data-ui-table-filter-input
                />

                <div class="flex items-center">
                    <x-ui.native-checkbox
                        name="only_negative_diff"
                        :checked="(bool) ($filters['only_negative_diff'] ?? false)"
                        label="Только с отрицательной разницей"
                    />
                </div>
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

                    const filters = new FormData(filterForm);
                    rebuildForm.querySelector('[data-rebuild-legal-id]').value = filters.get('legal_id') || '';
                    rebuildForm.querySelector('[data-rebuild-contractor-inn]').value = filters.get('contractor_inn') || '';
                    rebuildForm.querySelector('[data-rebuild-only-negative-diff]').value = filters.get('only_negative_diff') === '1' ? '1' : '';
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
            @include('counterparties.partials.head', ['showLegalEntitiesCount' => $showLegalEntitiesCount])
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
