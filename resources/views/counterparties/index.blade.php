@extends('layouts.app', ['title' => 'Контрагенты'])

@section('page_actions')
    <form method="post" action="{{ route('counterparties.rebuild-links') }}" data-counterparties-rebuild-form>
        @csrf
        <input type="hidden" name="legal_id" value="{{ $filters['legal_id'] ?? '' }}" data-rebuild-legal-id>
        <input type="hidden" name="contractor_inn" value="{{ $filters['contractor_inn'] ?? '' }}" data-rebuild-contractor-inn>
        <input type="hidden" name="only_negative_diff" value="{{ ! empty($filters['only_negative_diff']) ? 1 : '' }}" data-rebuild-only-negative-diff>
        <x-ui.button type="submit" size="lg">
            Пересчитать связи
        </x-ui.button>
    </form>
@endsection

@section('content')
    @if (session('status'))
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-4 rounded-lg border border-slate-200 bg-white shadow-sm">
        <form class="p-4" method="get" action="{{ route('counterparties.index') }}" data-counterparties-filter-form>
            <div class="grid gap-4 lg:grid-cols-3">
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

                <label class="block">
                    <span class="block text-sm/6 font-medium text-gray-900 dark:text-white">ИНН контрагента</span>
                    <input
                        class="mt-2 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus-visible:outline-indigo-500"
                        name="contractor_inn"
                        value="{{ $filters['contractor_inn'] ?? '' }}"
                        inputmode="numeric"
                        autocomplete="off"
                        data-counterparties-filter-input
                    >
                </label>

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
            </div>
        </form>
    </div>

    <div class="mb-4 flex flex-wrap gap-2" id="counterparties-summary">
        @include('counterparties.partials.summary', ['summary' => $summary])
    </div>

    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :sticky-summary-enabled="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
        body-id="counterparties-rows"
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

    @once
        <script>
            (() => {
                const tableRows = () => document.getElementById('counterparties-rows');
                const tableLoader = () => document.getElementById('counterparties-loader');
                const stickySummaryBody = () => document.querySelector('[data-ui-sticky-table-summary-body]');

                const filteredUrl = (form) => {
                    const url = new URL(form.action, window.location.origin);
                    const formData = new FormData(form);

                    for (const [key, value] of formData.entries()) {
                        if (String(value) !== '') {
                            url.searchParams.append(key, value);
                        }
                    }

                    return url;
                };

                const setLoaderState = (loader, state) => {
                    loader?.querySelector('[data-loader-spinner]')?.classList.toggle('hidden', state !== 'loading');
                    loader?.querySelector('[data-loader-error]')?.classList.toggle('hidden', state !== 'error');
                    loader?.closest('tr')?.classList.toggle('hidden', state === 'hidden');
                };

                const replaceCounterparties = (payload) => {
                    const summary = document.getElementById('counterparties-summary');
                    const rows = tableRows();
                    const summaryBody = stickySummaryBody();

                    if (summary) {
                        summary.innerHTML = payload.summary_html || '';
                    }

                    if (rows) {
                        rows.innerHTML = (payload.html || '') + (payload.loader_html || '');
                    }

                    if (summaryBody) {
                        summaryBody.innerHTML = payload.sticky_summary_html || '';
                    }

                    const loader = tableLoader();

                    if (loader) {
                        if (payload.has_more && payload.next_page) {
                            loader.dataset.nextPage = payload.next_page;
                            setLoaderState(loader, 'loading');
                        } else {
                            delete loader.dataset.nextPage;
                            setLoaderState(loader, 'hidden');
                        }
                    }

                    document.dispatchEvent(new Event('ui:sticky-table-refresh'));
                    document.dispatchEvent(new Event('counterparties:loader-refresh'));
                };

                const syncRebuildForm = (filterForm) => {
                    const rebuildForm = document.querySelector('[data-counterparties-rebuild-form]');

                    if (!rebuildForm) {
                        return;
                    }

                    rebuildForm.querySelector('[data-rebuild-legal-id]').value = filterForm.querySelector('[name="legal_id"]')?.value || '';
                    rebuildForm.querySelector('[data-rebuild-contractor-inn]').value = filterForm.querySelector('[name="contractor_inn"]')?.value || '';
                    rebuildForm.querySelector('[data-rebuild-only-negative-diff]').value = filterForm.querySelector('[name="only_negative_diff"]')?.checked ? '1' : '';
                };

                const fetchCounterparties = async (form) => {
                    if (!form || form.dataset.counterpartiesFilterLoading === 'true') {
                        return;
                    }

                    const url = filteredUrl(form);
                    const rows = tableRows();

                    form.dataset.counterpartiesFilterLoading = 'true';
                    rows?.classList.add('opacity-60');

                    try {
                        const response = await fetch(url.toString(), {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            throw new Error('Request failed');
                        }

                        replaceCounterparties(await response.json());
                        syncRebuildForm(form);
                        window.history.replaceState({}, '', url.toString());
                    } catch (error) {
                        form.submit();
                    } finally {
                        form.dataset.counterpartiesFilterLoading = 'false';
                        rows?.classList.remove('opacity-60');
                    }
                };

                const initCounterpartiesFilters = () => {
                    document.querySelectorAll('[data-counterparties-filter-form]:not([data-counterparties-filter-ready])').forEach((form) => {
                        let inputTimer = null;

                        form.dataset.counterpartiesFilterReady = 'true';

                        form.addEventListener('submit', (event) => {
                            event.preventDefault();
                            fetchCounterparties(form);
                        });

                        form.addEventListener('change', (event) => {
                            if (event.target.matches('[data-counterparties-filter-input]')) {
                                return;
                            }

                            fetchCounterparties(form);
                        });

                        form.querySelectorAll('[data-counterparties-filter-input]').forEach((input) => {
                            input.addEventListener('input', () => {
                                window.clearTimeout(inputTimer);
                                inputTimer = window.setTimeout(() => fetchCounterparties(form), 650);
                            });
                        });
                    });
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initCounterpartiesFilters);
                } else {
                    initCounterpartiesFilters();
                }

                document.addEventListener('livewire:navigated', initCounterpartiesFilters);
            })();

            (() => {
                const initCounterpartiesLoader = () => {
                    const rows = document.getElementById('counterparties-rows');
                    const loader = document.getElementById('counterparties-loader');
                    const loaderRow = document.getElementById('counterparties-loader-row');

                    if (!rows || !loader || !loaderRow || loader.dataset.counterpartiesLoaderReady === 'true') {
                        return;
                    }

                    let loading = false;
                    loader.dataset.counterpartiesLoaderReady = 'true';

                    const setLoaderState = (state) => {
                        loader.querySelector('[data-loader-spinner]')?.classList.toggle('hidden', state !== 'loading');
                        loader.querySelector('[data-loader-error]')?.classList.toggle('hidden', state !== 'error');
                        loaderRow.classList.toggle('hidden', state === 'hidden');
                    };

                    const loadNextPage = async () => {
                        if (loading || !loader.dataset.nextPage) {
                            return;
                        }

                        loading = true;
                        setLoaderState('loading');

                        const url = new URL(window.location.href);
                        url.searchParams.set('page', loader.dataset.nextPage);

                        try {
                            const response = await fetch(url.toString(), {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });

                            if (!response.ok) {
                                throw new Error('Request failed');
                            }

                            const payload = await response.json();
                            loaderRow.insertAdjacentHTML('beforebegin', payload.html || '');

                            if (payload.has_more && payload.next_page) {
                                loader.dataset.nextPage = payload.next_page;
                                setLoaderState('loading');
                            } else {
                                delete loader.dataset.nextPage;
                                setLoaderState('hidden');
                            }

                            document.dispatchEvent(new Event('ui:sticky-table-refresh'));
                        } catch (error) {
                            setLoaderState('error');
                        } finally {
                            loading = false;
                        }
                    };

                    const observer = new IntersectionObserver((entries) => {
                        if (entries.some((entry) => entry.isIntersecting)) {
                            loadNextPage();
                        }
                    }, { rootMargin: '600px 0px' });

                    observer.observe(loader);
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initCounterpartiesLoader);
                } else {
                    initCounterpartiesLoader();
                }

                document.addEventListener('livewire:navigated', initCounterpartiesLoader);
                document.addEventListener('counterparties:loader-refresh', initCounterpartiesLoader);
            })();
        </script>
    @endonce
@endsection
