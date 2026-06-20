@extends('layouts.app', ['title' => 'Детализация контрагента'])

@section('content')
    @php
        $showLegalEntityColumn = empty($filters['legal_id']);
        $emptyColspan = $showLegalEntityColumn ? 10 : 9;
    @endphp

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
                @if ($showLegalEntityColumn)
                    <th>Наше юрлицо</th>
                @endif
                <th>Документ</th>
                <th class="money">Сумма</th>
                <th class="money">В итог</th>
                <th class="money">Итог</th>
                <th class="money">НДС</th>
                <th class="money">НДС итог</th>
                <th>Описание</th>
            </tr>
            </thead>
            <tbody id="counterparty-ledger-rows">
            @if (count($ledgerEntries) > 0)
                @include('counterparties.partials.ledger-rows', [
                    'contractorInn' => $contractorInn,
                    'filters' => $filters,
                    'ledgerEntries' => $ledgerEntries,
                    'ledgerPagination' => $ledgerPagination,
                ])
            @else
                <tr>
                    <td colspan="{{ $emptyColspan }}">По этому контрагенту нет строк для сверки.</td>
                </tr>
            @endif
            </tbody>
        </table>
    </div>

    <div
        id="counterparty-ledger-loader"
        class="subtle"
        data-next-page="{{ $nextPage }}"
        style="padding: 16px 0; text-align: center;"
    >
        @if ($nextPage)
            Загрузка при прокрутке...
        @endif
    </div>

    <script>
        (() => {
            const rows = document.getElementById('counterparty-ledger-rows');
            const loader = document.getElementById('counterparty-ledger-loader');

            if (!rows || !loader || !loader.dataset.nextPage) {
                return;
            }

            let loading = false;

            const loadNextPage = async () => {
                if (loading || !loader.dataset.nextPage) {
                    return;
                }

                loading = true;
                loader.textContent = 'Загружаем...';

                const url = new URL(window.location.href);
                url.searchParams.set('page', loader.dataset.nextPage);

                try {
                    const response = await fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Request failed');
                    }

                    const payload = await response.json();
                    rows.insertAdjacentHTML('beforeend', payload.html);

                    if (payload.has_more && payload.next_page) {
                        loader.dataset.nextPage = payload.next_page;
                        loader.textContent = 'Загрузка при прокрутке...';
                    } else {
                        delete loader.dataset.nextPage;
                        loader.textContent = '';
                        observer.disconnect();
                    }
                } catch (error) {
                    loader.textContent = 'Не удалось загрузить следующую страницу.';
                } finally {
                    loading = false;
                }
            };

            const observer = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    loadNextPage();
                }
            }, { rootMargin: '600px 0px' });

            observer.observe(loader);
        })();
    </script>
@endsection
