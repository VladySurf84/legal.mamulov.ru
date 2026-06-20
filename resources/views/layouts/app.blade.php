<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Бухгалтерия' }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f6f7f9;
            --panel: #ffffff;
            --text: #1f2933;
            --muted: #697386;
            --line: #d8dee8;
            --accent: #176b87;
            --accent-strong: #0f4f64;
            --danger: #a8323e;
            --success-bg: #e8f5ee;
            --success-text: #256146;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            line-height: 1.45;
        }

        a {
            color: var(--accent);
            text-decoration: none;
        }

        a:hover { text-decoration: underline; }

        .shell { min-height: 100vh; }

        .topbar {
            background: #ffffff;
            border-bottom: 1px solid var(--line);
        }

        .topbar-inner,
        .content {
            width: calc(100vw - 32px);
            margin: 0 auto;
        }

        @media (min-width: 1440px) {
            .topbar-inner,
            .content {
                width: calc(100vw - 48px);
            }
        }

        .topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 58px;
            gap: 20px;
        }

        .brand {
            font-weight: 700;
            color: var(--text);
        }

        .nav {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            align-items: center;
        }

        .nav-form { margin: 0; }

        .nav-button {
            min-height: auto;
            padding: 0;
            border: 0;
            background: transparent;
            color: var(--accent);
        }

        .nav-button:hover {
            background: transparent;
            text-decoration: underline;
        }

        .content { padding: 28px 0 44px; }

        .page-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 20px;
        }

        h1 {
            margin: 0 0 4px;
            font-size: 28px;
            font-weight: 700;
        }

        .subtle { color: var(--muted); }

        .button,
        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 8px 14px;
            border: 1px solid transparent;
            border-radius: 6px;
            background: var(--accent);
            color: #ffffff;
            font: inherit;
            cursor: pointer;
            white-space: nowrap;
        }

        .button:hover {
            background: var(--accent-strong);
            text-decoration: none;
        }

        .button.secondary {
            background: #ffffff;
            border-color: var(--line);
            color: var(--text);
        }

        .button.danger,
        button.danger {
            background: #ffffff;
            border-color: #e3b8bf;
            color: var(--danger);
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
        }

        .notice {
            margin-bottom: 16px;
            padding: 11px 14px;
            border-radius: 6px;
            background: var(--success-bg);
            color: var(--success-text);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f9fafb;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        tr:last-child td { border-bottom: 0; }

        .code {
            font-family: Consolas, monospace;
            font-size: 13px;
        }

        .money {
            font-variant-numeric: tabular-nums;
            text-align: right;
            white-space: nowrap;
        }

        .badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 3px 8px;
            border-radius: 999px;
            background: #eef2f6;
            color: #475467;
            font-size: 12px;
            white-space: nowrap;
        }

        .actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .form { padding: 20px; }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .field {
            display: grid;
            gap: 6px;
        }

        .field.full { grid-column: 1 / -1; }

        label { font-weight: 700; }

        input,
        select,
        textarea {
            width: 100%;
            min-height: 38px;
            padding: 8px 10px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #ffffff;
            color: var(--text);
            font: inherit;
        }

        textarea {
            min-height: 120px;
            font-family: Consolas, monospace;
        }

        .checks {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px 16px;
        }

        .check {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 400;
        }

        .check input {
            width: 16px;
            min-height: 16px;
        }

        .errors {
            margin-bottom: 16px;
            padding: 12px 14px;
            border: 1px solid #f0b6bd;
            border-radius: 6px;
            background: #fff4f5;
            color: #8f2633;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        @media (max-width: 760px) {
            .page-head,
            .topbar-inner {
                flex-direction: column;
                align-items: stretch;
                padding: 14px 0;
            }

            .grid,
            .checks { grid-template-columns: 1fr; }

            .panel { overflow-x: auto; }
        }
    </style>
</head>
<body>
<div class="shell">
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ route('bank-accounts.index') }}">Бухгалтерия</a>
            <nav class="nav">
                <a href="{{ route('bank-accounts.index') }}">Банковские счета</a>
                <a href="{{ route('bank-transactions.index') }}">Банковские транзакции</a>
                <a href="{{ route('ozon-bank-statements.create') }}">Импорт Ozon</a>
                <a href="{{ route('money-layer.index') }}">Money layer</a>
                <a href="{{ route('vat-books.index') }}">Книги НДС</a>
                <a href="{{ route('document-types.index') }}">Типы документов</a>
                <a href="{{ route('scheduler.index') }}">Планировщик</a>
                @if (session('admin_authenticated') === true)
                    <form class="nav-form" method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="nav-button" type="submit">Выйти</button>
                    </form>
                @endif
            </nav>
        </div>
    </header>
    <main class="content">
        @yield('content')
    </main>
</div>
</body>
</html>
