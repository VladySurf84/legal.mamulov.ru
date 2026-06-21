@extends('layouts.app', ['title' => 'Money layer'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Money layer</h1>
            <div class="subtle">Интерпретационный слой денежных ребер: плательщик -> получатель.</div>
        </div>
        <form method="post" action="{{ route('money-layer.rebuild') }}">
            @csrf
            <button type="submit">Пересчитать слой</button>
        </form>
    </div>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    <div class="panel" style="margin-bottom: 16px;">
        <form class="form" method="get" action="{{ route('money-layer.index') }}">
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
                <div class="field">
                    <label for="contractor_inn">ИНН контрагента</label>
                    <input id="contractor_inn" name="contractor_inn" value="{{ $filters['contractor_inn'] ?? '' }}" inputmode="numeric">
                </div>
                <div class="field">
                    <label for="party">Участник / ИНН</label>
                    <input id="party" name="party" value="{{ $filters['party'] ?? '' }}">
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
                <a class="button secondary" href="{{ route('money-layer.index') }}" wire:navigate>Сбросить</a>
                <button type="submit">Показать</button>
            </div>
        </form>
    </div>

    <div class="badges" style="margin-bottom: 16px;">
        <span class="badge">Ребер: {{ $summary['count'] }}</span>
        <span class="badge">Сумма: {{ number_format($summary['total_amount'], 2, ',', ' ') }}</span>
        <span class="badge">Период: {{ $summary['min_date'] ?? '—' }} -> {{ $summary['max_date'] ?? '—' }}</span>
    </div>

    <div class="panel">
        <table>
            <thead>
            <tr>
                <th>Дата</th>
                <th>Откуда</th>
                <th>Куда</th>
                <th class="money">Сумма</th>
                <th>Счет / операция</th>
                <th>Назначение</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($edges as $edge)
                <tr>
                    <td>{{ $edge->occurred_on }}</td>
                    <td>
                        <strong>{{ $edge->payer_name_snapshot ?? '—' }}</strong>
                        <div class="subtle">{{ $edge->payer_inn_snapshot ?? '' }}</div>
                    </td>
                    <td>
                        <strong>{{ $edge->recipient_name_snapshot ?? '—' }}</strong>
                        <div class="subtle">{{ $edge->recipient_inn_snapshot ?? '' }}</div>
                    </td>
                    <td class="money">{{ number_format((float) $edge->amount, 2, ',', ' ') }} {{ $edge->currency }}</td>
                    <td>
                        <div class="code">{{ $edge->account_number ?? $edge->algorithm }}</div>
                        <div class="subtle code">{{ $edge->external_operation_id ?? 'document #'.$edge->source_document_id }}</div>
                    </td>
                    <td>{{ $edge->payment_purpose }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Денежный слой пока пуст. Пересчитай слой после загрузки банковских документов.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
