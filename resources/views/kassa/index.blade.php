@extends('layouts.app', [
    'title' => 'Касса',
    'titleDescription' => 'Кассовый слой из ручных записей и банковских операций, попавших под правила интерпретации.',
])

@php
    $displayTimezone = config('app.display_timezone', 'Europe/Moscow');
    $money = static fn ($value) => number_format((float) $value, 0, ',', ' ');
    $date = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse((string) $value, 'UTC')->timezone($displayTimezone)->format('d.m.Y H:i') : '—';
    $shouldOpenKassaCreateDialog = session('open_modal') === 'kassa-create-dialog' || (($errors ?? null)?->any() && old('_form') === 'kassa-create');
    $articleFilterOptions = $articles
        ->map(fn ($article) => [
            'value' => (string) $article->article_id,
            'label' => $article->article,
        ])
        ->values();
@endphp

@section('page_actions')
    @if ($canCreateCashEntry || $canRebuildCashLayer)
    <div class="flex flex-wrap items-center gap-2">
        @if ($canCreateCashEntry)
        <x-ui.button type="button" size="md" variant="ghost" data-ui-modal-open="kassa-create-dialog" data-kassa-create-open>
            Добавить запись
        </x-ui.button>
        @endif

        @if ($canRebuildCashLayer)
        <form method="post" action="{{ route('kassa.rebuild') }}">
            @csrf
            <x-ui.button type="submit" size="md" variant="ghost">
                Пересчитать слой
            </x-ui.button>
        </form>
        @endif
    </div>
    @endif
@endsection

@section('content')
    @if ($canCreateCashEntry || $canEditAnyCashEntry)
    <button type="button" class="hidden" data-ui-modal-open="kassa-create-dialog" data-kassa-edit-open></button>
    <button type="button" class="hidden" data-ui-modal-close="kassa-create-dialog" data-kassa-form-close></button>

    <x-ui.modal
        id="kassa-create-dialog"
        title="Добавить запись кассы"
        description="Ручная денежная операция будет сохранена в legal.kassa и автоматически отражена как документ manual_cash_operation."
        size="xl"
        :open="$shouldOpenKassaCreateDialog"
        data-kassa-form-dialog
    >
        <form method="post" action="{{ route('kassa.store') }}" data-kassa-create-form data-kassa-store-url="{{ route('kassa.store') }}">
            @csrf
            <input type="hidden" name="_method" value="PUT" data-kassa-method-field disabled>
            <input type="hidden" name="_form" value="kassa-create">

            <div class="space-y-5 px-6 py-5">
                <div class="hidden whitespace-pre-wrap rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" data-kassa-form-error></div>

                @if (($errors ?? null)?->any() && old('_form') === 'kassa-create')
                    <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="grid gap-4 lg:grid-cols-[max-content_minmax(0,1fr)]">
                    @php($direction = old('direction', 'expense'))
                    <div>
                        <input type="hidden" name="direction" value="{{ $direction }}" data-kassa-direction-input>
                        <div class="flex">
                            <button
                                type="button"
                                data-kassa-direction-toggle
                                data-kassa-create-field
                                @class([
                                    'flex w-[76px] shrink-0 items-center justify-center rounded-l-md px-3 text-base font-medium outline-1 -outline-offset-1 transition-colors focus:outline-1 focus:-outline-offset-1 sm:text-sm/6',
                                    'bg-emerald-50 text-emerald-700 outline-emerald-200 hover:bg-emerald-100' => $direction === 'income',
                                    'bg-rose-50 text-rose-700 outline-rose-200 hover:bg-rose-100' => $direction !== 'income',
                                ])
                            >
                                {{ $direction === 'income' ? 'Приход' : 'Расход' }}
                            </button>
                            <input
                                type="text"
                                name="amount"
                                value="{{ old('amount') }}"
                                placeholder="Сумма"
                                inputmode="numeric"
                                pattern="[0-9 ]*"
                                class="-ml-px block w-[120px] rounded-r-md bg-white px-3 py-1.5 text-right text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-1 focus:-outline-offset-1 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-gray-700 dark:placeholder:text-gray-500 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                data-kassa-amount-input
                                data-kassa-create-field
                                required
                            >
                        </div>
                    </div>

                    <x-ui.select
                        name="article_id"
                        :value="old('article_id')"
                        :options="$articles->pluck('article', 'article_id')->all()"
                        placeholder="Выберите статью"
                        data-kassa-create-field
                    />
                </div>

                <label class="grid gap-1.5">
                    <textarea
                        name="description"
                        rows="4"
                        maxlength="2000"
                        placeholder="Описание"
                        class="rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500"
                        data-kassa-create-field
                        required
                    >{{ old('description') }}</textarea>
                </label>
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 px-6 py-4 dark:border-white/10">
                <x-ui.button type="button" variant="ghost" data-ui-modal-close data-kassa-cancel-button>
                    Отмена
                </x-ui.button>

                <x-ui.button type="submit" variant="soft" data-kassa-submit-button>
                    <span data-kassa-submit-idle data-kassa-submit-label>Добавить</span>
                    <span class="hidden items-center gap-2" data-kassa-submit-loading>
                        <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                        </svg>
                        <span data-kassa-submit-loading-label>Добавляем</span>
                    </span>
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
        columns="grid-cols-1"
    >
        <div class="min-w-0 max-w-full">
            <div class="grid max-w-full grid-cols-1 gap-4 md:grid-cols-[minmax(0,240px)_minmax(0,240px)] xl:grid-cols-[minmax(0,240px)_minmax(0,240px)_minmax(280px,1fr)]">
                <div>
                    <x-ui.multi-select-with-secondary-text
                        name="article_id"
                        :value="$filters['article_id'] ?? []"
                        :options="$articleFilterOptions"
                        placeholder="Все статьи"
                    />
                </div>

                <div>
                    <x-ui.airdatepicker.date-range
                        name-from="date_from"
                        name-to="date_to"
                        :value-from="$filters['date_from'] ?? null"
                        :value-to="$filters['date_to'] ?? null"
                    />
                </div>

                <div class="md:col-span-2 xl:col-span-1">
                    <x-ui.input
                        name="q"
                        :value="$filters['q'] ?? ''"
                        placeholder="Описание, статья, юрлицо или ИНН"
                        data-ui-table-filter-input
                    />
                </div>
            </div>
        </div>
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
                <x-ui.money-columns-head />
                <x-ui.sticky-table-th>Статья</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Описание</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Итог</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last align="right">ID</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @if ($operations->isNotEmpty())
            @include('kassa.partials.rows', [
                'operations' => $operations,
                'displayTimezone' => $displayTimezone,
                'canCreateCashEntry' => $canCreateCashEntry,
                'canEditAnyCashEntry' => $canEditAnyCashEntry,
                'canDeleteFreshCashEntry' => $canDeleteFreshCashEntry,
                'canDeleteAnyCashEntry' => $canDeleteAnyCashEntry,
                'freshEntryDays' => $freshEntryDays,
            ])
        @else
            <tr>
                <td class="py-12 text-center text-sm text-gray-500 dark:text-gray-400" colspan="8">
                    Кассовые операции пока не найдены.
                </td>
            </tr>
        @endif

        @include('kassa.partials.loader-row', [
            'nextPage' => $nextPage,
            'tableColspan' => 8,
        ])

        <x-slot:stickySummary>
            <tr>
                <x-ui.sticky-table-summary-label first :columns="1">
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
            @if ($canDeleteCashEntry)
            <x-ui.context-menu-item danger data-kassa-delete-row>
                Удалить
            </x-ui.context-menu-item>
            @endif
        </x-slot:menu>
    </x-ui.context-menu>

    <button type="button" class="hidden" data-ui-alert-dialog-open="kassa-delete-dialog" data-kassa-delete-open></button>

    <x-ui.alert-dialog
        id="kassa-delete-dialog"
        title="Удалить запись кассы"
        description="Запись будет удалена из кассы, а кассовый слой будет пересчитан. Это действие нельзя отменить."
        confirm-label="Удалить"
        cancel-label="Отмена"
        data-kassa-delete-dialog
    />

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
                const digitsOnly = (value) => String(value || '').replace(/\D+/g, '');
                const formatInteger = (value) => digitsOnly(value).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

                const renderKassaDirection = (button, input) => {
                    const isIncome = input.value === 'income';
                    button.textContent = isIncome ? 'Приход' : 'Расход';
                    button.classList.toggle('bg-emerald-50', isIncome);
                    button.classList.toggle('text-emerald-700', isIncome);
                    button.classList.toggle('outline-emerald-200', isIncome);
                    button.classList.toggle('hover:bg-emerald-100', isIncome);
                    button.classList.toggle('bg-rose-50', ! isIncome);
                    button.classList.toggle('text-rose-700', ! isIncome);
                    button.classList.toggle('outline-rose-200', ! isIncome);
                    button.classList.toggle('hover:bg-rose-100', ! isIncome);
                };

                const initKassaAmountInputs = () => {
                    document.querySelectorAll('[data-kassa-amount-input]').forEach((input) => {
                        if (input.dataset.kassaAmountReady === 'true') {
                            return;
                        }

                        input.dataset.kassaAmountReady = 'true';
                        input.value = formatInteger(input.value);

                        input.addEventListener('input', () => {
                            input.value = formatInteger(input.value);
                        });

                    });
                };

                const initKassaDirectionToggles = () => {
                    document.querySelectorAll('[data-kassa-direction-toggle]').forEach((button) => {
                        if (button.dataset.kassaDirectionReady === 'true') {
                            return;
                        }

                        const input = button.closest('form')?.querySelector('[data-kassa-direction-input]');

                        if (! input) {
                            return;
                        }

                        button.dataset.kassaDirectionReady = 'true';
                        renderKassaDirection(button, input);

                        button.addEventListener('click', () => {
                            input.value = input.value === 'income' ? 'expense' : 'income';
                            renderKassaDirection(button, input);
                        });
                    });
                };

                const setKassaCreateFormMode = (form, mode, row = null) => {
                    const dialog = form.closest('[data-kassa-form-dialog]');
                    const directionInput = form.querySelector('[data-kassa-direction-input]');
                    const directionButton = form.querySelector('[data-kassa-direction-toggle]');
                    const methodField = form.querySelector('[data-kassa-method-field]');
                    const amountInput = form.querySelector('[name="amount"]');
                    const articleSelect = form.querySelector('[name="article_id"]');
                    const descriptionInput = form.querySelector('[name="description"]');
                    const submitLabel = form.querySelector('[data-kassa-submit-label]');
                    const loadingLabel = form.querySelector('[data-kassa-submit-loading-label]');

                    form.dataset.kassaSubmitting = 'false';
                    form.querySelectorAll('[data-kassa-create-field]').forEach((field) => {
                        field.removeAttribute('aria-disabled');
                        field.classList.remove('pointer-events-none', 'opacity-60');

                        if (field.matches('input, textarea')) {
                            field.readOnly = false;
                        }

                        if (field.matches('button')) {
                            field.disabled = false;
                        }
                    });

                    form.querySelector('[data-kassa-cancel-button]')?.removeAttribute('disabled');
                    form.querySelector('[data-kassa-submit-button]')?.removeAttribute('disabled');
                    form.querySelector('[data-kassa-submit-idle]')?.classList.remove('hidden');
                    form.querySelector('[data-kassa-submit-loading]')?.classList.add('hidden');
                    form.querySelector('[data-kassa-submit-loading]')?.classList.remove('inline-flex');

                    if (mode === 'edit' && row) {
                        form.action = row.dataset.kassaEditUrl;
                        methodField.disabled = false;
                        directionInput.value = row.dataset.kassaEditDirection || 'expense';
                        amountInput.value = formatInteger(row.dataset.kassaEditAmount || '');
                        articleSelect.value = row.dataset.kassaEditArticleId || '';
                        descriptionInput.value = row.dataset.kassaEditDescription || '';
                        dialog?.querySelector('h2')?.replaceChildren('Редактировать запись кассы');
                        submitLabel.textContent = 'Сохранить';
                        loadingLabel.textContent = 'Сохраняем';
                    } else {
                        form.action = form.dataset.kassaStoreUrl;
                        methodField.disabled = true;
                        directionInput.value = 'expense';
                        amountInput.value = '';
                        articleSelect.value = '';
                        descriptionInput.value = '';
                        dialog?.querySelector('h2')?.replaceChildren('Добавить запись кассы');
                        submitLabel.textContent = 'Добавить';
                        loadingLabel.textContent = 'Добавляем';
                    }

                    if (directionButton && directionInput) {
                        renderKassaDirection(directionButton, directionInput);
                    }
                };

                const refreshKassaTable = () => {
                    const filters = document.querySelector('[data-ui-table-filters]');

                    if (! filters) {
                        return;
                    }

                    filters.dispatchEvent(new Event('submit', {
                        bubbles: true,
                        cancelable: true,
                    }));
                };

                const setKassaFormSubmitting = (form, submitting) => {
                    form.dataset.kassaSubmitting = submitting ? 'true' : 'false';

                    form.querySelectorAll('[data-kassa-create-field]').forEach((field) => {
                        field.toggleAttribute('aria-disabled', submitting);
                        field.classList.toggle('pointer-events-none', submitting);
                        field.classList.toggle('opacity-60', submitting);

                        if (field.matches('input, textarea')) {
                            field.readOnly = submitting;
                        }

                        if (field.matches('button')) {
                            field.disabled = submitting;
                        }
                    });

                    form.querySelector('[data-kassa-cancel-button]')?.toggleAttribute('disabled', submitting);

                    const submitButton = form.querySelector('[data-kassa-submit-button]');
                    submitButton?.toggleAttribute('disabled', submitting);
                    submitButton?.querySelector('[data-kassa-submit-idle]')?.classList.toggle('hidden', submitting);

                    const loading = submitButton?.querySelector('[data-kassa-submit-loading]');
                    loading?.classList.toggle('hidden', ! submitting);
                    loading?.classList.toggle('inline-flex', submitting);
                };

                const showKassaFormError = (form, message = '') => {
                    const error = form.querySelector('[data-kassa-form-error]');

                    if (! error) {
                        return;
                    }

                    error.textContent = message;
                    error.classList.toggle('hidden', message === '');
                };

                const errorMessageFromPayload = (payload, fallback = 'Не удалось сохранить запись.') => {
                    const errors = payload?.errors ? Object.values(payload.errors).flat() : [];

                    return errors[0] || payload?.message || fallback;
                };

                const responseLog = (response, payload, text) => [
                    `HTTP ${response.status} ${response.statusText || ''}`.trim(),
                    `URL: ${response.url}`,
                    payload ? `JSON: ${JSON.stringify(payload, null, 2)}` : `TEXT: ${text || ''}`,
                ].join('\n');

                const parseResponse = async (response) => {
                    const text = await response.text();
                    let payload = null;

                    try {
                        payload = text ? JSON.parse(text) : null;
                    } catch (error) {
                        payload = null;
                    }

                    return { payload, text };
                };

                const initKassaCreateForms = () => {
                    document.querySelectorAll('[data-kassa-create-form]').forEach((form) => {
                        if (form.dataset.kassaCreateReady === 'true') {
                            return;
                        }

                        form.dataset.kassaCreateReady = 'true';

                        document.querySelectorAll('[data-kassa-create-open]').forEach((button) => {
                            button.addEventListener('click', () => setKassaCreateFormMode(form, 'create'), true);
                        });

                        document.addEventListener('dblclick', (event) => {
                            const row = event.target.closest('[data-kassa-edit-url]');

                            if (! row) {
                                return;
                            }

                            setKassaCreateFormMode(form, 'edit', row);
                            document.querySelector('[data-kassa-edit-open]')?.click();
                        });

                        form.addEventListener('submit', async (event) => {
                            event.preventDefault();

                            if (form.dataset.kassaSubmitting === 'true') {
                                return;
                            }

                            showKassaFormError(form);
                            const formData = new FormData(form);
                            formData.set('amount', digitsOnly(formData.get('amount')));
                            setKassaFormSubmitting(form, true);

                            try {
                                const response = await fetch(form.action, {
                                    method: 'POST',
                                    body: formData,
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                });
                                const { payload, text } = await parseResponse(response);

                                if (! response.ok) {
                                    throw new Error(responseLog(response, payload, text));
                                }

                                document.querySelector('[data-kassa-form-close]')?.click();
                                setKassaCreateFormMode(form, 'create');
                                refreshKassaTable();
                            } catch (error) {
                                console.error('Kassa form submit failed', error);
                                showKassaFormError(form, error.message || 'Не удалось сохранить запись.');
                                setKassaFormSubmitting(form, false);
                                form.querySelector('[data-kassa-amount-input]')?.dispatchEvent(new Event('input'));
                            }
                        });
                    });
                };

                const initKassaContextMenu = () => {
                    const menu = document.querySelector('[data-ui-context-menu-trigger-selector="[data-kassa-context-row]"]');

                    if (!menu || menu.dataset.kassaMenuReady === 'true') {
                        return;
                    }

                    menu.dataset.kassaMenuReady = 'true';

                    document.addEventListener('contextmenu', (event) => {
                        const row = event.target.closest('[data-kassa-context-row]');
                        const deleteButton = menu.querySelector('[data-kassa-delete-row]');

                        if (!row) {
                            return;
                        }

                        menu.dataset.row = JSON.stringify(row.dataset);
                        deleteButton?.toggleAttribute('disabled', ! row.dataset.kassaDeleteUrl);
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

                    menu.querySelector('[data-kassa-delete-row]')?.addEventListener('click', async () => {
                        const data = JSON.parse(menu.dataset.row || '{}');

                        if (! data.kassaDeleteUrl) {
                            return;
                        }

                        const dialog = document.querySelector('[data-kassa-delete-dialog]');
                        dialog.dataset.kassaDeleteUrl = data.kassaDeleteUrl;
                        document.querySelector('[data-kassa-delete-open]')?.click();
                    });

                    document.querySelector('[data-kassa-delete-dialog]')?.addEventListener('ui:alert-dialog:confirm', async (event) => {
                        event.preventDefault();

                        const dialog = event.currentTarget;
                        const confirmButton = event.detail?.confirmButton;

                        try {
                            confirmButton.disabled = true;

                            const response = await fetch(dialog.dataset.kassaDeleteUrl, {
                                method: 'POST',
                                body: new URLSearchParams({
                                    _token: document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value || '',
                                    _method: 'DELETE',
                                }),
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });
                            const { payload, text } = await parseResponse(response);

                            if (! response.ok) {
                                throw new Error(responseLog(response, payload, text));
                            }

                            dialog.dispatchEvent(new Event('ui:alert-dialog:close', { bubbles: true }));
                            refreshKassaTable();
                        } catch (error) {
                            console.error('Kassa delete failed', error);
                            window.alert(error.message || 'Не удалось удалить кассовую запись.');
                        } finally {
                            confirmButton.disabled = false;
                        }
                    });
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', () => {
                        initKassaAmountInputs();
                        initKassaDirectionToggles();
                        initKassaCreateForms();
                        initKassaContextMenu();
                    });
                } else {
                    initKassaAmountInputs();
                    initKassaDirectionToggles();
                    initKassaCreateForms();
                    initKassaContextMenu();
                }

                document.addEventListener('livewire:navigated', () => {
                    initKassaAmountInputs();
                    initKassaDirectionToggles();
                    initKassaCreateForms();
                    initKassaContextMenu();
                });
            })();
        </script>
    @endonce
@endsection
