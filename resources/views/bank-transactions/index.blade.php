@extends('layouts.app', ['title' => 'Банковские транзакции'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Банковские транзакции</h1>
            <div class="subtle">Операции из банка, связанные со сверкой и юридическими лицами.</div>
        </div>
        <div class="bank-transaction-actions">
            <button class="button secondary statement-import-open" type="button" data-bank-statement-import-open>
                Загрузить выписку
            </button>

            <div class="api-sync-control">
                <form class="api-sync-main-form" method="post" action="{{ route('bank-transactions.sync') }}">
                    @csrf
                    <input type="hidden" name="full" value="1">
                    <button class="api-sync-button" type="submit">
                        <span class="api-sync-dot"></span>
                        Все API-счета
                    </button>
                </form>
                <details class="api-sync-dropdown">
                    <summary class="api-sync-toggle" title="Выбрать счет">
                        <span class="api-sync-caret"></span>
                    </summary>
                    <div class="api-sync-menu">
                        @if ($apiAccounts->isNotEmpty())
                            @foreach ($apiAccounts as $account)
                                <form method="post" action="{{ route('bank-transactions.sync') }}">
                                    @csrf
                                    <input type="hidden" name="full" value="1">
                                    <input type="hidden" name="account_number" value="{{ $account->account_number }}">
                                    <button type="submit" title="Обновить счет {{ $account->account_number }}">
                                        <span class="api-sync-dot"></span>
                                        <span class="api-sync-account">
                                            <span>{{ $account->name ?: $account->account_number }}</span>
                                            <small>{{ $account->legalEntity?->legal_name ?? 'Юрлицо #' . $account->legal_id }} · {{ $account->account_number }}</small>
                                        </span>
                                    </button>
                                </form>
                            @endforeach
                        @else
                            <div class="subtle" style="padding: 8px 10px;">API-счета Тинькофф пока не найдены.</div>
                        @endif
                    </div>
                </details>
            </div>
        </div>
    </div>

    <dialog class="statement-import-dialog" data-bank-statement-import-dialog>
        <div class="statement-import-dialog-shell">
            <div class="statement-import-dialog-head">
                <div>
                    <h2>Загрузка банковской выписки</h2>
                    <div class="subtle">Файл 1CClientBankExchange будет добавлен в новые документы и Money layer.</div>
                </div>
                <button class="statement-import-close" type="button" title="Закрыть" data-bank-statement-import-close>
                    &times;
                </button>
            </div>

            @include('bank-statement-imports._form', [
                'formId' => 'bank-transactions-statement-import',
                'redirectTo' => url()->full(),
                'submitLabel' => 'Загрузить',
            ])
        </div>
    </dialog>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="errors">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="errors">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <style>
        .bank-transaction-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .statement-import-open {
            min-height: 34px;
            white-space: nowrap;
        }

        .statement-import-dialog {
            width: min(720px, calc(100vw - 32px));
            padding: 0;
            border: 0;
            border-radius: 8px;
            color: var(--text);
            box-shadow: 0 20px 48px rgba(16, 24, 40, .24);
        }

        .statement-import-dialog::backdrop {
            background: rgba(15, 23, 42, .45);
        }

        .statement-import-dialog-shell {
            background: #ffffff;
        }

        .statement-import-dialog-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 20px 20px 0;
        }

        .statement-import-dialog-head h2 {
            margin: 0 0 6px;
            font-size: 20px;
            line-height: 1.2;
        }

        .statement-import-close {
            width: 34px;
            min-height: 34px;
            padding: 0;
            border-color: var(--line);
            background: #ffffff;
            color: var(--muted);
            font-size: 22px;
            line-height: 1;
        }

        .statement-import-close:hover {
            color: var(--text);
            background: #f8fafc;
        }

        .api-sync-control {
            display: inline-flex;
            align-items: stretch;
            flex-shrink: 0;
        }

        .api-sync-dropdown {
            position: relative;
            display: inline-flex;
            align-items: stretch;
        }

        .api-sync-main-form {
            display: inline-flex;
        }

        .api-sync-button,
        .api-sync-toggle,
        .api-sync-menu button {
            min-height: 34px;
            gap: 8px;
            border-color: #b7e4c7;
            background: #f6fef9;
            color: #027a48;
            box-shadow: 0 1px 2px rgba(16, 24, 40, .05);
            transition: background .15s ease, border-color .15s ease, box-shadow .15s ease, transform .05s ease;
        }

        .api-sync-button {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .api-sync-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            padding: 8px 10px;
            border-left-color: #d1fadf;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .api-sync-button:hover,
        .api-sync-toggle:hover,
        .api-sync-menu button:hover {
            background: #ecfdf3;
            border-color: #75c69a;
            color: #02663f;
            box-shadow: 0 3px 10px rgba(2, 122, 72, .14);
            text-decoration: none;
        }

        .api-sync-button:active,
        .api-sync-toggle:active,
        .api-sync-menu button:active {
            transform: translateY(1px);
        }

        .api-sync-dropdown[open] .api-sync-toggle {
            background: #ecfdf3;
            border-color: #75c69a;
            box-shadow: 0 3px 10px rgba(2, 122, 72, .14);
        }

        .api-sync-toggle::marker,
        .api-sync-toggle::-webkit-details-marker {
            display: none;
            content: "";
        }

        .api-sync-caret {
            width: 0;
            height: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            border-top: 5px solid currentColor;
        }

        .api-sync-menu {
            position: absolute;
            z-index: 20;
            top: calc(100% + 6px);
            right: 0;
            width: min(420px, calc(100vw - 40px));
            padding: 6px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 16px 36px rgba(16, 24, 40, .16);
        }

        .api-sync-menu form {
            margin: 0;
        }

        .api-sync-menu button {
            width: 100%;
            justify-content: flex-start;
            border-color: transparent;
            background: #ffffff;
            color: var(--text);
            box-shadow: none;
        }

        .api-sync-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #12b76a;
            box-shadow: 0 0 0 3px #d1fadf;
        }

        .api-sync-account {
            display: inline-grid;
            gap: 1px;
            text-align: left;
            line-height: 1.15;
        }

        .api-sync-account small {
            color: #667085;
            font-size: 11px;
        }
    </style>

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
            const dialog = document.querySelector('[data-bank-statement-import-dialog]');
            const openButton = document.querySelector('[data-bank-statement-import-open]');
            const closeButtons = dialog?.querySelectorAll('[data-bank-statement-import-close]') ?? [];

            if (!dialog || !openButton) {
                return;
            }

            openButton.addEventListener('click', () => {
                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                    return;
                }

                dialog.setAttribute('open', 'open');
            });

            closeButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    if (typeof dialog.close === 'function') {
                        dialog.close();
                        return;
                    }

                    dialog.removeAttribute('open');
                });
            });

            dialog.addEventListener('click', (event) => {
                if (event.target === dialog && typeof dialog.close === 'function') {
                    dialog.close();
                }
            });
        })();

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
