@extends('layouts.app', [
    'title' => 'Курсы валют',
    'titleDescription' => 'Интервалы наблюдения курсов MBank и Obank. Для графика главным временем является период, когда мы реально видели курс: от observed_from до observed_to.',
])

@php
    $rate = static fn ($value) => $value !== null ? number_format((float) $value, 4, ',', ' ') : '—';
    $displayTimezone = config('app.display_timezone', 'Europe/Moscow');
    $date = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse((string) $value, 'UTC')->timezone($displayTimezone)->format('d.m.Y H:i:s') : '—';
    $rateTypeLabels = [
        'cash' => 'Наличные',
        'non_cash' => 'Безналичные',
        'official' => 'Официальный',
    ];
    $providerLabels = [
        'mbank' => 'MBank',
        'obank' => 'Obank',
    ];
    $interval = static function ($from, $to, $lastSeenAt): string {
        if (! $from) {
            return '—';
        }

        $fromDate = \Illuminate\Support\Carbon::parse((string) $from, 'UTC');
        $toDate = $to
            ? \Illuminate\Support\Carbon::parse((string) $to, 'UTC')
            : ($lastSeenAt ? \Illuminate\Support\Carbon::parse((string) $lastSeenAt, 'UTC') : now('UTC'));

        return $fromDate->diffForHumans($toDate, true, false, 3);
    };
@endphp

@section('page_actions')
    <form method="post" action="{{ route('exchange-rates.sync') }}">
        @csrf
        <x-ui.button type="submit" size="lg" variant="ghost">
            Обновить курсы
        </x-ui.button>
    </form>
@endsection

@section('content')
    @if (session('status'))
        <div class="mb-4 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-600/20">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-4 border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <form method="get" action="{{ route('exchange-rates.index') }}">
            <div class="grid gap-4 lg:grid-cols-4">
                <x-ui.select
                    label="Банк"
                    name="provider"
                    :value="$filters['provider'] ?? ''"
                    :options="collect($providers)->mapWithKeys(fn ($provider, $key) => [$key => $providerLabels[$key] ?? $provider])->all()"
                    placeholder="Все банки"
                />

                <x-ui.select
                    label="Тип курса"
                    name="rate_type"
                    :value="$filters['rate_type'] ?? ''"
                    :options="collect($rateTypes)->mapWithKeys(fn ($type, $key) => [$key => $rateTypeLabels[$key] ?? $type])->all()"
                    placeholder="Все типы"
                />

                <x-ui.select
                    label="Валюта"
                    name="currency_code"
                    :value="$filters['currency_code'] ?? ''"
                    :options="$currencyCodes"
                    placeholder="Все валюты"
                />

                <label class="flex items-end gap-3 pb-2 text-sm font-medium text-gray-900 dark:text-white">
                    <input
                        type="checkbox"
                        name="show_history"
                        value="1"
                        class="size-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        @checked(! empty($filters['show_history']))
                    >
                    <span>Показать историю</span>
                </label>
            </div>

            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <x-ui.button :href="route('exchange-rates.index')" variant="ghost" wire:navigate>
                    Сбросить
                </x-ui.button>

                <x-ui.button type="submit" variant="soft">
                    Показать
                </x-ui.button>
            </div>
        </form>
    </div>

    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
        table-class="!min-w-[1400px]"
    >
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th first>Банк</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Тип</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Валюта</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Покупка</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Продажа</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Офиц.</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Наблюдали с</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Наблюдали до</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Интервал</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Последний раз</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last>Источник</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @forelse ($rates as $item)
            <tr
                class="align-top hover:bg-gray-50 dark:hover:bg-white/5"
                data-exchange-rate-context-row
                data-exchange-rate-id="{{ $item->exchange_rate_id }}"
                data-exchange-rate-provider="{{ $providerLabels[$item->provider] ?? $item->provider }}"
                data-exchange-rate-type="{{ $rateTypeLabels[$item->rate_type] ?? $item->rate_type }}"
                data-exchange-rate-currency="{{ trim((string) $item->currency_code) }}"
                data-exchange-rate-hash="{{ $item->quote_hash }}"
                data-exchange-rate-first-source="{{ $item->first_source_record_id ?: '' }}"
                data-exchange-rate-last-source="{{ $item->last_source_record_id ?: '' }}"
                data-exchange-rate-buy="{{ $rate($item->buy_rate) }}"
                data-exchange-rate-sell="{{ $rate($item->sell_rate) }}"
                data-exchange-rate-official="{{ $rate($item->official_rate) }}"
                data-exchange-rate-observed-from="{{ $date($item->observed_from) }}"
                data-exchange-rate-observed-to="{{ $item->observed_to ? $date($item->observed_to) : 'текущий' }}"
                data-exchange-rate-last-seen="{{ $date($item->last_seen_at) }}"
                data-exchange-rate-bank-valid-from="{{ $item->bank_valid_from ? $date($item->bank_valid_from) : '—' }}"
                data-exchange-rate-interval="{{ $interval($item->observed_from, $item->observed_to, $item->last_seen_at) }}"
            >
                <x-ui.sticky-table-td first strong>
                    {{ $providerLabels[$item->provider] ?? $item->provider }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    {{ $rateTypeLabels[$item->rate_type] ?? $item->rate_type }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false">
                    <div class="font-mono font-semibold text-gray-900 dark:text-white">{{ trim((string) $item->currency_code) }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $item->currency_name ?: 'к ' . trim((string) $item->rate_currency_code) }}
                    </div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="tabular-nums" align="right">
                    {{ $rate($item->buy_rate) }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="tabular-nums" align="right">
                    {{ $rate($item->sell_rate) }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="tabular-nums" align="right">
                    {{ $rate($item->official_rate) }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="tabular-nums">
                    {{ $date($item->observed_from) }}
                    @if ($item->bank_valid_from)
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            банк: {{ $date($item->bank_valid_from) }}
                        </div>
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="tabular-nums">
                    @if ($item->observed_to)
                        {{ $date($item->observed_to) }}
                    @else
                        <span class="inline-flex rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/20">
                            текущий
                        </span>
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    {{ $interval($item->observed_from, $item->observed_to, $item->last_seen_at) }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="tabular-nums">
                    {{ $date($item->last_seen_at) }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td last :nowrap="false">
                    <div class="font-mono text-xs text-gray-500 dark:text-gray-400">
                        first #{{ $item->first_source_record_id ?: '—' }}
                        · last #{{ $item->last_source_record_id ?: '—' }}
                    </div>
                    <div class="mt-1 font-mono text-xs text-gray-400">
                        {{ substr($item->quote_hash, 0, 12) }}
                    </div>
                </x-ui.sticky-table-td>
            </tr>
        @empty
            <tr>
                <td class="py-12 text-center text-sm text-gray-500 dark:text-gray-400" colspan="11">
                    Курсы валют пока не загружены.
                </td>
            </tr>
        @endforelse
    </x-ui.sticky-table>

    @if ($rates->count() === 500)
        <p class="mt-3 px-4 text-xs text-gray-500 sm:px-6 lg:px-8">
            Показаны первые 500 интервалов по текущему фильтру.
        </p>
    @endif

    <x-ui.context-menu trigger-selector="[data-exchange-rate-context-row]">
        <x-slot:menu>
            <div class="border-b border-gray-100 px-3 py-2 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                <div class="font-medium text-gray-900 dark:text-white" data-exchange-rate-menu-title>Курс валют</div>
                <div class="mt-0.5 font-mono" data-exchange-rate-menu-subtitle></div>
            </div>

            <x-ui.context-menu-item data-exchange-rate-menu-copy-hash>
                Копировать hash
            </x-ui.context-menu-item>

            <x-ui.context-menu-item data-exchange-rate-menu-copy-source>
                Копировать ID источника
            </x-ui.context-menu-item>

            <div class="group/submenu relative">
                <button type="button" role="menuitem" tabindex="-1" class="flex w-full items-center justify-between gap-x-3 rounded-md px-3 py-1.5 text-left text-sm text-gray-700 outline-none hover:bg-gray-50 focus:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/10 dark:focus:bg-white/10">
                    <span>Источник</span>
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4 text-gray-400">
                        <path d="M7.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L10.94 10 7.22 6.28a.75.75 0 0 1 0-1.06Z" />
                    </svg>
                </button>

                <div class="absolute top-0 left-full ml-1 hidden min-w-52 rounded-lg border border-gray-200 bg-white p-1 shadow-xl ring-1 ring-black/5 group-hover/submenu:block group-focus-within/submenu:block dark:border-white/10 dark:bg-gray-900 dark:ring-white/10">
                    <x-ui.context-menu-item data-exchange-rate-menu-copy-first-source>
                        Копировать первый source id
                    </x-ui.context-menu-item>
                    <x-ui.context-menu-item data-exchange-rate-menu-copy-last-source>
                        Копировать последний source id
                    </x-ui.context-menu-item>
                </div>
            </div>

            <div class="my-1 border-t border-gray-100 dark:border-white/10"></div>

            <x-ui.context-menu-item data-exchange-rate-menu-properties>
                <span class="font-mono text-base leading-none">⋯</span>
                <span>Свойства</span>
            </x-ui.context-menu-item>
        </x-slot:menu>
    </x-ui.context-menu>

    <button type="button" class="hidden" data-ui-modal-open="exchange-rate-properties-dialog" data-exchange-rate-properties-open></button>

    <x-ui.modal
        id="exchange-rate-properties-dialog"
        title="Свойства курса"
        description="Свойства объекта строки таблицы курсов валют."
        size="xl"
    >
        <div class="px-6 py-5">
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Банк</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white" data-exchange-rate-property="provider">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Тип курса</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white" data-exchange-rate-property="type">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Валюта</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-exchange-rate-property="currency">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">ID строки</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-exchange-rate-property="id">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Покупка</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-exchange-rate-property="buy">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Продажа</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-exchange-rate-property="sell">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Официальный</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-exchange-rate-property="official">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Интервал</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white" data-exchange-rate-property="interval">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Наблюдали с</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-exchange-rate-property="observedFrom">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Наблюдали до</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-exchange-rate-property="observedTo">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Последний раз</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-exchange-rate-property="lastSeen">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Время банка</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-exchange-rate-property="bankValidFrom">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Первый source id</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-exchange-rate-property="firstSource">—</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Последний source id</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white" data-exchange-rate-property="lastSource">—</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Hash</dt>
                    <dd class="mt-1 break-all font-mono text-xs text-gray-900 dark:text-white" data-exchange-rate-property="hash">—</dd>
                </div>
            </dl>
        </div>
    </x-ui.modal>

    @once
        <script>
            (() => {
                const copyText = async (value) => {
                    if (!value) {
                        return;
                    }

                    try {
                        await navigator.clipboard.writeText(value);
                    } catch (error) {
                        const input = document.createElement('textarea');
                        input.value = value;
                        input.style.position = 'fixed';
                        input.style.opacity = '0';
                        document.body.appendChild(input);
                        input.select();
                        document.execCommand('copy');
                        input.remove();
                    }
                };

                const initExchangeRateContextMenu = () => {
                    const menu = document.querySelector('[data-ui-context-menu-trigger-selector="[data-exchange-rate-context-row]"]');

                    if (!menu || menu.dataset.exchangeRateMenuReady === 'true') {
                        return;
                    }

                    menu.dataset.exchangeRateMenuReady = 'true';

                    document.addEventListener('contextmenu', (event) => {
                        const row = event.target.closest('[data-exchange-rate-context-row]');

                        if (!row) {
                            return;
                        }

                        menu.dataset.hash = row.dataset.exchangeRateHash || '';
                        menu.dataset.firstSource = row.dataset.exchangeRateFirstSource || '';
                        menu.dataset.lastSource = row.dataset.exchangeRateLastSource || '';
                        menu.dataset.row = JSON.stringify(row.dataset);

                        const title = menu.querySelector('[data-exchange-rate-menu-title]');
                        const subtitle = menu.querySelector('[data-exchange-rate-menu-subtitle]');

                        if (title) {
                            title.textContent = `${row.dataset.exchangeRateProvider || ''} · ${row.dataset.exchangeRateCurrency || ''}`;
                        }

                        if (subtitle) {
                            subtitle.textContent = `${row.dataset.exchangeRateType || ''} · #${row.dataset.exchangeRateId || ''}`;
                        }
                    });

                    menu.querySelector('[data-exchange-rate-menu-copy-hash]')?.addEventListener('click', () => {
                        copyText(menu.dataset.hash || '');
                    });

                    menu.querySelector('[data-exchange-rate-menu-copy-source]')?.addEventListener('click', () => {
                        copyText(menu.dataset.lastSource || menu.dataset.firstSource || '');
                    });

                    menu.querySelector('[data-exchange-rate-menu-copy-first-source]')?.addEventListener('click', () => {
                        copyText(menu.dataset.firstSource || '');
                    });

                    menu.querySelector('[data-exchange-rate-menu-copy-last-source]')?.addEventListener('click', () => {
                        copyText(menu.dataset.lastSource || '');
                    });

                    menu.querySelector('[data-exchange-rate-menu-properties]')?.addEventListener('click', () => {
                        const data = JSON.parse(menu.dataset.row || '{}');
                        const properties = {
                            provider: data.exchangeRateProvider || '—',
                            type: data.exchangeRateType || '—',
                            currency: data.exchangeRateCurrency || '—',
                            id: data.exchangeRateId || '—',
                            buy: data.exchangeRateBuy || '—',
                            sell: data.exchangeRateSell || '—',
                            official: data.exchangeRateOfficial || '—',
                            interval: data.exchangeRateInterval || '—',
                            observedFrom: data.exchangeRateObservedFrom || '—',
                            observedTo: data.exchangeRateObservedTo || '—',
                            lastSeen: data.exchangeRateLastSeen || '—',
                            bankValidFrom: data.exchangeRateBankValidFrom || '—',
                            firstSource: data.exchangeRateFirstSource || '—',
                            lastSource: data.exchangeRateLastSource || '—',
                            hash: data.exchangeRateHash || '—',
                        };

                        for (const [key, value] of Object.entries(properties)) {
                            const element = document.querySelector(`[data-exchange-rate-property="${key}"]`);

                            if (element) {
                                element.textContent = value || '—';
                            }
                        }

                        document.querySelector('[data-exchange-rate-properties-open]')?.click();
                    });
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initExchangeRateContextMenu);
                } else {
                    initExchangeRateContextMenu();
                }

                document.addEventListener('livewire:navigated', initExchangeRateContextMenu);
            })();
        </script>
    @endonce
@endsection
