@extends('layouts.app', ['title' => 'Банковские транзакции'])

@section('page_actions')
    <form method="post" action="{{ route('bank-transactions.sync') }}">
        @csrf
        <input type="hidden" name="days" value="5">
        <x-ui.button class="inline-flex items-center gap-2" type="submit" size="lg">
            Обновить Тинек за 5 дней
        </x-ui.button>
    </form>
@endsection

@section('content')
    <div class="mb-6 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <div class="max-w-3xl text-sm text-slate-500">
                Операции по расчетным счетам, загруженные через API банка или из файлов 1CClientBankExchange.
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button type="button" size="lg" data-bank-statement-import-open>
                Загрузить выписку
            </x-ui.button>
        </div>
    </div>

    <dialog class="w-[min(720px,calc(100vw-32px))] rounded-lg border-0 p-0 text-slate-900 shadow-2xl backdrop:bg-slate-900/45" data-bank-statement-import-dialog>
        <div class="bg-white">
            <div class="flex items-start justify-between gap-4 px-5 pt-5">
                <div>
                    <h2 class="!m-0 !text-xl !font-semibold text-slate-950">Загрузка банковской выписки</h2>
                    <div class="mt-1 text-sm text-slate-500">
                        Файл 1CClientBankExchange будет добавлен в новые документы и Money layer.
                    </div>
                </div>
                <x-ui.button
                    class="flex h-9 w-9 items-center justify-center !px-0 !py-0 text-xl leading-none text-slate-500"
                    type="button"
                    size="md"
                    title="Закрыть"
                    data-bank-statement-import-close
                >
                    &times;
                </x-ui.button>
            </div>

            @include('bank-statement-imports._form', [
                'formId' => 'bank-transactions-statement-import',
                'redirectTo' => url()->full(),
                'submitLabel' => 'Загрузить',
            ])
        </div>
    </dialog>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ session('error') }}
        </div>
    @endif

    @if (isset($errors) && $errors->any())
        <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="mb-4 rounded-lg border border-slate-200 bg-white shadow-sm">
        <form class="p-4" method="get" action="{{ route('bank-transactions.index') }}">
            <div class="grid gap-4 lg:grid-cols-4">
                <x-ui.multi-select-with-secondary-text
                    label="Счет"
                    name="account_numbers"
                    :value="$filters['account_numbers'] ?? array_filter([($filters['account_number'] ?? null)])"
                    :options="$accounts->map(fn ($account) => [
                        'value' => $account->account_number,
                        'label' => $account->legalEntity?->legal_name ?? 'Юрлицо #' . $account->legal_id,
                        'secondary' => trim(($account->name ?: 'Счет') . ' · ' . $account->account_number),
                    ])->values()"
                    placeholder="Все счета"
                />

                <x-ui.select-with-secondary-text
                    label="Тип"
                    name="type"
                    :value="$filters['type'] ?? ''"
                    :options="[
                        [
                            'value' => '',
                            'label' => 'Все движения',
                            'secondary' => '',
                        ],
                        [
                            'value' => 'income',
                            'label' => 'Приход',
                            'secondary' => 'Поступления на счет',
                        ],
                        [
                            'value' => 'expense',
                            'label' => 'Расход',
                            'secondary' => 'Списания со счета',
                        ],
                    ]"
                />

                <label class="grid gap-1.5 text-sm font-medium text-slate-700">
                    <span>Контрагент / ИНН</span>
                    <input class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/15" name="contractor" value="{{ $filters['contractor'] ?? '' }}">
                </label>

                <div class="grid gap-1.5 text-sm font-medium text-slate-700">
                    <span>Период</span>
                    <div class="grid grid-cols-2 gap-2">
                        <input class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/15" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                        <input class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/15" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                </div>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <x-ui.button href="{{ route('bank-transactions.index') }}" size="lg" wire:navigate>
                    Сбросить
                </x-ui.button>
                <x-ui.button type="submit" size="lg">
                    Показать
                </x-ui.button>
            </div>
        </form>
    </div>


    <x-ui.sticky-table :contained="false" body-id="bank-transactions-rows">
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th first>Дата</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Юрлицо / счет</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Контрагент</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Приход</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Расход</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Назначение</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last align="right">Итог</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @if (count($transactions) > 0)
            @include('bank-transactions.partials.rows', ['transactions' => $transactions])
        @else
            <tr>
                <td class="py-8 text-center text-sm text-gray-500 dark:text-gray-400" colspan="7">Банковские транзакции пока не загружены.</td>
            </tr>
        @endif

        <x-slot:foot>
            <tr>
                <th scope="row" colspan="3" class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 backdrop-blur-sm backdrop-filter sm:pl-6 lg:pl-8 dark:border-white/15 dark:bg-gray-900/75 dark:text-white">Итого операций: {{ $summary['count'] }}</th>
                <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 text-right text-sm font-semibold tabular-nums text-emerald-700 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75">
                    {{ number_format($summary['income'], 2, ',', ' ') }}
                </td>
                <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 text-right text-sm font-semibold tabular-nums text-rose-700 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75">
                    {{ number_format($summary['expense'], 2, ',', ' ') }}
                </td>
                <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
                <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 py-3.5 pr-4 pl-3 backdrop-blur-sm backdrop-filter sm:pr-6 lg:pr-8 dark:border-white/15 dark:bg-gray-900/75"></td>
            </tr>
        </x-slot:foot>
    </x-ui.sticky-table>

    <div
        id="bank-transactions-loader"
        class="text-center text-sm text-slate-500"
        data-next-page="{{ $nextPage }}"
    >
        @if ($nextPage)
            Загрузка при прокрутке...
        @endif
    </div>

    <script>
        (() => {
            const dialog = document.querySelector('[data-bank-statement-import-dialog]');
            const openButton = document.querySelector('[data-bank-statement-import-open]');
            const closeButtons = dialog?.querySelectorAll('[data-bank-statement-import-close]') ?? [];

            if (!dialog || !openButton) {
                return;
            }

            openButton.addEventListener('click', () => {
                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                    return;
                }

                dialog.setAttribute('open', 'open');
            });

            closeButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    if (typeof dialog.close === 'function') {
                        dialog.close();
                        return;
                    }

                    dialog.removeAttribute('open');
                });
            });

            dialog.addEventListener('click', (event) => {
                if (event.target === dialog && typeof dialog.close === 'function') {
                    dialog.close();
                }
            });
        })();

        (() => {
            const rows = document.getElementById('bank-transactions-rows');
            const loader = document.getElementById('bank-transactions-loader');

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
