@extends('layouts.app', [
    'title' => 'Касса',
    'titleDescription' => 'Кассовый слой из ручных записей и банковских операций, попавших под правила интерпретации.',
])

@php
    $displayTimezone = config('app.display_timezone', 'Europe/Moscow');
    $money = static fn ($value) => number_format((float) $value, 0, ',', ' ');
    $date = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse((string) $value, 'UTC')->timezone($displayTimezone)->format('d.m.Y H:i') : '—';
    $defaultKassaTime = now($displayTimezone)->format('Y-m-d\TH:i');
    $shouldOpenKassaCreateDialog = session('open_modal') === 'kassa-create-dialog' || (($errors ?? null)?->any() && old('_form') === 'kassa-create');
@endphp

@section('page_actions')
    @if ($canEditManualOperations)
    <div class="flex flex-wrap items-center gap-2">
        <x-ui.button type="button" size="md" variant="ghost" data-ui-modal-open="kassa-create-dialog">
            Добавить запись
        </x-ui.button>

        <form method="post" action="{{ route('kassa.rebuild') }}">
            @csrf
            <x-ui.button type="submit" size="md" variant="ghost">
                Пересчитать слой
            </x-ui.button>
        </form>
    </div>
    @endif
@endsection

@section('content')
    @if ($canEditManualOperations)
    <x-ui.modal
        id="kassa-create-dialog"
        title="Добавить запись кассы"
        description="Ручная денежная операция будет сохранена в legal.kassa и автоматически отражена как документ manual_cash_operation."
        size="xl"
        :open="$shouldOpenKassaCreateDialog"
    >
        <form method="post" action="{{ route('kassa.store') }}">
            @csrf
            <input type="hidden" name="_form" value="kassa-create">

            <div class="space-y-5 px-6 py-5">
                @if (($errors ?? null)?->any() && old('_form') === 'kassa-create')
                    <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select
                        label="Статья"
                        name="article_id"
                        :value="old('article_id')"
                        :options="$articles->pluck('article', 'article_id')->all()"
                        placeholder="Выберите статью"
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="grid gap-1.5 text-sm font-medium text-gray-900 dark:text-white">
                        <span>Дата и время</span>
                        <input
                            type="datetime-local"
                            name="time"
                            value="{{ old('time', $defaultKassaTime) }}"
                            class="h-10 rounded-md bg-white px-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500"
                            required
                        >
                    </label>

                    <x-ui.select
                        label="Тип"
                        name="direction"
                        :value="old('direction', 'expense')"
                        :options="['income' => 'Приход', 'expense' => 'Расход']"
                    />

                    <label class="grid gap-1.5 text-sm font-medium text-gray-900 dark:text-white">
                        <span>Сумма</span>
                        <input
                            type="number"
                            name="amount"
                            value="{{ old('amount') }}"
                            min="1"
                            step="1"
                            class="h-10 rounded-md bg-white px-3 text-right text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500"
                            required
                        >
                    </label>
                </div>

                <label class="grid gap-1.5 text-sm font-medium text-gray-900 dark:text-white">
                    <span>Описание</span>
                    <textarea
                        name="description"
                        rows="4"
                        maxlength="2000"
                        class="rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500"
                        required
                    >{{ old('description') }}</textarea>
                </label>
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 px-6 py-4 dark:border-white/10">
                <x-ui.button type="button" variant="ghost" data-ui-modal-close>
                    Отмена
                </x-ui.button>

                <x-ui.button type="submit" variant="soft">
                    Добавить
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
    @endif

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

    <x-ui.table-filters
        :action="route('kassa.index')"
        rows-id="kassa-rows"
        loader-id="kassa-loader"
        table-selector="[data-kassa-table]"
        columns="grid-cols-1 sm:grid-cols-2 xl:grid-cols-12"
    >
            <div class="xl:col-span-3">
                <x-ui.select
                    label="Описание"
                    name="article_id"
                    :value="$filters['article_id'] ?? ''"
                    :options="$articles->pluck('article', 'article_id')->all()"
                    placeholder="Все описания"
                />
            </div>

            <div class="sm:col-span-2 xl:col-span-9">
                <x-ui.airdatepicker.date-range
                    label="Период"
                    name-from="date_from"
                    name-to="date_to"
                    :value-from="$filters['date_from'] ?? null"
                    :value-to="$filters['date_to'] ?? null"
                />
            </div>

                <label class="grid gap-1.5 text-sm font-medium text-gray-900 sm:col-span-2 xl:col-span-12 dark:text-white">
                    <span>Поиск</span>
                    <input
                        class="h-10 rounded-md bg-white px-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500"
                        name="q"
                        data-ui-table-filter-input
                        value="{{ $filters['q'] ?? '' }}"
                        placeholder="Описание, статья, юрлицо или ИНН"
                    >
                </label>
    </x-ui.table-filters>

    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :sticky-summary-enabled="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
        body-id="kassa-rows"
        data-kassa-table
    >
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th first>Дата</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Источник</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Статья</x-ui.sticky-table-th>
                <x-ui.money-columns-head />
                <x-ui.sticky-table-th>Описание</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Документ</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Итог</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last align="right">ID</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @if ($operations->isNotEmpty())
            @include('kassa.partials.rows', ['operations' => $operations, 'displayTimezone' => $displayTimezone])
        @else
            <tr>
                <td class="py-12 text-center text-sm text-gray-500 dark:text-gray-400" colspan="10">
                    Кассовые операции пока не найдены.
                </td>
            </tr>
        @endif

        @include('kassa.partials.loader-row', [
            'nextPage' => $nextPage,
            'tableColspan' => 10,
        ])

        <x-slot:stickySummary>
            <tr>
                <x-ui.sticky-table-summary-label first :columns="3">
                    Итого операций: {{ number_format((int) $summary->operations_count, 0, ',', ' ') }}
                </x-ui.sticky-table-summary-label>
                <x-ui.money-columns
                    :amount="$summary->saldo_amount"
                    :income="$summary->income_amount"
                    :expense="$summary->expense_amount"
                    summary
                    :decimals="0"
                />
                <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
                <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 px-3 py-3.5 backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75"></td>
                <td @class([
                    'sticky bottom-0 z-10 whitespace-nowrap border-t border-gray-300 bg-white/75 px-3 py-3.5 text-right text-sm font-semibold tabular-nums backdrop-blur-sm backdrop-filter dark:border-white/15 dark:bg-gray-900/75',
                    'text-emerald-700' => (float) $summary->saldo_amount > 0,
                    'text-rose-700' => (float) $summary->saldo_amount < 0,
                    'text-gray-900 dark:text-white' => (float) $summary->saldo_amount === 0.0,
                ])>
                    {{ $money($summary->saldo_amount) }}
                </td>
                <td class="sticky bottom-0 z-10 border-t border-gray-300 bg-white/75 py-3.5 pr-4 pl-3 backdrop-blur-sm backdrop-filter sm:pr-6 lg:pr-8 dark:border-white/15 dark:bg-gray-900/75"></td>
            </tr>
        </x-slot:stickySummary>
    </x-ui.sticky-table>

    <x-ui.context-menu trigger-selector="[data-kassa-context-row]">
        <x-slot:menu>
            <x-ui.context-menu-item data-kassa-show-properties>
                Свойства
            </x-ui.context-menu-item>
        </x-slot:menu>
    </x-ui.context-menu>

    <button type="button" class="hidden" data-ui-modal-open="kassa-properties-dialog" data-kassa-properties-open></button>

    <x-ui.modal
        id="kassa-properties-dialog"
        title="Свойства кассовой операции"
        description="Свойства объекта строки кассового слоя."
        size="2xl"
    >
        <div class="px-6 py-5">
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">ID строки кассы</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-kassa-property="cashEntryId">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Источник</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white" data-kassa-property="sourceLabel">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Код источника</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-kassa-property="sourceType">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Дата операции</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-kassa-property="operationDate">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Создано</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-kassa-property="createdDate">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Статья</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white" data-kassa-property="article">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">ID статьи</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-kassa-property="articleId">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Юрлицо</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white" data-kassa-property="legal">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Legal ID</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-kassa-property="legalId">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Приход</dt>
                    <dd class="mt-1 font-mono text-emerald-700 dark:text-emerald-400" data-kassa-property="income">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Расход</dt>
                    <dd class="mt-1 font-mono text-rose-700 dark:text-rose-400" data-kassa-property="expense">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Сумма со знаком</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-kassa-property="amount">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Итог</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-kassa-property="runningTotal">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Исходная запись</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-kassa-property="sourceRecord">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Rule ID</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-kassa-property="ruleId">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Kassa ID</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-kassa-property="kassaId">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Bank transaction ID</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-kassa-property="bankTransactionId">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Document ID</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-kassa-property="documentId">—</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Документ</dt>
                    <dd class="mt-1 whitespace-normal break-words text-gray-900 dark:text-white" data-kassa-property="document">—</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="font-medium text-gray-500 dark:text-gray-400">External ID документа</dt>
                    <dd class="mt-1 break-all font-mono text-xs text-gray-900 dark:text-white" data-kassa-property="documentExternalId">—</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Описание</dt>
                    <dd class="mt-1 whitespace-pre-wrap text-gray-900 dark:text-white" data-kassa-property="description">—</dd>
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
                const initKassaContextMenu = () => {
                    const menu = document.querySelector('[data-ui-context-menu-trigger-selector="[data-kassa-context-row]"]');

                    if (!menu || menu.dataset.kassaMenuReady === 'true') {
                        return;
                    }

                    menu.dataset.kassaMenuReady = 'true';

                    document.addEventListener('contextmenu', (event) => {
                        const row = event.target.closest('[data-kassa-context-row]');

                        if (!row) {
                            return;
                        }

                        menu.dataset.row = JSON.stringify(row.dataset);
                    });

                    menu.querySelector('[data-kassa-show-properties]')?.addEventListener('click', () => {
                        const data = JSON.parse(menu.dataset.row || '{}');
                        const properties = {
                            cashEntryId: data.kassaCashEntryId || '—',
                            sourceType: data.kassaSourceType || '—',
                            sourceLabel: data.kassaSourceLabel || '—',
                            operationDate: data.kassaOperationDate || '—',
                            createdDate: data.kassaCreatedDate || '—',
                            article: data.kassaArticle || '—',
                            articleId: data.kassaArticleId || '—',
                            legal: data.kassaLegal || '—',
                            legalId: data.kassaLegalId || '—',
                            income: data.kassaIncome || '—',
                            expense: data.kassaExpense || '—',
                            amount: data.kassaAmount || '—',
                            runningTotal: data.kassaRunningTotal || '—',
                            sourceRecord: data.kassaSourceRecord || '—',
                            ruleId: data.kassaRuleId || '—',
                            kassaId: data.kassaKassaId || '—',
                            bankTransactionId: data.kassaBankTransactionId || '—',
                            document: data.kassaDocument || '—',
                            documentId: data.kassaDocumentId || '—',
                            documentExternalId: data.kassaDocumentExternalId || '—',
                            description: data.kassaDescription || '—',
                        };

                        for (const [key, value] of Object.entries(properties)) {
                            const element = document.querySelector(`[data-kassa-property="${key}"]`);

                            if (element) {
                                element.textContent = value || '—';
                            }
                        }

                        document.querySelector('[data-kassa-properties-open]')?.click();
                    });
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initKassaContextMenu);
                } else {
                    initKassaContextMenu();
                }

                document.addEventListener('livewire:navigated', initKassaContextMenu);
            })();
        </script>
    @endonce
@endsection
