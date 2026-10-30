@extends('layouts.app', ['title' => 'Банковские счета'])

@section('page_actions')
    @if ($canManageBankAccounts)
        <form method="post" action="{{ route('bank-directories.import') }}">
            @csrf
            <x-ui.button type="submit" size="lg">
                Обновить из mamulov.ru
            </x-ui.button>
        </form>
    @endif
@endsection

@section('before_content')
    @if (session('status'))
        <div class="mb-4 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-600/20">
            {{ session('status') }}
        </div>
    @endif
@endsection

@section('content')
    @php
        $balanceTotals = $accounts->groupBy('currency')->map(fn ($currencyAccounts) => $currencyAccounts->sum(function ($account) {
            $snapshot = $account->latest_balance ? json_decode($account->latest_balance, true) : null;

            return (float) ($snapshot['amount'] ?? $account->balance_otb ?? 0);
        }));
    @endphp

    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :sticky-summary-enabled="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
    >
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th padding="p-0" aria-label="Юрлицо" style="width: 5px; min-width: 5px; max-width: 5px; padding: 0;"></x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Счет</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Банк</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Название счета</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Тип</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Валюта</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Дата открытия</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last align="right">Баланс</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @forelse ($accounts as $account)
            @php
                $latestBalance = $account->latest_balance ? json_decode($account->latest_balance, true) : null;
                $balanceAmount = $latestBalance['amount'] ?? $account->balance_otb;
            @endphp
            <tr class="align-top hover:bg-gray-50">
                <td
                    class="border-b border-gray-200 dark:border-white/10"
                    title="{{ $account->legalEntity?->legal_name ?? 'Юрлицо #' . $account->legal_id }}"
                    aria-label="{{ $account->legalEntity?->legal_name ?? 'Юрлицо #' . $account->legal_id }}"
                    style="width: 5px; min-width: 5px; max-width: 5px; padding: 0; background-color: {{ $account->legalEntity?->legal_color ?: '#e5e7eb' }};"
                ></td>

                <x-ui.sticky-table-td>
                    <div class="font-mono text-gray-900">{{ $account->account_number }}</div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false">
                    <div class="font-medium text-gray-900">{{ $account->bank?->bank_name ?? $account->bank_id }}</div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false">
                    {{ $account->name ?: '—' }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    {{ $account->account_type ?: '—' }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    {{ $account->currency ?: '—' }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="tabular-nums">
                    {{ $account->activation_date?->format('d.m.Y') ?? '—' }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="font-semibold tabular-nums" last align="right" strong>
                    {{ $balanceAmount !== null ? number_format((float) $balanceAmount, 2, ',', ' ') : '—' }}
                </x-ui.sticky-table-td>
            </tr>
        @empty
            <tr>
                <td class="py-8 text-center text-sm text-gray-500 dark:text-gray-400" colspan="8">
                    Банковские счета пока не загружены.
                </td>
            </tr>
        @endforelse

        <x-slot:stickySummary>
            <tr>
                <th scope="row" colspan="7" class="border-t border-gray-300 px-3 py-2.5 text-left text-sm font-semibold text-gray-900 dark:border-white/15 dark:text-white">
                    Всего счетов: {{ $accounts->count() }}
                </th>
                <x-ui.sticky-table-td summary last align="right" class="tabular-nums">
                    @forelse ($balanceTotals as $currency => $total)
                        <div>
                            {{ number_format($total, 2, ',', ' ') }}@if ($balanceTotals->count() > 1) {{ $currency ?: '—' }}@endif
                        </div>
                    @empty
                        0,00
                    @endforelse
                </x-ui.sticky-table-td>
            </tr>
        </x-slot:stickySummary>
    </x-ui.sticky-table>
@endsection
