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
            <tbody id="bank-transactions-rows">
            @if (count($transactions) > 0)
                @include('bank-transactions.partials.rows', ['transactions' => $transactions])
            @else
                <tr>
                    <td colspan="7">Банковские транзакции пока не загружены.</td>
                </tr>
            @endif
            </tbody>
        </table>
    </div>

    <div
        id="bank-transactions-loader"
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
            const rows = document.getElementById('bank-transactions-rows');
            const loader = document.getElementById('bank-transactions-loader');

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
