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
    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
    >
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th first>Юрлицо</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Счет</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Банк</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Тип</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Валюта</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Дата открытия</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last align="right">Баланс</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @forelse ($accounts as $account)
            <tr class="align-top hover:bg-gray-50">
                <x-ui.sticky-table-td first :nowrap="false">
                    <div class="font-medium text-gray-900">{{ $account->legalEntity?->legal_name ?? 'Юрлицо #' . $account->legal_id }}</div>
                    @if ($account->legalEntity?->legal_inn)
                        <div class="mt-1 text-xs text-gray-500">ИНН {{ $account->legalEntity->legal_inn }}</div>
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    <div class="font-mono text-gray-900">{{ $account->account_number }}</div>
                    <div class="mt-1 text-gray-500">{{ $account->name }}</div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false">
                    <div class="font-medium text-gray-900">{{ $account->bank?->bank_name ?? $account->bank_id }}</div>
                    <div class="mt-1 font-mono text-xs text-gray-400">{{ $account->bank_id }}</div>
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
                    {{ $account->balance_otb !== null ? number_format((float) $account->balance_otb, 2, ',', ' ') : '—' }}
                </x-ui.sticky-table-td>
            </tr>
        @empty
            <tr>
                <td class="py-8 text-center text-sm text-gray-500 dark:text-gray-400" colspan="7">
                    Банковские счета пока не загружены.
                </td>
            </tr>
        @endforelse
    </x-ui.sticky-table>
@endsection
