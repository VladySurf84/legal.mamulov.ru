@extends('layouts.app', ['title' => 'Банковские счета'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Банковские счета</h1>
            <div class="subtle">Расчетные и накопительные счета по нашим юридическим лицам.</div>
        </div>
        <form method="post" action="{{ route('bank-directories.import') }}">
            @csrf
            <button type="submit">Обновить из mamulov.ru</button>
        </form>
    </div>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    <div class="panel">
        <table>
            <thead>
            <tr>
                <th>Юрлицо</th>
                <th>Счет</th>
                <th>Банк</th>
                <th>Тип</th>
                <th>Валюта</th>
                <th>Дата открытия</th>
                <th class="money">Баланс</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($accounts as $account)
                <tr>
                    <td>
                        <strong>{{ $account->legalEntity?->legal_name ?? 'Юрлицо #' . $account->legal_id }}</strong>
                        @if ($account->legalEntity?->legal_inn)
                            <div class="subtle">ИНН {{ $account->legalEntity->legal_inn }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="code">{{ $account->account_number }}</span>
                        <div class="subtle">{{ $account->name }}</div>
                    </td>
                    <td>
                        {{ $account->bank?->bank_name ?? $account->bank_id }}
                        <div class="subtle code">{{ $account->bank_id }}</div>
                    </td>
                    <td>{{ $account->account_type }}</td>
                    <td>{{ $account->currency }}</td>
                    <td>{{ $account->activation_date?->format('d.m.Y') ?? '—' }}</td>
                    <td class="money">{{ $account->balance_otb !== null ? number_format((float) $account->balance_otb, 2, ',', ' ') : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Банковские счета пока не загружены.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
