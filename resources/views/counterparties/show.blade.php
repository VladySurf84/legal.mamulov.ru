@extends('layouts.app', ['title' => 'Детализация контрагента'])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $contractorName }}</h1>
            <div class="subtle">ИНН {{ $contractorInn }} · детализация сальдо по новым банковским документам.</div>
        </div>
        <div class="actions">
            <a class="button secondary" href="{{ route('counterparties.index', ['legal_id' => $filters['legal_id'] ?? null, 'contractor_inn' => $contractorInn]) }}">
                К списку
            </a>
        </div>
    </div>

    <div class="panel" style="margin-bottom: 16px;">
        <form class="form" method="get" action="{{ route('counterparties.show', ['contractorInn' => $contractorInn]) }}">
            <div class="grid">
                <div class="field">
                    <label for="legal_id">Наше юрлицо</label>
                    <select id="legal_id" name="legal_id">
                        <option value="">Все юрлица</option>
                        @foreach ($legalEntities as $legalEntity)
                            <option value="{{ $legalEntity->legal_id }}" @selected((string) ($filters['legal_id'] ?? '') === (string) $legalEntity->legal_id)>
                                {{ $legalEntity->legal_name }}@if ($legalEntity->legal_inn) · ИНН {{ $legalEntity->legal_inn }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <a class="button secondary" href="{{ route('counterparties.show', ['contractorInn' => $contractorInn]) }}">Сбросить</a>
                <button type="submit">Показать</button>
            </div>
        </form>
    </div>

    <div class="badges" style="margin-bottom: 16px;">
        <span class="badge">Операций: {{ number_format($summary['count'], 0, ',', ' ') }}</span>
        <span class="badge">Сальдо: {{ number_format($summary['saldo'], 2, ',', ' ') }}</span>
        <span class="badge">Приход: {{ number_format($summary['income_amount'], 2, ',', ' ') }}</span>
        <span class="badge">Расход: {{ number_format($summary['expense_amount'], 2, ',', ' ') }}</span>
    </div>

    <div class="panel">
        <table>
            <thead>
            <tr>
                <th>Дата</th>
                <th>Наше юрлицо</th>
                <th>Направление</th>
                <th class="money">Приход</th>
                <th class="money">Расход</th>
                <th class="money">В сальдо</th>
                <th>Счет / операция</th>
                <th>Назначение</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($operations as $operation)
                <tr>
                    <td>
                        {{ $operation->operation_date ? \Illuminate\Support\Carbon::parse($operation->operation_date)->format('d.m.Y') : '—' }}
                        @if ($operation->document_date && $operation->document_date !== $operation->operation_date)
                            <div class="subtle">Док. {{ \Illuminate\Support\Carbon::parse($operation->document_date)->format('d.m.Y') }}</div>
                        @endif
                    </td>
                    <td>{{ $operation->legal_name ?? '—' }}</td>
                    <td>
                        <span class="badge">{{ $operation->direction === 'income' ? 'Приход' : 'Расход' }}</span>
                    </td>
                    <td class="money">{{ number_format((float) $operation->income_amount, 2, ',', ' ') }}</td>
                    <td class="money">{{ number_format((float) $operation->expense_amount, 2, ',', ' ') }}</td>
                    <td class="money">{{ number_format((float) $operation->signed_amount, 2, ',', ' ') }}</td>
                    <td>
                        <div class="code">{{ $operation->account_number }}</div>
                        <div class="subtle code">{{ $operation->external_operation_id }}</div>
                        <div class="subtle">Документ #{{ $operation->document_id }}</div>
                    </td>
                    <td>{{ $operation->payment_purpose ?: $operation->document_title }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">По этому контрагенту операций нет.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
