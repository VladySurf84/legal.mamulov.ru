@extends('layouts.app', ['title' => 'VAT layer'])

@section('content')
    <div class="page-head">
        <div>
            <h1>VAT layer</h1>
            <div class="subtle">Интерпретационный слой НДС из книг бухгалтера и банковских назначений платежа.</div>
        </div>
        <div class="actions">
            <form method="post" action="{{ route('vat-layer.rebuild') }}">
                @csrf
                <button type="submit">Пересчитать книги</button>
            </form>
            <form method="post" action="{{ route('vat-layer.rebuild-bank') }}">
                @csrf
                <button type="submit">Пересчитать банк</button>
            </form>
        </div>
    </div>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    <div class="panel" style="margin-bottom: 16px;">
        <form class="form" method="get" action="{{ route('vat-layer.index') }}">
            <div class="grid">
                <div class="field">
                    <label for="source_system">Источник</label>
                    <select id="source_system" name="source_system">
                        <option value="">Все источники</option>
                        <option value="accountant_vat_book" @selected(($filters['source_system'] ?? '') === 'accountant_vat_book')>Книги бухгалтера</option>
                        <option value="bank_payment_vat" @selected(($filters['source_system'] ?? '') === 'bank_payment_vat')>Наш расчет по банку</option>
                    </select>
                </div>
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
                    <label>Период</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <input name="year" value="{{ $filters['year'] ?? '' }}" placeholder="2026" inputmode="numeric">
                        <select name="quarter">
                            <option value="">Все кварталы</option>
                            @foreach ([1, 2, 3, 4] as $quarter)
                                <option value="{{ $quarter }}" @selected((string) ($filters['quarter'] ?? '') === (string) $quarter)>Q{{ $quarter }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label for="direction">Направление</label>
                    <select id="direction" name="direction">
                        <option value="">Все</option>
                        <option value="output" @selected(($filters['direction'] ?? '') === 'output')>Начисленный НДС</option>
                        <option value="input" @selected(($filters['direction'] ?? '') === 'input')>Вычет НДС</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <a class="button secondary" href="{{ route('vat-layer.index') }}">Сбросить</a>
                <button type="submit">Показать</button>
            </div>
        </form>
    </div>

    <div class="badges" style="margin-bottom: 16px;">
        <span class="badge">Событий: {{ $summary['count'] }}</span>
        <span class="badge">Начислено: {{ number_format($summary['output_vat'], 2, ',', ' ') }}</span>
        <span class="badge">Вычет: {{ number_format($summary['input_vat'], 2, ',', ' ') }}</span>
        <span class="badge">Итого к уплате: {{ number_format($summary['vat_balance'], 2, ',', ' ') }}</span>
    </div>

    <div class="panel">
        <table>
            <thead>
            <tr>
                <th>Источник</th>
                <th>Период</th>
                <th>Дата</th>
                <th>Тип</th>
                <th>Юрлицо</th>
                <th>Контрагент</th>
                <th>Документ</th>
                <th class="money">Сумма</th>
                <th class="money">НДС</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($events as $event)
                <tr>
                    <td>{{ $event->source_system === 'bank_payment_vat' ? 'Банк' : 'Книга' }}</td>
                    <td>{{ $event->year }} Q{{ $event->quarter }}</td>
                    <td>{{ $event->occurred_on ?? '—' }}</td>
                    <td>{{ $event->vat_direction === 'input' ? 'Вычет' : 'Начисление' }}</td>
                    <td>{{ $event->legal_name }}</td>
                    <td>
                        <strong>{{ $event->contractor_name ?? '—' }}</strong>
                        <div class="subtle">{{ $event->contractor_inn ?? '' }}</div>
                    </td>
                    <td>
                        <span class="code">{{ $event->invoice_number ?? '—' }}</span>
                        <div class="subtle">{{ $event->invoice_date ?? '' }}</div>
                    </td>
                    <td class="money">{{ $event->amount_total !== null ? number_format((float) $event->amount_total, 2, ',', ' ') : '—' }}</td>
                    <td class="money">{{ number_format((float) $event->signed_vat_amount, 2, ',', ' ') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">VAT layer пока пуст. Пересчитай слой после импорта книг НДС или банковских документов.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
