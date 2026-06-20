@extends('layouts.app', ['title' => 'Контрагенты'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Контрагенты</h1>
            <div class="subtle">Сводка по ИНН из банка, книг бухгалтера и бухгалтерского сальдо.</div>
        </div>
    </div>

    <div class="panel" style="margin-bottom: 16px;">
        <form class="form" method="get" action="{{ route('counterparties.index') }}">
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
            </div>

            <div class="form-actions">
                <a class="button secondary" href="{{ route('counterparties.index') }}">Сбросить</a>
                <button type="submit">Показать</button>
            </div>
        </form>
    </div>

    <div class="badges" style="margin-bottom: 16px;">
        <span class="badge">Контрагентов: {{ number_format($summary['count'], 0, ',', ' ') }}</span>
        <span class="badge">Сальдо: {{ number_format($summary['saldo'], 2, ',', ' ') }}</span>
    </div>

    <div class="panel">
        <table>
            <thead>
            <tr>
                <th>Контрагент</th>
                <th>ИНН</th>
                <th class="money">Сальдо</th>
                <th class="money">Строк источников</th>
                <th class="money">Источников</th>
                <th class="money">Наших юрлиц</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($counterparties as $counterparty)
                <tr>
                    <td><strong>{{ $counterparty->contractor_name }}</strong></td>
                    <td class="code">{{ $counterparty->contractor_inn }}</td>
                    <td class="money">{{ number_format((float) $counterparty->saldo, 2, ',', ' ') }}</td>
                    <td class="money">{{ number_format((int) $counterparty->source_rows_count, 0, ',', ' ') }}</td>
                    <td class="money">{{ number_format((int) $counterparty->source_systems_count, 0, ',', ' ') }}</td>
                    <td class="money">{{ number_format((int) $counterparty->legal_entities_count, 0, ',', ' ') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">По этим фильтрам контрагентов нет.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
