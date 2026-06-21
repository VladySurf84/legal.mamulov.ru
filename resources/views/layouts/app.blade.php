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
            --panel-soft: #f9fafb;
            --text: #1f2933;
            --muted: #697386;
            --line: #d8dee8;
            --accent: #176b87;
            --accent-strong: #0f4f64;
            --accent-soft: #eef8fb;
            --danger: #a8323e;
            --success-bg: #e8f5ee;
            --success-text: #256146;
            --shadow: 0 1px 2px rgba(16, 24, 40, .05);
            --radius: 8px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: var(--font-sans, "Inter Variable", ui-sans-serif, system-ui, sans-serif);
            font-size: 14px;
            line-height: 1.45;
        }

        a {
            color: var(--accent);
            text-decoration: none;
        }

        a:hover { text-decoration: underline; }

        .app-shell a,
        .app-shell a:hover {
            text-decoration: none;
        }

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
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
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
            background: var(--panel-soft);
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        tr:last-child td { border-bottom: 0; }

        tr.linked-ledger-row td {
            background: #ecfdf3;
            border-bottom-color: #b7e4c7;
        }

        .linked-ledger-badge {
            margin-left: 6px;
            background: #d1fadf;
            color: #027a48;
        }

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
            border-radius: var(--radius);
            background: var(--panel);
            color: var(--text);
            font: inherit;
        }

        textarea {
            min-height: 120px;
            font-family: Consolas, monospace;
        }

        .checkline {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 38px;
            font-weight: 700;
        }

        .checkline input[type="checkbox"] {
            width: auto;
            min-height: 0;
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
            .page-head {
                flex-direction: column;
                align-items: stretch;
                padding: 14px 0;
            }

            .grid,
            .checks { grid-template-columns: 1fr; }

            .panel { overflow-x: auto; }
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased">
<div class="app-shell min-h-full bg-slate-50">
    @php
        $navItems = [
            ['label' => 'Банковские счета', 'route' => 'bank-accounts.index', 'active' => 'bank-accounts.*'],
            ['label' => 'Транзакции', 'route' => 'bank-transactions.index', 'active' => 'bank-transactions.*'],
            ['label' => 'Контрагенты', 'route' => 'counterparties.index', 'active' => 'counterparties.*'],
            ['label' => 'Money layer', 'route' => 'money-layer.index', 'active' => 'money-layer.*'],
            ['label' => 'Книги НДС', 'route' => 'vat-books.index', 'active' => 'vat-books.*'],
            ['label' => 'Содержание книг', 'route' => 'vat-book-entries.index', 'active' => 'vat-book-entries.*'],
            ['label' => 'VAT layer', 'route' => 'vat-layer.index', 'active' => 'vat-layer.*'],
            ['label' => 'Типы документов', 'route' => 'document-types.index', 'active' => 'document-types.*'],
            ['label' => 'Планировщик', 'route' => 'scheduler.index', 'active' => 'scheduler.*'],
        ];
    @endphp

    <header class="relative z-30">
        <nav class="bg-slate-800">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-8">
                        <a class="flex shrink-0 items-center gap-3 text-white hover:no-underline" href="{{ route('bank-accounts.index') }}" wire:navigate>
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-cyan-500 text-sm font-bold text-white">Б</span>
                            <span class="text-base font-semibold">Бухгалтерия</span>
                        </a>

                        <nav class="hidden items-center gap-1 lg:flex" aria-label="Основная навигация">
                            @foreach ($navItems as $item)
                                @php($isActive = request()->routeIs($item['active']))
                                <a
                                    class="{{ $isActive ? 'bg-slate-900 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }} rounded-md px-3 py-2 text-sm font-medium hover:no-underline"
                                    href="{{ route($item['route']) }}"
                                    wire:navigate
                                >
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </nav>
                    </div>

                    <div class="hidden shrink-0 items-center gap-3 lg:flex">
                        @if (session('admin_authenticated') === true)
                            <form class="m-0" method="post" action="{{ route('logout') }}">
                                @csrf
                                <button class="!h-9 !min-h-0 rounded-md border border-slate-600 !bg-slate-900 px-3 text-sm font-medium !text-slate-100 shadow-sm hover:!bg-slate-700" type="submit">Выйти</button>
                            </form>
                        @endif
                    </div>

                    <details class="relative lg:hidden">
                        <summary class="flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-md border border-slate-600 bg-slate-900 text-slate-100 shadow-sm hover:bg-slate-700 [&::-webkit-details-marker]:hidden" title="Меню">
                            <span class="sr-only">Открыть меню</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M2 5.5A.75.75 0 0 1 2.75 4.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 5.5Zm0 4.5a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 10Zm0 4.5a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 14.5Z" clip-rule="evenodd" />
                            </svg>
                        </summary>

                        <div class="absolute right-0 z-40 mt-2 w-[min(340px,calc(100vw-32px))] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl">
                            <nav class="grid p-2" aria-label="Мобильная навигация">
                                @foreach ($navItems as $item)
                                    @php($isActive = request()->routeIs($item['active']))
                                    <a
                                        class="{{ $isActive ? 'bg-slate-100 text-slate-950' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950' }} rounded-md px-3 py-2 text-sm font-medium hover:no-underline"
                                        href="{{ route($item['route']) }}"
                                        wire:navigate
                                    >
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            </nav>

                            <div class="border-t border-slate-200 p-2">
                                @if (session('admin_authenticated') === true)
                                    <form method="post" action="{{ route('logout') }}">
                                        @csrf
                                        <button class="!h-9 w-full !min-h-0 rounded-md border border-slate-300 !bg-white px-3 text-sm font-medium !text-slate-700 shadow-sm hover:!bg-slate-50" type="submit">Выйти</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </details>
                </div>
            </div>
        </nav>

        <div class="bg-white shadow-sm">
            <div class="px-4 py-5 sm:px-6 lg:px-8">
                <h1 class="!m-0 !text-2xl !font-semibold !tracking-normal text-slate-950">{{ $title ?? 'Бухгалтерия' }}</h1>
            </div>
        </div>
    </header>

    <main class="px-4 py-6 sm:px-6 lg:px-8">
        @yield('content')
    </main>
</div>
@livewireScripts
</body>
</html>
