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

<x-ui.app-shell
    :title="$title ?? 'Бухгалтерия'"
    :title-attribute="$titleAttribute ?? null"
    :nav-items="$navItems"
    :current-user="auth()->user()"
>
    @hasSection('page_actions')
        <x-slot:pageActions>
            @yield('page_actions')
        </x-slot:pageActions>
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
