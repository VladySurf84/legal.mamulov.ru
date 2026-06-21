@extends('layouts.app', ['title' => 'Содержание книг бухгалтера'])

@php
    $bookLabels = [
        'purchase' => 'Покупки',
        'sales' => 'Продажи',
    ];
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Содержание книг бухгалтера</h1>
            <div class="subtle">Строки из книг покупок и продаж, которые загрузил бухгалтер.</div>
        </div>
        <div class="actions">
            <a class="button secondary" href="{{ route('vat-books.index') }}" wire:navigate>Импорт книг</a>
        </div>
    </div>

    <div class="panel" style="margin-bottom: 16px;">
        <form class="form" method="get" action="{{ route('vat-book-entries.index') }}">
            <div class="grid">
                <div class="field">
                    <label for="year">Год</label>
                    <select id="year" name="year">
                        <option value="">Все годы</option>
                        @foreach ($years as $year)
                            <option value="{{ $year }}" @selected((string) ($filters['year'] ?? '') === (string) $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="quarter">Квартал</label>
                    <select id="quarter" name="quarter">
                        <option value="">Все кварталы</option>
                        @foreach ([1, 2, 3, 4] as $quarter)
                            <option value="{{ $quarter }}" @selected((string) ($filters['quarter'] ?? '') === (string) $quarter)>Q{{ $quarter }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="book_type">Книга</label>
                    <select id="book_type" name="book_type">
                        <option value="">Покупки и продажи</option>
                        @foreach ($bookLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['book_type'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="legal_id">Юрлицо</label>
                    <select id="legal_id" name="legal_id">
                        <option value="">Все юрлица</option>
                        @foreach ($legals as $legal)
                            <option value="{{ $legal->legal_id }}" @selected((string) ($filters['legal_id'] ?? '') === (string) $legal->legal_id)>
                                {{ $legal->legal_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="contractor_inn">ИНН контрагента</label>
                    <input id="contractor_inn" name="contractor_inn" value="{{ $filters['contractor_inn'] ?? '' }}" inputmode="numeric">
                </div>

                <div class="field">
                    <label for="q">Поиск</label>
                    <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Контрагент, счет-фактура, код">
                </div>
            </div>

            <div class="form-actions">
                <a class="button secondary" href="{{ route('vat-book-entries.index') }}" wire:navigate>Сбросить</a>
                <button type="submit">Показать</button>
            </div>
        </form>
    </div>

    <div class="badges" style="margin-bottom: 16px;">
        <span class="badge">Строк: {{ number_format((int) $summary->entries_count, 0, ',', ' ') }}</span>
        <span class="badge">Сумма: {{ number_format((float) $summary->amount_total, 2, ',', ' ') }}</span>
        <span class="badge">Без НДС: {{ number_format((float) $summary->amount_without_vat, 2, ',', ' ') }}</span>
        <span class="badge">НДС: {{ number_format((float) $summary->vat_amount, 2, ',', ' ') }}</span>
    </div>

    <div class="panel">
        <table>
            <thead>
            <tr>
                <th>Период</th>
                <th>Книга</th>
                <th>Строка</th>
                <th>Юрлицо</th>
                <th>Счет-фактура</th>
                <th>Контрагент</th>
                <th>Платеж</th>
                <th class="money">Сумма</th>
                <th class="money">Без НДС</th>
                <th class="money">НДС</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ $entry->year }} Q{{ $entry->quarter }}</td>
                    <td>{{ $bookLabels[$entry->book_type] ?? $entry->book_type }}</td>
                    <td class="money">{{ number_format((int) $entry->row_number, 0, ',', ' ') }}</td>
                    <td>
                        <strong>{{ $entry->legal_name }}</strong>
                        <div class="subtle">ИНН {{ $entry->legal_inn }}</div>
                    </td>
                    <td>
                        <div>{{ $entry->invoice_number ?: '—' }}</div>
                        <div class="subtle">{{ $entry->invoice_date ? \Illuminate\Support\Carbon::parse($entry->invoice_date)->format('d.m.Y') : '' }}</div>
                        @if ($entry->correction_invoice_number)
                            <div class="subtle">Корр. {{ $entry->correction_invoice_number }}</div>
                        @endif
                    </td>
                    <td>
                        <strong>{{ $entry->contractor_name ?: '—' }}</strong>
                        <div class="subtle">
                            ИНН {{ $entry->contractor_inn ?: '—' }}
                            @if ($entry->contractor_kpp)
                                КПП {{ $entry->contractor_kpp }}
                            @endif
                        </div>
                        @if ($entry->operation_code)
                            <div class="subtle">Код {{ $entry->operation_code }}</div>
                        @endif
                    </td>
                    <td>
                        <div>{{ $entry->payment_doc_number ?: '—' }}</div>
                        <div class="subtle">{{ $entry->payment_doc_date ? \Illuminate\Support\Carbon::parse($entry->payment_doc_date)->format('d.m.Y') : '' }}</div>
                        @if ($entry->acceptance_date)
                            <div class="subtle">Принят {{ \Illuminate\Support\Carbon::parse($entry->acceptance_date)->format('d.m.Y') }}</div>
                        @endif
                    </td>
                    <td class="money">{{ $entry->amount_total !== null ? number_format((float) $entry->amount_total, 2, ',', ' ') : '—' }}</td>
                    <td class="money">{{ $entry->amount_without_vat !== null ? number_format((float) $entry->amount_without_vat, 2, ',', ' ') : '—' }}</td>
                    <td class="money">{{ $entry->vat_amount !== null ? number_format((float) $entry->vat_amount, 2, ',', ' ') : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">По этим фильтрам строк нет.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="form-actions">
        @if ($entries->onFirstPage())
            <span class="button secondary" style="opacity: .55;">Назад</span>
        @else
            <a class="button secondary" href="{{ $entries->previousPageUrl() }}" wire:navigate>Назад</a>
        @endif

        <span class="badge">Страница {{ $entries->currentPage() }} из {{ $entries->lastPage() }}</span>

        @if ($entries->hasMorePages())
            <a class="button secondary" href="{{ $entries->nextPageUrl() }}" wire:navigate>Дальше</a>
        @else
            <span class="button secondary" style="opacity: .55;">Дальше</span>
        @endif
    </div>
@endsection
