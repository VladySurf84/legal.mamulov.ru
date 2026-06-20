@extends('layouts.app', ['title' => 'Контрагенты'])

@section('content')
    @php
        $showLegalEntitiesCount = empty($filters['legal_id']);
        $emptyColspan = $showLegalEntitiesCount ? 12 : 11;
    @endphp

    <div class="page-head">
        <div>
            <h1>Контрагенты</h1>
            <div class="subtle">Сводка по контрагентам из новых документов: legal.documents и legal.document_bank_transaction.</div>
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

                <label class="checkline" for="only_negative_diff">
                    <input
                        id="only_negative_diff"
                        name="only_negative_diff"
                        type="checkbox"
                        value="1"
                        @checked((bool) ($filters['only_negative_diff'] ?? false))
                    >
                    <span>Только с отрицательной разницей</span>
                </label>
            </div>

            <div class="form-actions">
                <a class="button secondary" href="{{ route('counterparties.index') }}">Сбросить</a>
                <button type="submit">Показать</button>
            </div>
        </form>
    </div>

    <div class="badges" style="margin-bottom: 16px;">
        <span class="badge">Контрагентов: {{ number_format($summary['count'], 0, ',', ' ') }}</span>
        <span class="badge">Входящее: {{ number_format($summary['opening_amount'], 2, ',', ' ') }}</span>
        <span class="badge">Наше сальдо: {{ number_format($summary['saldo'], 2, ',', ' ') }}</span>
        <span class="badge">Книги покупок: {{ number_format($summary['buh_saldo'], 2, ',', ' ') }}</span>
        <span class="badge">Разница: {{ number_format($summary['saldo_diff'], 2, ',', ' ') }}</span>
        <span class="badge">Разница НДС: {{ number_format($summary['vat_diff'], 2, ',', ' ') }}</span>
    </div>

    <div class="panel">
        <table>
            <thead>
            <tr>
                <th>Контрагент</th>
                <th>ИНН</th>
                <th class="money">Входящее</th>
                <th class="money">Наше сальдо</th>
                <th class="money">Книги покупок</th>
                <th class="money">Разница</th>
                <th class="money">Разница НДС</th>
                <th class="money">Приход</th>
                <th class="money">Расход</th>
                <th class="money">Операций</th>
                @if ($showLegalEntitiesCount)
                    <th class="money">Наших юрлиц</th>
                @endif
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($counterparties as $counterparty)
                <tr>
                    <td><strong>{{ $counterparty->contractor_name }}</strong></td>
                    <td class="code">{{ $counterparty->contractor_inn }}</td>
                    <td class="money">{{ number_format((float) $counterparty->opening_amount, 2, ',', ' ') }}</td>
                    <td class="money">{{ number_format((float) $counterparty->saldo, 2, ',', ' ') }}</td>
                    <td class="money">{{ number_format((float) $counterparty->buh_saldo, 2, ',', ' ') }}</td>
                    <td class="money">{{ number_format((float) $counterparty->saldo_diff, 2, ',', ' ') }}</td>
                    <td class="money">{{ number_format((float) $counterparty->vat_diff, 2, ',', ' ') }}</td>
                    <td class="money">{{ number_format((float) $counterparty->income_amount, 2, ',', ' ') }}</td>
                    <td class="money">{{ number_format((float) $counterparty->expense_amount, 2, ',', ' ') }}</td>
                    <td class="money">{{ number_format((int) $counterparty->operations_count, 0, ',', ' ') }}</td>
                    @if ($showLegalEntitiesCount)
                        <td class="money">{{ number_format((int) $counterparty->legal_entities_count, 0, ',', ' ') }}</td>
                    @endif
                    <td>
                        <div class="actions">
                            <a class="button secondary" href="{{ route('counterparties.show', ['contractorInn' => $counterparty->contractor_inn, 'legal_id' => $filters['legal_id'] ?? null]) }}">
                                Детализация
                            </a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $emptyColspan }}">По этим фильтрам контрагентов нет.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
