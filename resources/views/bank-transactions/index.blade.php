@extends('layouts.app', ['title' => 'Банковские транзакции'])

@section('content')
    <div class="mb-6 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <h1 class="!mb-2 !text-2xl !font-semibold !tracking-normal text-slate-950">Банковские транзакции</h1>
            <div class="max-w-3xl text-sm text-slate-500">
                Операции по расчетным счетам, загруженные через API банка или из файлов 1CClientBankExchange.
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button
                class="inline-flex h-9 items-center rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                type="button"
                data-bank-statement-import-open
            >
                Загрузить выписку
            </button>

            <div class="inline-flex items-stretch">
                <form method="post" action="{{ route('bank-transactions.sync') }}">
                    @csrf
                    <input type="hidden" name="full" value="1">
                    <button
                        class="inline-flex h-9 items-center gap-2 rounded-l-md border border-emerald-300 bg-emerald-50 px-3 text-sm font-medium text-emerald-700 shadow-sm hover:bg-emerald-100"
                        type="submit"
                    >
                        <span class="h-2 w-2 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></span>
                        Все API-счета
                    </button>
                </form>

                <details class="group relative">
                    <summary
                        class="flex h-9 w-10 cursor-pointer list-none items-center justify-center rounded-r-md border border-l-0 border-emerald-300 bg-emerald-50 text-emerald-700 shadow-sm hover:bg-emerald-100 [&::-webkit-details-marker]:hidden"
                        title="Выбрать счет"
                    >
                        <span class="h-0 w-0 border-x-4 border-t-[5px] border-x-transparent border-t-current"></span>
                    </summary>
                    <div class="absolute right-0 z-30 mt-2 w-[min(420px,calc(100vw-40px))] overflow-hidden rounded-lg border border-slate-200 bg-white p-1 shadow-xl">
                        @if ($apiAccounts->isNotEmpty())
                            @foreach ($apiAccounts as $account)
                                <form method="post" action="{{ route('bank-transactions.sync') }}">
                                    @csrf
                                    <input type="hidden" name="full" value="1">
                                    <input type="hidden" name="account_number" value="{{ $account->account_number }}">
                                    <button
                                        class="flex w-full items-center gap-3 rounded-md bg-white px-3 py-2 text-left text-sm text-slate-800 hover:bg-emerald-50"
                                        type="submit"
                                        title="Обновить счет {{ $account->account_number }}"
                                    >
                                        <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></span>
                                        <span class="grid min-w-0 gap-0.5">
                                            <span class="truncate font-medium">{{ $account->name ?: $account->account_number }}</span>
                                            <small class="truncate text-xs text-slate-500">
                                                {{ $account->legalEntity?->legal_name ?? 'Юрлицо #' . $account->legal_id }} · {{ $account->account_number }}
                                            </small>
                                        </span>
                                    </button>
                                </form>
                            @endforeach
                        @else
                            <div class="px-3 py-2 text-sm text-slate-500">API-счета Тинькофф пока не найдены.</div>
                        @endif
                    </div>
                </details>
            </div>
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
                <button
                    class="flex h-9 w-9 items-center justify-center rounded-md border border-slate-200 bg-white text-xl leading-none text-slate-500 hover:bg-slate-50 hover:text-slate-900"
                    type="button"
                    title="Закрыть"
                    data-bank-statement-import-close
                >
                    &times;
                </button>
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
                <label class="grid gap-1.5 text-sm font-medium text-slate-700">
                    <span>Счет</span>
                    <select class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/15" name="account_number">
                        <option value="">Все счета</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->account_number }}" @selected(($filters['account_number'] ?? '') === $account->account_number)>
                                {{ $account->legalEntity?->legal_name ?? 'Юрлицо #' . $account->legal_id }} · {{ $account->name }} · {{ $account->account_number }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="grid gap-1.5 text-sm font-medium text-slate-700">
                    <span>Тип</span>
                    <select class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/15" name="type">
                        <option value="">Все движения</option>
                        <option value="income" @selected(($filters['type'] ?? '') === 'income')>Приход</option>
                        <option value="expense" @selected(($filters['type'] ?? '') === 'expense')>Расход</option>
                    </select>
                </label>

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
                <a class="inline-flex h-9 items-center rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50" href="{{ route('bank-transactions.index') }}">
                    Сбросить
                </a>
                <button class="inline-flex h-9 items-center rounded-md bg-cyan-800 px-4 text-sm font-medium text-white shadow-sm hover:bg-cyan-900" type="submit">
                    Показать
                </button>
            </div>
        </form>
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-xs font-medium uppercase text-slate-500">Операций</div>
            <div class="mt-1 text-xl font-semibold tabular-nums text-slate-950">{{ $summary['count'] }}</div>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-sm">
            <div class="text-xs font-medium uppercase text-emerald-700">Приход</div>
            <div class="mt-1 text-xl font-semibold tabular-nums text-emerald-900">{{ number_format($summary['income'], 2, ',', ' ') }}</div>
        </div>
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 shadow-sm">
            <div class="text-xs font-medium uppercase text-rose-700">Расход</div>
            <div class="mt-1 text-xl font-semibold tabular-nums text-rose-900">{{ number_format($summary['expense'], 2, ',', ' ') }}</div>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full border-separate border-spacing-0 text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50">
                <tr>
                    <th class="border-b border-slate-200 px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Дата</th>
                    <th class="border-b border-slate-200 px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Юрлицо / счет</th>
                    <th class="border-b border-slate-200 px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Контрагент</th>
                    <th class="border-b border-slate-200 px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Приход</th>
                    <th class="border-b border-slate-200 px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Расход</th>
                    <th class="border-b border-slate-200 px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Назначение</th>
                    <th class="border-b border-slate-200 px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Итог</th>
                </tr>
                </thead>
                <tbody id="bank-transactions-rows" class="divide-y divide-slate-100 bg-white">
                @if (count($transactions) > 0)
                    @include('bank-transactions.partials.rows', ['transactions' => $transactions])
                @else
                    <tr>
                        <td class="px-4 py-8 text-center text-sm text-slate-500" colspan="7">Банковские транзакции пока не загружены.</td>
                    </tr>
                @endif
                </tbody>
            </table>
        </div>
    </div>

    <div
        id="bank-transactions-loader"
        class="py-4 text-center text-sm text-slate-500"
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
