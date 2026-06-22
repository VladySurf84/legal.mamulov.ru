@extends('layouts.app', [
    'title' => 'Детализация контрагента',
    'titleAttribute' => trim(($contractorName ?: 'Контрагент') . ' · ИНН ' . $contractorInn),
])

@section('page_actions')
    <x-ui.button
        href="{{ route('counterparties.index', ['legal_id' => $filters['legal_id'] ?? null, 'contractor_inn' => $contractorInn]) }}"
        size="lg"
        wire:navigate
    >
        К списку
    </x-ui.button>
@endsection

@section('content')
    @php
        $showLegalEntityColumn = empty($filters['legal_id']);
        $emptyColspan = $showLegalEntityColumn ? 9 : 8;
    @endphp

    <div class="mb-6">
        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">ИНН {{ $contractorInn }}</div>
        <h2 class="mt-1 max-w-5xl text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
            {{ $contractorName ?: 'Контрагент без названия' }}
        </h2>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="mb-4 rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <form class="p-4" method="get" action="{{ route('counterparties.show', ['contractorInn' => $contractorInn]) }}">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
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

                <div class="flex gap-2">
                    <x-ui.button
                        href="{{ route('counterparties.show', ['contractorInn' => $contractorInn]) }}"
                        size="lg"
                        wire:navigate
                    >
                        Сбросить
                    </x-ui.button>
                    <x-ui.button type="submit" size="lg" variant="soft">
                        Показать
                    </x-ui.button>
                </div>
            </div>
        </form>
    </div>

    <div class="mb-4 rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <form class="p-4" method="post" action="{{ route('counterparties.opening-balances.store', ['contractorInn' => $contractorInn]) }}">
            @csrf
            <div class="grid gap-4 lg:grid-cols-4">
                <x-ui.select-with-secondary-text
                    label="Наше юрлицо"
                    name="legal_id"
                    :value="old('legal_id', $filters['legal_id'] ?? '')"
                    :options="$legalEntities->map(fn ($legalEntity) => [
                        'value' => $legalEntity->legal_id,
                        'label' => $legalEntity->legal_name,
                        'secondary' => $legalEntity->legal_inn ? 'ИНН ' . $legalEntity->legal_inn : '',
                    ])->prepend([
                        'value' => '',
                        'label' => 'Выбери юрлицо',
                        'secondary' => '',
                    ])->values()"
                    required
                />

                <label class="block">
                    <span class="block text-sm/6 font-medium text-gray-900 dark:text-white">Дата старта</span>
                    <input
                        class="mt-2 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500"
                        name="starts_on"
                        type="date"
                        value="{{ old('starts_on', '2025-01-01') }}"
                        required
                    >
                </label>

                <label class="block">
                    <span class="block text-sm/6 font-medium text-gray-900 dark:text-white">Входящее сальдо</span>
                    <input
                        class="mt-2 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500"
                        name="amount"
                        type="number"
                        step="0.01"
                        value="{{ old('amount') }}"
                        required
                    >
                </label>

                <label class="block">
                    <span class="block text-sm/6 font-medium text-gray-900 dark:text-white">Источник</span>
                    <input
                        class="mt-2 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500"
                        name="source"
                        value="{{ old('source', 'Акт сверки') }}"
                    >
                </label>

                <label class="block lg:col-span-3">
                    <span class="block text-sm/6 font-medium text-gray-900 dark:text-white">Комментарий</span>
                    <textarea
                        class="mt-2 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500"
                        name="comment"
                        rows="2"
                    >{{ old('comment') }}</textarea>
                </label>

                <div class="flex items-end">
                    <x-ui.button type="submit" size="lg" variant="soft">
                        Сохранить входящее
                    </x-ui.button>
                </div>
            </div>
        </form>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-200">Операций банка: {{ number_format($summary['count'], 0, ',', ' ') }}</span>
        <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-200">Входящее: {{ number_format($summary['opening_amount'], 2, ',', ' ') }}</span>
        <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700 ring-1 ring-emerald-200">Наше сальдо: {{ number_format($summary['saldo'], 2, ',', ' ') }}</span>
        <span class="inline-flex rounded-full bg-cyan-50 px-3 py-1 text-sm font-medium text-cyan-700 ring-1 ring-cyan-200">Книги покупок: {{ number_format($summary['buh_saldo'], 2, ',', ' ') }}</span>
        <span class="inline-flex rounded-full bg-indigo-50 px-3 py-1 text-sm font-medium text-indigo-700 ring-1 ring-indigo-200">Разница: {{ number_format($summary['saldo_diff'], 2, ',', ' ') }}</span>
        <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-200">Приход: {{ number_format($summary['income_amount'], 2, ',', ' ') }}</span>
        <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-200">Расход: {{ number_format($summary['expense_amount'], 2, ',', ' ') }}</span>
    </div>

    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
        table-class="!min-w-[1400px]"
        body-id="counterparty-ledger-rows"
    >
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th first>Дата</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Источник</x-ui.sticky-table-th>
                @if ($showLegalEntityColumn)
                    <x-ui.sticky-table-th>Наше юрлицо</x-ui.sticky-table-th>
                @endif
                <x-ui.sticky-table-th>Документ</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Сумма</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Итог</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">НДС</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">НДС итог</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last>Описание</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @if (count($ledgerEntries) > 0)
            @include('counterparties.partials.ledger-rows', [
                'contractorInn' => $contractorInn,
                'filters' => $filters,
                'ledgerEntries' => $ledgerEntries,
                'ledgerPagination' => $ledgerPagination,
            ])
        @else
            <tr>
                <td class="py-8 text-center text-sm text-gray-500 dark:text-gray-400" colspan="{{ $emptyColspan }}">
                    По этому контрагенту нет строк для сверки.
                </td>
            </tr>
        @endif
    </x-ui.sticky-table>

    <div
        id="counterparty-ledger-loader"
        class="py-4 text-center text-sm text-slate-500"
        data-next-page="{{ $nextPage }}"
    >
        @if ($nextPage)
            Загрузка при прокрутке...
        @endif
    </div>

    <script>
        (() => {
            const rows = document.getElementById('counterparty-ledger-rows');
            const loader = document.getElementById('counterparty-ledger-loader');

            if (!rows || !loader || !loader.dataset.nextPage) {
                return;
            }

            let loading = false;

            const loadNextPage = async () => {
                if (loading || !loader.dataset.nextPage) {
                    return;
                }

                loading = true;
                loader.textContent = 'Загружаем...';

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
                    rows.insertAdjacentHTML('beforeend', payload.html);

                    if (payload.has_more && payload.next_page) {
                        loader.dataset.nextPage = payload.next_page;
                        loader.textContent = 'Загрузка при прокрутке...';
                    } else {
                        delete loader.dataset.nextPage;
                        loader.textContent = '';
                        observer.disconnect();
                    }

                    document.dispatchEvent(new Event('ui:sticky-table-refresh'));
                } catch (error) {
                    loader.textContent = 'Не удалось загрузить следующую страницу.';
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
        })();
    </script>
@endsection
