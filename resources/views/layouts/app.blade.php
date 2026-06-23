<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-gray-100 dark:bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Бухгалтерия' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full font-sans antialiased">
@php
    $navItems = [
        ['label' => 'Наши юридические лица', 'route' => 'legal-entities.index', 'active' => 'legal-entities.*', 'group' => 'Деньги'],
        ['label' => 'Банковские счета', 'route' => 'bank-accounts.index', 'active' => 'bank-accounts.*', 'group' => 'Деньги'],
        ['label' => 'Транзакции', 'route' => 'bank-transactions.index', 'active' => 'bank-transactions.*', 'group' => 'Деньги'],
        ['label' => 'Документы', 'route' => 'documents.index', 'active' => 'documents.*', 'group' => 'Деньги'],
        ['label' => 'Контрагенты', 'route' => 'counterparties.index', 'active' => 'counterparties.*', 'group' => 'Деньги'],
        ['label' => 'Money layer', 'route' => 'money-layer.index', 'active' => 'money-layer.*', 'group' => 'Слои'],
        ['label' => 'VAT layer', 'route' => 'vat-layer.index', 'active' => 'vat-layer.*', 'group' => 'Слои'],
        ['label' => 'Книги НДС', 'route' => 'vat-books.index', 'active' => 'vat-books.*', 'group' => 'Внешний бухгалтер'],
        ['label' => 'Содержание книг', 'route' => 'vat-book-entries.index', 'active' => 'vat-book-entries.*', 'group' => 'Внешний бухгалтер'],
        ['label' => 'Валюты', 'route' => 'currencies.index', 'active' => 'currencies.*', 'group' => 'Справочники'],
        ['label' => 'Курсы валют', 'route' => 'exchange-rates.index', 'active' => 'exchange-rates.*', 'group' => 'Справочники'],
        ['label' => 'Типы документов', 'route' => 'document-types.index', 'active' => 'document-types.*', 'group' => 'Справочники'],
        ['label' => 'Электронные подписи', 'route' => 'electronic-signatures.index', 'active' => 'electronic-signatures.*', 'group' => 'Система'],
        ['label' => 'Пользователи', 'route' => 'users.index', 'active' => 'users.*', 'group' => 'Система'],
        ['label' => 'Права пользователей', 'route' => 'user-access.index', 'active' => 'user-access.*', 'group' => 'Система'],
        ['label' => 'Планировщик', 'route' => 'scheduler.index', 'active' => 'scheduler.*', 'group' => 'Система'],
    ];

    $legalEntities = \App\Models\LegalEntity::query()
        ->orderBy('legal_name')
        ->get(['legal_id', 'legal_name', 'legal_inn', 'legal_color']);
    $currentLegalId = session('current_legal_id');
    $currentLegalEntity = $currentLegalId
        ? $legalEntities->firstWhere('legal_id', (string) $currentLegalId)
        : null;
@endphp

<x-ui.app-shell
    :title="$title ?? 'Бухгалтерия'"
    :title-attribute="$titleAttribute ?? null"
    :title-description="$titleDescription ?? null"
    :nav-items="$navItems"
    :current-user="auth()->user()"
    :legal-entities="$legalEntities"
    :current-legal-entity="$currentLegalEntity"
>
    @hasSection('page_actions')
        <x-slot:pageActions>
            @yield('page_actions')
        </x-slot:pageActions>
    @endif

    @hasSection('title_after')
        <x-slot:titleAfter>
            @yield('title_after')
        </x-slot:titleAfter>
    @endif

    @hasSection('title_meta')
        <x-slot:titleMeta>
            @yield('title_meta')
        </x-slot:titleMeta>
    @endif

    @hasSection('before_content')
        <x-slot:beforeContent>
            @yield('before_content')
        </x-slot:beforeContent>
    @endif

    @yield('content')
</x-ui.app-shell>

@livewireScripts
</body>
</html>
