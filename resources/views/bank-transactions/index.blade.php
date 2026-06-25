@extends('layouts.app', [
    'title' => 'Банковские транзакции',
    'titleAttribute' => 'Операции по расчетным счетам, загруженные через API банка или из файлов 1CClientBankExchange.',
])

@section('page_actions')
    <div class="flex flex-wrap items-center gap-2">
        <x-ui.button type="button" size="md" variant="ghost" data-ui-modal-open="bank-statement-import-dialog">
            Загрузить выписку
        </x-ui.button>

        <x-ui.button type="button" size="md" variant="ghost" data-ui-modal-open="tinkoff-sync-dialog">
            Обновить Тинек
        </x-ui.button>

        <x-ui.button type="button" size="md" variant="ghost" data-ui-modal-open="bank-transaction-data-map-dialog">
            Карта данных
        </x-ui.button>
    </div>
@endsection

@section('content')
    <x-ui.modal
        id="tinkoff-sync-dialog"
        title="Обновить Тинек"
        description="Синхронизация загрузит из API Тинькофф список счетов и операции за последние 5 дней."
        size="lg"
    >
        <div class="px-6 py-5">
            <div class="rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-600 ring-1 ring-gray-900/5 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                <div class="font-medium text-gray-900 dark:text-white">Что произойдет</div>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>Будут использованы активные API-ключи Тинькофф по банковским счетам.</li>
                    <li>Каждый запрос и ответ API попадет в журнал синхронизации.</li>
                    <li>Новые банковские операции будут добавлены в документы и банковскую детализацию.</li>
                    <li>Существующие операции будут обновлены без дублей.</li>
                </ul>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <x-ui.button type="button" size="md" variant="ghost" data-ui-modal-close>
                    Отмена
                </x-ui.button>

                <form method="post" action="{{ route('bank-transactions.sync') }}">
                    @csrf
                    <input type="hidden" name="days" value="5">
                    <x-ui.button type="submit" size="md" variant="soft">
                        Запустить
                    </x-ui.button>
                </form>
            </div>
        </div>
    </x-ui.modal>

    <x-ui.modal
        id="bank-statement-import-dialog"
        title="Загрузка банковской выписки"
        description="Файлы должны быть формата 1CClientBankExchange. Они будут добавлены в документы и Money layer."
        size="xl"
        :open="session('open_modal') === 'bank-statement-import-dialog'"
    >
        @include('bank-statement-imports._form', [
            'formId' => 'bank-transactions-statement-import',
            'redirectTo' => url()->full(),
            'submitLabel' => 'Загрузить',
        ])
    </x-ui.modal>

    <x-ui.modal
        id="bank-transaction-data-map-dialog"
        title="Карта данных банковской операции"
        description="Откуда берется строка в таблице банковских транзакций и какие слои участвуют."
        size="2xl"
    >
        <div class="space-y-6 px-6 py-5 text-sm text-gray-600 dark:text-gray-300">
            <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-900/5 dark:bg-white/5 dark:ring-white/10">
                <div class="font-medium text-gray-900 dark:text-white">Итоговая UI-таблица</div>
                <p class="mt-1">
                    Страница <span class="font-mono text-xs">/bank-transactions</span> не читает старые
                    <span class="font-mono text-xs">legal_reconciliation</span> и
                    <span class="font-mono text-xs">bank_transaction</span>. Основная строка берется из новой
                    канонической таблицы <span class="font-mono text-xs">legal.document_bank_transaction</span>.
                </p>
            </div>

            <div class="overflow-x-auto pb-2">
                <div class="grid min-w-[980px] grid-cols-[1fr_auto_1fr_auto_1fr_auto_1fr] items-stretch gap-3">
                    <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 dark:border-sky-500/30 dark:bg-sky-500/10">
                        <div class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">Acquisition layer</div>
                        <div class="mt-3 space-y-2">
                            <div class="rounded-md bg-white px-3 py-2 font-mono text-xs text-gray-900 shadow-sm ring-1 ring-sky-200 dark:bg-gray-900 dark:text-white dark:ring-sky-500/30">api_sync_runs</div>
                            <div class="rounded-md bg-white px-3 py-2 font-mono text-xs text-gray-900 shadow-sm ring-1 ring-sky-200 dark:bg-gray-900 dark:text-white dark:ring-sky-500/30">api_sync_requests</div>
                            <div class="rounded-md bg-white px-3 py-2 font-mono text-xs text-gray-900 shadow-sm ring-1 ring-sky-200 dark:bg-gray-900 dark:text-white dark:ring-sky-500/30">bank_statement_imports</div>
                        </div>
                        <p class="mt-3 text-xs text-sky-900 dark:text-sky-100">API Тинькофф или файл 1CClientBankExchange. Здесь хранится факт запуска, запрос, ответ или файл.</p>
                    </div>

                    <div class="flex items-center justify-center text-gray-400">-&gt;</div>

                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                        <div class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Source layer</div>
                        <div class="mt-3 space-y-2">
                            <div class="rounded-md bg-white px-3 py-2 font-mono text-xs text-gray-900 shadow-sm ring-1 ring-amber-200 dark:bg-gray-900 dark:text-white dark:ring-amber-500/30">source_records</div>
                            <div class="rounded-md bg-white px-3 py-2 font-mono text-xs text-gray-900 shadow-sm ring-1 ring-amber-200 dark:bg-gray-900 dark:text-white dark:ring-amber-500/30">source_record_bank_details</div>
                            <div class="rounded-md bg-white px-3 py-2 font-mono text-xs text-gray-900 shadow-sm ring-1 ring-amber-200 dark:bg-gray-900 dark:text-white dark:ring-amber-500/30">source_record_parties</div>
                            <div class="rounded-md bg-white px-3 py-2 font-mono text-xs text-gray-900 shadow-sm ring-1 ring-amber-200 dark:bg-gray-900 dark:text-white dark:ring-amber-500/30">source_record_amounts</div>
                            <div class="rounded-md bg-white px-3 py-2 font-mono text-xs text-gray-900 shadow-sm ring-1 ring-amber-200 dark:bg-gray-900 dark:text-white dark:ring-amber-500/30">source_record_files</div>
                        </div>
                        <p class="mt-3 text-xs text-amber-900 dark:text-amber-100">Нормализованный первоисточник: одна операция банка как запись источника, ее стороны, суммы и файл/ответ.</p>
                    </div>

                    <div class="flex items-center justify-center text-gray-400">-&gt;</div>

                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10">
                        <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Canonical layer</div>
                        <div class="mt-3 space-y-2">
                            <div class="rounded-md bg-white px-3 py-2 font-mono text-xs text-gray-900 shadow-sm ring-1 ring-emerald-200 dark:bg-gray-900 dark:text-white dark:ring-emerald-500/30">documents</div>
                            <div class="rounded-md bg-white px-3 py-2 font-mono text-xs text-gray-900 shadow-sm ring-1 ring-emerald-200 dark:bg-gray-900 dark:text-white dark:ring-emerald-500/30">document_bank_transaction</div>
                            <div class="rounded-md bg-white px-3 py-2 font-mono text-xs text-gray-900 shadow-sm ring-1 ring-emerald-200 dark:bg-gray-900 dark:text-white dark:ring-emerald-500/30">document_sources</div>
                        </div>
                        <p class="mt-3 text-xs text-emerald-900 dark:text-emerald-100">Канонический документ "банковская операция" и его банковская детализация. Это главный источник строки UI.</p>
                    </div>

                    <div class="flex items-center justify-center text-gray-400">-&gt;</div>

                    <div class="rounded-lg border border-violet-200 bg-violet-50 p-4 dark:border-violet-500/30 dark:bg-violet-500/10">
                        <div class="text-xs font-semibold uppercase tracking-wide text-violet-700 dark:text-violet-300">UI query</div>
                        <div class="mt-3 space-y-2">
                            <div class="rounded-md bg-white px-3 py-2 font-mono text-xs text-gray-900 shadow-sm ring-1 ring-violet-200 dark:bg-gray-900 dark:text-white dark:ring-violet-500/30">document_bank_transaction dbt</div>
                            <div class="rounded-md bg-white px-3 py-2 font-mono text-xs text-gray-900 shadow-sm ring-1 ring-violet-200 dark:bg-gray-900 dark:text-white dark:ring-violet-500/30">bank_account ba</div>
                            <div class="rounded-md bg-white px-3 py-2 font-mono text-xs text-gray-900 shadow-sm ring-1 ring-violet-200 dark:bg-gray-900 dark:text-white dark:ring-violet-500/30">legal_own l</div>
                            <div class="rounded-md bg-white px-3 py-2 font-mono text-xs text-gray-900 shadow-sm ring-1 ring-violet-200 dark:bg-gray-900 dark:text-white dark:ring-violet-500/30">bank_operation_types bot</div>
                            <div class="rounded-md bg-white px-3 py-2 font-mono text-xs text-gray-900 shadow-sm ring-1 ring-violet-200 dark:bg-gray-900 dark:text-white dark:ring-violet-500/30">vat_events exists</div>
                        </div>
                        <p class="mt-3 text-xs text-violet-900 dark:text-violet-100">Контроллер собирает контрагента, приход/расход, тип операции, флаг НДС и нарастающий итог.</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                    <div class="font-medium text-gray-900 dark:text-white">Строка таблицы</div>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-xs">
                        <li><span class="font-mono">date</span> = <span class="font-mono">dbt.operation_date</span></li>
                        <li><span class="font-mono">account_number</span> = <span class="font-mono">dbt.account_number</span></li>
                        <li><span class="font-mono">income/expense</span> = знак <span class="font-mono">dbt.signed_amount</span></li>
                        <li><span class="font-mono">total</span> = window sum по дате и <span class="font-mono">order_intraday</span></li>
                    </ul>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                    <div class="font-medium text-gray-900 dark:text-white">Расшифровки</div>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-xs">
                        <li>Юрлицо: <span class="font-mono">bank_account -> legal_own</span></li>
                        <li>Тип операции: <span class="font-mono">bank_operation_types</span></li>
                        <li>Контрагент: payer/recipient выбирается относительно нашего счета</li>
                    </ul>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                    <div class="font-medium text-gray-900 dark:text-white">Интерпретационные слои</div>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-xs">
                        <li><span class="font-mono">vat_events</span> дает бейдж НДС</li>
                        <li><span class="font-mono">money_edges</span> строится из этих же документов для Money layer</li>
                        <li><span class="font-mono">cash_entries</span> строится отдельным правилом для страницы кассы</li>
                    </ul>
                </div>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex justify-end">
                <x-ui.button type="button" size="md" variant="ghost" data-ui-modal-close>
                    Закрыть
                </x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.modal>

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
        <form class="p-4" method="get" action="{{ route('bank-transactions.index') }}" data-auto-filter-form>
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
                    <input class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/15" name="contractor" value="{{ $filters['contractor'] ?? '' }}" data-auto-filter-input>
                </label>

                <x-ui.airdatepicker.date-range
                    label="Период"
                    name-from="date_from"
                    name-to="date_to"
                    :value-from="$filters['date_from'] ?? null"
                    :value-to="$filters['date_to'] ?? null"
                />
            </div>

        </form>
    </div>

    @once
        <script>
            (() => {
                const tableRows = () => document.getElementById('bank-transactions-rows');
                const tableLoader = () => document.getElementById('bank-transactions-loader');
                const stickySummaryBody = () => document.querySelector('[data-ui-sticky-table-summary-body]');
                const tableHead = () => document.querySelector('[data-bank-transactions-table] thead');

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

                const replaceTable = (payload) => {
                    const rows = tableRows();
                    const summaryBody = stickySummaryBody();
                    const head = tableHead();

                    if (head) {
                        head.innerHTML = payload.head_html || '';
                    }

                    if (rows) {
                        rows.innerHTML = (payload.html || '') + (payload.loader_html || '');
                    }

                    if (summaryBody) {
                        summaryBody.innerHTML = payload.sticky_summary_html || '';
                    }

                    const loader = tableLoader();

                    if (!loader) {
                        return;
                    }

                    if (payload.has_more && payload.next_page) {
                        loader.dataset.nextPage = payload.next_page;
                        setLoaderState(loader, 'loading');
                    } else {
                        delete loader.dataset.nextPage;
                        setLoaderState(loader, 'hidden');
                    }

                    document.dispatchEvent(new Event('ui:sticky-table-refresh'));
                };

                const fetchTable = async (form) => {
                    if (!form || form.dataset.autoFilterLoading === 'true') {
                        return;
                    }

                    const url = filteredUrl(form);
                    const rows = tableRows();

                    form.dataset.autoFilterLoading = 'true';
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

                        replaceTable(await response.json());
                        window.history.replaceState({}, '', url.toString());
                    } catch (error) {
                        form.submit();
                    } finally {
                        form.dataset.autoFilterLoading = 'false';
                        rows?.classList.remove('opacity-60');
                    }
                };

                const submitForm = (form) => {
                    if (!form || form.dataset.autoFilterSubmitting === 'true') {
                        return;
                    }

                    form.dataset.autoFilterSubmitting = 'true';
                    fetchTable(form).finally(() => {
                        form.dataset.autoFilterSubmitting = 'false';
                    });
                };

                const initAutoFilterForms = () => {
                    document.querySelectorAll('[data-auto-filter-form]:not([data-auto-filter-ready])').forEach((form) => {
                        let inputTimer = null;
                        let dateRangeTimer = null;

                        form.dataset.autoFilterReady = 'true';

                        form.addEventListener('submit', (event) => {
                            event.preventDefault();
                            submitForm(form);
                        });

                        form.addEventListener('change', (event) => {
                            if (event.target.matches('[data-auto-filter-input]')) {
                                return;
                            }

                            submitForm(form);
                        });

                        form.addEventListener('airdatepicker-range-change', (event) => {
                            window.clearTimeout(dateRangeTimer);

                            if (event.detail?.selectedCount === 1) {
                                dateRangeTimer = window.setTimeout(() => submitForm(form), 1200);
                                return;
                            }

                            submitForm(form);
                        });

                        form.querySelectorAll('[data-auto-filter-input]').forEach((input) => {
                            input.addEventListener('input', () => {
                                window.clearTimeout(inputTimer);
                                inputTimer = window.setTimeout(() => submitForm(form), 650);
                            });
                        });
                    });
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initAutoFilterForms);
                } else {
                    initAutoFilterForms();
                }

                document.addEventListener('livewire:navigated', initAutoFilterForms);
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
        table-class="!min-w-[1500px]"
        body-id="bank-transactions-rows"
        data-bank-transactions-table
    >
        <x-slot:head>
            @include('bank-transactions.partials.head', ['showAccountColumn' => $showAccountColumn])
        </x-slot:head>

        @include('bank-transactions.partials.body', [
            'transactions' => $transactions,
            'showAccountColumn' => $showAccountColumn,
            'tableColspan' => $tableColspan,
        ])

        @include('bank-transactions.partials.loader-row', [
            'nextPage' => $nextPage,
            'tableColspan' => $tableColspan,
        ])

        <x-slot:stickySummary>
            @include('bank-transactions.partials.foot', [
                'summary' => $summary,
                'showAccountColumn' => $showAccountColumn,
            ])
        </x-slot:stickySummary>
    </x-ui.sticky-table>

    <x-ui.context-menu trigger-selector="[data-bank-transaction-context-row]">
        <x-slot:menu>
            <x-ui.context-menu-item data-bank-transaction-show-operation>
                Показать операцию
            </x-ui.context-menu-item>

            <x-ui.context-menu-item>
                Показать первоисточник
            </x-ui.context-menu-item>

            <div class="group/submenu relative">
                <button type="button" role="menuitem" tabindex="-1" class="flex w-full items-center justify-between gap-x-3 rounded-md px-3 py-1.5 text-left text-sm text-gray-700 outline-none hover:bg-gray-50 focus:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/10 dark:focus:bg-white/10">
                    <span>Тестовое подменю</span>
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4 text-gray-400">
                        <path d="M7.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L10.94 10 7.22 6.28a.75.75 0 0 1 0-1.06Z" />
                    </svg>
                </button>

                <div class="absolute top-0 left-full ml-1 hidden min-w-52 rounded-lg border border-gray-200 bg-white p-1 shadow-xl ring-1 ring-black/5 group-hover/submenu:block group-focus-within/submenu:block dark:border-white/10 dark:bg-gray-900 dark:ring-white/10">
                    <x-ui.context-menu-item>
                        Создать связь
                    </x-ui.context-menu-item>
                    <x-ui.context-menu-item>
                        Пометить НДС
                    </x-ui.context-menu-item>
                    <x-ui.context-menu-item danger>
                        Тест удалить
                    </x-ui.context-menu-item>
                </div>
            </div>

            <x-ui.context-menu-item danger>
                Тестовое действие
            </x-ui.context-menu-item>
        </x-slot:menu>
    </x-ui.context-menu>

    <button type="button" class="hidden" data-ui-modal-open="bank-transaction-operation-dialog" data-bank-transaction-operation-open></button>

    <x-ui.modal
        id="bank-transaction-operation-dialog"
        title="Свойства банковской операции"
        description="Данные выбранной строки банковских транзакций."
        size="2xl"
    >
        <div class="px-6 py-5">
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">ID операции</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-bank-transaction-property="id">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Reconciliation ID</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-bank-transaction-property="reconciliationId">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Дата</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-bank-transaction-property="date">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Тип операции</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white" data-bank-transaction-property="typeLabel">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Код типа операции</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-bank-transaction-property="type">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Юрлицо</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white" data-bank-transaction-property="legal">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Legal ID</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-bank-transaction-property="legalId">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Счет</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white" data-bank-transaction-property="account">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Банк</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-bank-transaction-property="bankId">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Контрагент</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white" data-bank-transaction-property="contractor">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">ИНН контрагента</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-bank-transaction-property="contractorInn">—</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Счет контрагента</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-bank-transaction-property="contractorAccount">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Приход</dt>
                    <dd class="mt-1 font-mono text-emerald-700 dark:text-emerald-400" data-bank-transaction-property="income">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Расход</dt>
                    <dd class="mt-1 font-mono text-rose-700 dark:text-rose-400" data-bank-transaction-property="expense">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Сумма со знаком</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-bank-transaction-property="amount">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Итог</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-bank-transaction-property="total">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">НДС</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white" data-bank-transaction-property="vat">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Касса</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-bank-transaction-property="kassa">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Порядок внутри дня</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-bank-transaction-property="orderIntraday">—</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Описание типа операции</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white" data-bank-transaction-property="typeDescription">—</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Назначение платежа</dt>
                    <dd class="mt-1 whitespace-pre-wrap text-gray-900 dark:text-white" data-bank-transaction-property="paymentPurpose">—</dd>
                </div>
            </dl>
        </div>

        <x-slot:footer>
            <div class="flex justify-end">
                <x-ui.button type="button" size="md" variant="ghost" data-ui-modal-close>
                    Закрыть
                </x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.modal>

    @once
        <script>
            (() => {
                const initBankTransactionContextMenu = () => {
                    const menu = document.querySelector('[data-ui-context-menu-trigger-selector="[data-bank-transaction-context-row]"]');

                    if (!menu || menu.dataset.bankTransactionMenuReady === 'true') {
                        return;
                    }

                    menu.dataset.bankTransactionMenuReady = 'true';

                    document.addEventListener('contextmenu', (event) => {
                        const row = event.target.closest('[data-bank-transaction-context-row]');

                        if (!row) {
                            return;
                        }

                        menu.dataset.row = JSON.stringify(row.dataset);
                    });

                    menu.querySelector('[data-bank-transaction-show-operation]')?.addEventListener('click', () => {
                        const data = JSON.parse(menu.dataset.row || '{}');
                        const account = [data.bankTransactionAccountName, data.bankTransactionAccountNumber]
                            .filter(Boolean)
                            .join(' · ');

                        const properties = {
                            id: data.bankTransactionId || '—',
                            reconciliationId: data.bankTransactionReconciliationId || '—',
                            date: data.bankTransactionDate || '—',
                            type: data.bankTransactionType || '—',
                            typeLabel: data.bankTransactionTypeLabel || '—',
                            typeDescription: data.bankTransactionTypeDescription || '—',
                            legal: data.bankTransactionLegal || '—',
                            legalId: data.bankTransactionLegalId || '—',
                            account: account || '—',
                            bankId: data.bankTransactionBankId || '—',
                            contractor: data.bankTransactionContractor || '—',
                            contractorInn: data.bankTransactionContractorInn || '—',
                            contractorAccount: data.bankTransactionContractorAccount || '—',
                            income: data.bankTransactionIncome || '—',
                            expense: data.bankTransactionExpense || '—',
                            amount: data.bankTransactionAmount || '—',
                            total: data.bankTransactionTotal || '—',
                            vat: data.bankTransactionVat || '—',
                            kassa: data.bankTransactionKassa || '—',
                            orderIntraday: data.bankTransactionOrderIntraday || '—',
                            paymentPurpose: data.bankTransactionPaymentPurpose || '—',
                        };

                        for (const [key, value] of Object.entries(properties)) {
                            const element = document.querySelector(`[data-bank-transaction-property="${key}"]`);

                            if (element) {
                                element.textContent = value || '—';
                            }
                        }

                        document.querySelector('[data-bank-transaction-operation-open]')?.click();
                    });
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initBankTransactionContextMenu);
                } else {
                    initBankTransactionContextMenu();
                }

                document.addEventListener('livewire:navigated', initBankTransactionContextMenu);
            })();
        </script>
    @endonce

    <script>
        (() => {
            const rows = document.getElementById('bank-transactions-rows');
            const loader = document.getElementById('bank-transactions-loader');
            const loaderRow = document.getElementById('bank-transactions-loader-row');

            if (!rows || !loader || !loaderRow) {
                return;
            }

            let loading = false;

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
        })();
    </script>
@endsection
