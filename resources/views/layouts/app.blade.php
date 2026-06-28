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
        ['label' => 'Касса', 'route' => 'kassa.index', 'active' => 'kassa.*', 'group' => 'Деньги'],
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
        ['label' => 'HH резюме', 'route' => 'hh-resumes.index', 'active' => 'hh-resumes.*', 'group' => 'Система'],
        ['label' => 'Пользователи', 'route' => 'users.index', 'active' => 'users.*', 'group' => 'Система'],
        ['label' => 'Права пользователей', 'route' => 'user-access.index', 'active' => 'user-access.*', 'group' => 'Система'],
        ['label' => 'Планировщик', 'route' => 'scheduler.index', 'active' => 'scheduler.*', 'group' => 'Система'],
    ];

    $navItems = array_values(array_filter(
        $navItems,
        fn (array $item): bool => match ($item['route']) {
            'legal-entities.index' => \App\Support\UserAccess::canViewModule(auth()->user(), \App\Support\UserAccess::MODULE_LEGAL_ENTITIES),
            'bank-accounts.index' => \App\Support\UserAccess::canViewBankAccounts(auth()->user()),
            'bank-transactions.index' => \App\Support\UserAccess::canViewBankTransactions(auth()->user()),
            'kassa.index' => \App\Support\UserAccess::canViewCashPage(auth()->user()),
            'documents.index' => \App\Support\UserAccess::canViewModule(auth()->user(), \App\Support\UserAccess::MODULE_DOCUMENTS),
            'counterparties.index' => \App\Support\UserAccess::canViewModule(auth()->user(), \App\Support\UserAccess::MODULE_COUNTERPARTIES),
            'money-layer.index' => \App\Support\UserAccess::canViewModule(auth()->user(), \App\Support\UserAccess::MODULE_MONEY_LAYER),
            'vat-layer.index' => \App\Support\UserAccess::canViewModule(auth()->user(), \App\Support\UserAccess::MODULE_VAT_LAYER),
            'vat-books.index' => \App\Support\UserAccess::canViewModule(auth()->user(), \App\Support\UserAccess::MODULE_VAT_BOOKS),
            'vat-book-entries.index' => \App\Support\UserAccess::canViewModule(auth()->user(), \App\Support\UserAccess::MODULE_VAT_BOOK_ENTRIES),
            'currencies.index' => \App\Support\UserAccess::canViewModule(auth()->user(), \App\Support\UserAccess::MODULE_CURRENCIES),
            'exchange-rates.index' => \App\Support\UserAccess::canViewModule(auth()->user(), \App\Support\UserAccess::MODULE_EXCHANGE_RATES),
            'document-types.index' => \App\Support\UserAccess::canViewModule(auth()->user(), \App\Support\UserAccess::MODULE_DOCUMENT_TYPES),
            'electronic-signatures.index' => \App\Support\UserAccess::canViewElectronicSignatures(auth()->user()),
            'hh-resumes.index' => auth()->user()?->isAdmin() ?? false,
            'hh-browser-captures.index' => auth()->user()?->isAdmin() ?? false,
            'users.index' => \App\Support\UserAccess::canViewUsers(auth()->user()),
            'user-access.index' => \App\Support\UserAccess::canViewUserAccess(auth()->user()),
            'scheduler.index' => \App\Support\UserAccess::canViewScheduler(auth()->user()),
            default => true,
        },
    ));

    $legalEntities = \App\Support\UserAccess::legalEntitiesQuery(request())
        ->orderBy('legal_name')
        ->get(['legal_id', 'legal_name', 'legal_inn', 'legal_color']);
    $currentLegalId = session('current_legal_id');
    $currentLegalEntity = $currentLegalId
        ? $legalEntities->firstWhere('legal_id', (string) $currentLegalId)
        : null;
    if ($currentLegalId && ! $currentLegalEntity) {
        session()->forget('current_legal_id');
        $currentLegalId = null;
    }
    $authenticatedUser = request()->attributes->get('authenticated_user') ?: auth()->user();
    $isImpersonating = (bool) request()->attributes->get('is_impersonating');
    $canViewAllGraph = \App\Support\UserAccess::canViewAllGraph(auth()->user());
@endphp

<x-ui.app-shell
    :title="$title ?? 'Бухгалтерия'"
    :title-attribute="$titleAttribute ?? null"
    :title-description="$titleDescription ?? null"
    :nav-items="$navItems"
    :current-user="auth()->user()"
    :authenticated-user="$authenticatedUser"
    :is-impersonating="$isImpersonating"
    :legal-entities="$legalEntities"
    :current-legal-entity="$currentLegalEntity"
    :can-view-all-graph="$canViewAllGraph"
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
