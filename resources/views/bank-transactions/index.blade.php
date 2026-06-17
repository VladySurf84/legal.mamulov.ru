@extends('layouts.app', ['title' => 'Банковские транзакции'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Банковские транзакции</h1>
            <div class="subtle">Операции из банка, связанные со сверкой и юридическими лицами.</div>
        </div>
    </div>

    <div class="panel" style="margin-bottom: 16px;">
        <form class="form" method="get" action="{{ route('bank-transactions.index') }}">
            <div class="grid">
                <div class="field">
                    <label for="account_number">Счет</label>
                    <select id="account_number" name="account_number">
                        <option value="">Все счета</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->account_number }}" @selected(($filters['account_number'] ?? '') === $account->account_number)>
                                {{ $account->legalEntity?->legal_name ?? 'Юрлицо #' . $account->legal_id }} · {{ $account->name }} · {{ $account->account_number }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="type">Тип</label>
                    <select id="type" name="type">
                        <option value="">Все движения</option>
                        <option value="expense" @selected(($filters['type'] ?? '') === 'expense')>Расход</option>
                        <option value="income" @selected(($filters['type'] ?? '') === 'income')>Приход</option>
                    </select>
                </div>

                <div class="field">
                    <label for="contractor">Контрагент / ИНН</label>
                    <input id="contractor" name="contractor" value="{{ $filters['contractor'] ?? '' }}">
                </div>

                <div class="field">
                    <label>Период</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a class="button secondary" href="{{ route('bank-transactions.index') }}">Сбросить</a>
                <button type="submit">Показать</button>
            </div>
        </form>
    </div>

    <div class="badges" style="margin-bottom: 16px;">
        <span class="badge">Операций: {{ $summary['count'] }}</span>
        <span class="badge">Приход: {{ number_format($summary['income'], 2, ',', ' ') }}</span>
        <span class="badge">Расход: {{ number_format($summary['expense'], 2, ',', ' ') }}</span>
    </div>

    <div class="panel">
        <table>
            <thead>
            <tr>
                <th>Дата</th>
                <th>Юрлицо / счет</th>
                <th>Контрагент</th>
                <th class="money">Расход</th>
                <th class="money">Приход</th>
                <th>Назначение</th>
                <th class="money">Итог</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($transactions as $transaction)
                <tr>
                    <td>
                        {{ optional(\Illuminate\Support\Carbon::parse($transaction->date))->format('d.m.Y') }}
                        <div class="subtle code">#{{ $transaction->bank_transaction_id }}</div>
                    </td>
                    <td>
                        <strong>{{ $transaction->legal_name ?? 'Юрлицо #' . $transaction->legal_id }}</strong>
                        <div class="subtle">{{ $transaction->bank_account_name }}</div>
                        <div class="subtle code">{{ $transaction->account_number }} · {{ $transaction->bank_id }}</div>
                    </td>
                    <td>
                        {{ $transaction->name ?: '—' }}
                        @if ($transaction->contractor_inn)
                            <div class="subtle">ИНН {{ $transaction->contractor_inn }}</div>
                        @endif
                        @if ($transaction->contractor_bank_account)
                            <div class="subtle code">{{ $transaction->contractor_bank_account }}</div>
                        @endif
                        <div class="badges" style="margin-top: 6px;">
                            @if ((int) $transaction->has_vat === 1)<span class="badge">НДС</span>@endif
                            @if ((int) $transaction->dohras === 1)<span class="badge">дох/рас</span>@endif
                            @if ($transaction->type_alias)<span class="badge">{{ $transaction->type_alias }}</span>@endif
                            @if ($transaction->k_id)<span class="badge">касса</span>@endif
                        </div>
                    </td>
                    <td class="money">{{ $transaction->amount_p !== null ? number_format((float) $transaction->amount_p, 2, ',', ' ') : '' }}</td>
                    <td class="money">{{ $transaction->amount_m !== null ? number_format((float) $transaction->amount_m, 2, ',', ' ') : '' }}</td>
                    <td>
                        {{ $transaction->payment_purpose }}
                        <div class="subtle code">{{ $transaction->order_intraday }}</div>
                    </td>
                    <td class="money">{{ number_format((float) $transaction->total, 2, ',', ' ') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Банковские транзакции пока не загружены.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
