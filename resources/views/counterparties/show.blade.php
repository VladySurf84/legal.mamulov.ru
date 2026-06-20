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
        <span class="badge">Наше сальдо: {{ number_format($summary['saldo'], 2, ',', ' ') }}</span>
        <span class="badge">Книги покупок: {{ number_format($summary['buh_saldo'], 2, ',', ' ') }}</span>
        <span class="badge">Разница: {{ number_format($summary['saldo_diff'], 2, ',', ' ') }}</span>
        <span class="badge">Приход: {{ number_format($summary['income_amount'], 2, ',', ' ') }}</span>
        <span class="badge">Расход: {{ number_format($summary['expense_amount'], 2, ',', ' ') }}</span>
    </div>

    <div class="page-head" style="margin-top: 24px;">
        <div>
            <h1 style="font-size: 22px;">Книга покупок</h1>
            <div class="subtle">Строки активных книг покупок по этому ИНН. Сумма показана со знаком расхода.</div>
        </div>
    </div>

    <div class="panel">
        <table>
            <thead>
            <tr>
                <th>Период</th>
                <th>Наше юрлицо</th>
                <th>Строка</th>
                <th>Счет-фактура</th>
                <th>Принят / платеж</th>
                <th class="money">Сумма</th>
                <th class="money">Без НДС</th>
                <th class="money">НДС</th>
                <th class="money">В сальдо</th>
                <th class="money">Итог</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($purchaseEntries as $entry)
                <tr>
                    <td>{{ $entry->year }} Q{{ $entry->quarter }}</td>
                    <td>{{ $entry->legal_name ?? '—' }}</td>
                    <td class="money">{{ number_format((int) $entry->row_number, 0, ',', ' ') }}</td>
                    <td>
                        <div>{{ $entry->invoice_number ?: '—' }}</div>
                        <div class="subtle">{{ $entry->invoice_date ? \Illuminate\Support\Carbon::parse($entry->invoice_date)->format('d.m.Y') : '' }}</div>
                        @if ($entry->operation_code)
                            <div class="subtle">Код {{ $entry->operation_code }}</div>
                        @endif
                    </td>
                    <td>
                        @if ($entry->acceptance_date)
                            <div>Принят {{ \Illuminate\Support\Carbon::parse($entry->acceptance_date)->format('d.m.Y') }}</div>
                        @else
                            <div>—</div>
                        @endif
                        @if ($entry->payment_doc_number || $entry->payment_doc_date)
                            <div class="subtle">
                                {{ $entry->payment_doc_number ?: 'платеж' }}
                                {{ $entry->payment_doc_date ? \Illuminate\Support\Carbon::parse($entry->payment_doc_date)->format('d.m.Y') : '' }}
                            </div>
                        @endif
                    </td>
                    <td class="money">{{ $entry->amount_total !== null ? number_format((float) $entry->amount_total, 2, ',', ' ') : '—' }}</td>
                    <td class="money">{{ $entry->amount_without_vat !== null ? number_format((float) $entry->amount_without_vat, 2, ',', ' ') : '—' }}</td>
                    <td class="money">{{ $entry->vat_amount !== null ? number_format((float) $entry->vat_amount, 2, ',', ' ') : '—' }}</td>
                    <td class="money">{{ number_format((float) $entry->signed_amount, 2, ',', ' ') }}</td>
                    <td class="money">{{ number_format((float) $entry->running_saldo, 2, ',', ' ') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">В активных книгах покупок строк по этому контрагенту нет.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="page-head" style="margin-top: 24px;">
        <div>
            <h1 style="font-size: 22px;">Банк</h1>
            <div class="subtle">Новые банковские документы по этому ИНН.</div>
        </div>
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
                <th class="money">Итог</th>
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
                    <td class="money">{{ number_format((float) $operation->running_saldo, 2, ',', ' ') }}</td>
                    <td>
                        <div class="code">{{ $operation->account_number }}</div>
                        <div class="subtle code">{{ $operation->external_operation_id }}</div>
                        <div class="subtle">Документ #{{ $operation->document_id }}</div>
                    </td>
                    <td>{{ $operation->payment_purpose ?: $operation->document_title }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">По этому контрагенту операций нет.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
