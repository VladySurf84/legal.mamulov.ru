@extends('layouts.app', ['title' => 'Детализация контрагента'])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $contractorName }}</h1>
            <div class="subtle">ИНН {{ $contractorInn }} · сверка банка и книги покупок в одной ленте.</div>
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

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="errors">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="panel" style="margin-bottom: 16px;">
        <form class="form" method="post" action="{{ route('counterparties.opening-balances.store', ['contractorInn' => $contractorInn]) }}">
            @csrf
            <div class="grid">
                <div class="field">
                    <label for="opening_legal_id">Наше юрлицо</label>
                    <select id="opening_legal_id" name="legal_id" required>
                        <option value="">Выбери юрлицо</option>
                        @foreach ($legalEntities as $legalEntity)
                            <option value="{{ $legalEntity->legal_id }}" @selected((string) old('legal_id', $filters['legal_id'] ?? '') === (string) $legalEntity->legal_id)>
                                {{ $legalEntity->legal_name }}@if ($legalEntity->legal_inn) · ИНН {{ $legalEntity->legal_inn }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="starts_on">Дата старта</label>
                    <input id="starts_on" name="starts_on" type="date" value="{{ old('starts_on', '2025-01-01') }}" required>
                </div>
                <div class="field">
                    <label for="amount">Входящее сальдо</label>
                    <input id="amount" name="amount" type="number" step="0.01" value="{{ old('amount') }}" required>
                </div>
                <div class="field">
                    <label for="source">Источник</label>
                    <input id="source" name="source" value="{{ old('source', 'Акт сверки') }}">
                </div>
                <div class="field full">
                    <label for="comment">Комментарий</label>
                    <textarea id="comment" name="comment">{{ old('comment') }}</textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit">Сохранить входящее сальдо</button>
            </div>
        </form>
    </div>

    <div class="badges" style="margin-bottom: 16px;">
        <span class="badge">Операций банка: {{ number_format($summary['count'], 0, ',', ' ') }}</span>
        <span class="badge">Входящее: {{ number_format($summary['opening_amount'], 2, ',', ' ') }}</span>
        <span class="badge">Наше сальдо: {{ number_format($summary['saldo'], 2, ',', ' ') }}</span>
        <span class="badge">Книги покупок: {{ number_format($summary['buh_saldo'], 2, ',', ' ') }}</span>
        <span class="badge">Разница: {{ number_format($summary['saldo_diff'], 2, ',', ' ') }}</span>
        <span class="badge">Приход: {{ number_format($summary['income_amount'], 2, ',', ' ') }}</span>
        <span class="badge">Расход: {{ number_format($summary['expense_amount'], 2, ',', ' ') }}</span>
    </div>

    <div class="page-head" style="margin-top: 24px;">
        <div>
            <h1 style="font-size: 22px;">Сверка</h1>
            <div class="subtle">Банк и книга покупок в одной таблице. Итог показывает накопленную разницу: банк минус книга покупок.</div>
        </div>
    </div>

    <div class="panel">
        <table>
            <thead>
            <tr>
                <th>Дата</th>
                <th>Источник</th>
                <th>Наше юрлицо</th>
                <th>Документ</th>
                <th class="money">Приход</th>
                <th class="money">Расход</th>
                <th class="money">Книга покупок</th>
                <th class="money">НДС</th>
                <th class="money">В итог</th>
                <th class="money">Итог</th>
                <th>Описание</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($ledgerEntries as $entry)
                <tr>
                    <td>{{ $entry->event_date ? \Illuminate\Support\Carbon::parse($entry->event_date)->format('d.m.Y') : '—' }}</td>
                    <td>
                        <span class="badge">
                            @if ($entry->source_type === 'bank')
                                Банк
                            @elseif ($entry->source_type === 'opening_balance')
                                Входящее
                            @else
                                Книга покупок
                            @endif
                        </span>
                    </td>
                    <td>{{ $entry->legal_name ?? '—' }}</td>
                    <td>
                        <div>{{ $entry->primary_ref ?: '—' }}</div>
                        @if ($entry->secondary_ref)
                            <div class="subtle code">{{ $entry->secondary_ref }}</div>
                        @endif
                    </td>
                    <td class="money">{{ $entry->income_amount !== null ? number_format((float) $entry->income_amount, 2, ',', ' ') : '—' }}</td>
                    <td class="money">{{ $entry->expense_amount !== null ? number_format((float) $entry->expense_amount, 2, ',', ' ') : '—' }}</td>
                    <td class="money">{{ $entry->purchase_amount !== null ? number_format((float) $entry->purchase_amount, 2, ',', ' ') : '—' }}</td>
                    <td class="money">{{ $entry->vat_amount !== null ? number_format((float) $entry->vat_amount, 2, ',', ' ') : '—' }}</td>
                    <td class="money">{{ number_format((float) $entry->reconciliation_amount, 2, ',', ' ') }}</td>
                    <td class="money">{{ number_format((float) $entry->running_saldo, 2, ',', ' ') }}</td>
                    <td>
                        <div>{{ $entry->description ?: '—' }}</div>
                        @if ($entry->source_type === 'opening_balance')
                            <form method="post" action="{{ route('counterparties.opening-balances.destroy', ['contractorInn' => $contractorInn, 'openingBalanceId' => $entry->source_id]) }}" style="margin-top: 8px;">
                                @csrf
                                @method('delete')
                                <input type="hidden" name="legal_id" value="{{ $filters['legal_id'] ?? '' }}">
                                <input type="hidden" name="page" value="{{ $ledgerPagination['page'] }}">
                                <button class="danger" type="submit">Удалить</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11">По этому контрагенту нет строк для сверки.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="form-actions">
        @if ($ledgerPagination['page'] > 1)
            <a class="button secondary" href="{{ route('counterparties.show', ['contractorInn' => $contractorInn, 'legal_id' => $filters['legal_id'] ?? null, 'page' => $ledgerPagination['page'] - 1]) }}">Новее</a>
        @else
            <span class="button secondary" style="opacity: .55;">Новее</span>
        @endif

        <span class="badge">Страница {{ $ledgerPagination['page'] }}, по {{ $ledgerPagination['per_page'] }}</span>

        @if ($ledgerPagination['has_more'])
            <a class="button secondary" href="{{ route('counterparties.show', ['contractorInn' => $contractorInn, 'legal_id' => $filters['legal_id'] ?? null, 'page' => $ledgerPagination['page'] + 1]) }}">Старее</a>
        @else
            <span class="button secondary" style="opacity: .55;">Старее</span>
        @endif
    </div>
@endsection
