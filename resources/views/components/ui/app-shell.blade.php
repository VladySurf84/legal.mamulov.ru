@props([
    'title' => 'Бухгалтерия',
    'titleAttribute' => null,
    'titleDescription' => null,
    'navItems' => [],
    'currentUser' => null,
    'legalEntities' => collect(),
    'currentLegalEntity' => null,
])

@php
    $hasPageActions = isset($pageActions) && trim($pageActions->toHtml()) !== '';
    $hasTitleAfter = isset($titleAfter) && trim($titleAfter->toHtml()) !== '';
    $hasTitleMeta = isset($titleMeta) && trim($titleMeta->toHtml()) !== '';
    $hasBeforeContent = isset($beforeContent) && trim($beforeContent->toHtml()) !== '';
    $allGraphValue = '__all__';
    $brandLabel = $currentLegalEntity?->legal_name ?: 'Глобальный';
    $brandLetter = mb_strtoupper(mb_substr($currentLegalEntity?->legal_letter ?: $brandLabel, 0, 1));
    $brandColor = $currentLegalEntity?->legal_color ?: '#6b7280';
    $legalEntityOptions = collect($legalEntities)->map(fn ($legalEntity) => [
        'value' => (string) $legalEntity->legal_id,
        'label' => $legalEntity->legal_name,
        'secondary' => 'ИНН ' . ($legalEntity->legal_inn ?: $legalEntity->legal_id),
        'swatch' => $legalEntity->legal_color ?: '#e5e7eb',
    ])->values()->push([
        'value' => $allGraphValue,
        'label' => 'Глобальный',
        'secondary' => 'Все юрлица и все контрагенты',
        'icon' => 'globe',
    ]);
    $currentLegalContextValue = $currentLegalEntity?->legal_id ?: $allGraphValue;
@endphp

<div>
    <nav class="bg-gray-800 dark:bg-gray-800/50">
        <div class="mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <div class="flex min-w-0 items-center">
                    <button
                        type="button"
                        class="flex shrink-0 items-center gap-3 rounded-md text-white hover:no-underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                        data-ui-drawer-open="app-navigation-drawer"
                    >
                        <span
                            class="inline-flex size-8 shrink-0 items-center justify-center rounded-md text-sm font-bold text-white"
                            style="background-color: {{ $brandColor ?: '#6366f1' }}"
                        >{{ $brandLetter }}</span>
                        <span class="max-w-56 truncate text-base font-semibold lg:max-w-72">{{ $brandLabel }}</span>
                    </button>

                    <div class="hidden md:block" style="overflow-x: hidden">
                        <div class="ml-10 flex items-baseline space-x-4">
                            @foreach ($navItems as $item)
                                @php($isActive = request()->routeIs($item['active']))
                                <a
                                    class="{{ $isActive ? 'bg-gray-900 text-white dark:bg-gray-950/50' : 'text-gray-300 hover:bg-white/5 hover:text-white' }} rounded-md px-3 py-2 text-sm font-medium"
                                    href="{{ route($item['route']) }}"
                                    @if ($isActive) aria-current="page" @endif
                                    wire:navigate
                                >
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="hidden md:block">
                    <div class="ml-4 flex items-center md:ml-6">
                        <button
                            type="button"
                            class="relative rounded-full p-1 text-gray-400 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                            title="Уведомления"
                        >
                            <span class="absolute -inset-1.5"></span>
                            <span class="sr-only">Уведомления</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-6">
                                <path d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022 23.848 23.848 0 0 0 5.455 1.31m5.714 0a3 3 0 0 1-5.714 0" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                        <details class="relative ml-3">
                            <summary class="relative flex max-w-xs cursor-pointer list-none items-center rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 [&::-webkit-details-marker]:hidden">
                                <span class="absolute -inset-1.5"></span>
                                <span class="sr-only">Открыть меню пользователя</span>
                                @if ($currentUser?->avatar)
                                    <img src="{{ $currentUser->avatar }}" alt="" class="size-8 shrink-0 rounded-full object-cover outline -outline-offset-1 outline-white/10">
                                @else
                                    <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-gray-900 text-sm font-semibold text-white outline -outline-offset-1 outline-white/10">
                                        {{ mb_strtoupper(mb_substr($currentUser?->name ?: $currentUser?->email ?: 'U', 0, 1)) }}
                                    </span>
                                @endif
                            </summary>

                            <div class="absolute right-0 z-40 mt-2 w-56 origin-top-right rounded-md bg-white py-1 shadow-lg outline-1 outline-black/5 dark:bg-gray-800 dark:shadow-none dark:-outline-offset-1 dark:outline-white/10">
                                <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10">
                                    <div class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ $currentUser?->name ?: 'Пользователь' }}</div>
                                    <div class="truncate text-sm text-gray-500 dark:text-gray-400">{{ $currentUser?->email }}</div>
                                </div>
                                <form class="m-0" method="post" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="block w-full !min-h-0 !justify-start rounded-none !border-0 !bg-white px-4 py-2 text-left text-sm !font-normal !text-gray-700 shadow-none hover:!bg-gray-100 dark:!bg-gray-800 dark:!text-gray-300 dark:hover:!bg-white/5" type="submit">
                                        Выйти
                                    </button>
                                </form>
                            </div>
                        </details>
                    </div>
                </div>

                <details class="-mr-2 flex md:hidden">
                    <summary class="relative inline-flex cursor-pointer list-none items-center justify-center rounded-md p-2 text-gray-400 hover:bg-white/5 hover:text-white focus:outline-2 focus:outline-offset-2 focus:outline-indigo-500 [&::-webkit-details-marker]:hidden">
                        <span class="absolute -inset-0.5"></span>
                        <span class="sr-only">Открыть меню</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-6">
                            <path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </summary>

                    <div class="absolute inset-x-0 top-16 z-40 bg-gray-800 shadow-xl md:hidden">
                        <div class="space-y-1 px-2 pt-2 pb-3 sm:px-3">
                            @foreach ($navItems as $item)
                                @php($isActive = request()->routeIs($item['active']))
                                <a
                                    class="{{ $isActive ? 'bg-gray-900 text-white dark:bg-gray-950/50' : 'text-gray-300 hover:bg-white/5 hover:text-white' }} block rounded-md px-3 py-2 text-base font-medium"
                                    href="{{ route($item['route']) }}"
                                    @if ($isActive) aria-current="page" @endif
                                    wire:navigate
                                >
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>

                        <div class="border-t border-white/10 pt-4 pb-3">
                            <div class="flex items-center px-5">
                                <div class="shrink-0">
                                    @if ($currentUser?->avatar)
                                        <img src="{{ $currentUser->avatar }}" alt="" class="size-10 shrink-0 rounded-full object-cover outline -outline-offset-1 outline-white/10">
                                    @else
                                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-gray-900 text-base font-semibold text-white outline -outline-offset-1 outline-white/10">
                                            {{ mb_strtoupper(mb_substr($currentUser?->name ?: $currentUser?->email ?: 'U', 0, 1)) }}
                                        </span>
                                    @endif
                                </div>
                                <div class="ml-3 min-w-0">
                                    <div class="truncate text-base/5 font-medium text-white">{{ $currentUser?->name ?: 'Пользователь' }}</div>
                                    <div class="truncate text-sm font-medium text-gray-400">{{ $currentUser?->email }}</div>
                                </div>
                            </div>
                            <div class="mt-3 space-y-1 px-2">
                                <form method="post" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="block w-full !min-h-0 !justify-start rounded-md !border-0 !bg-transparent px-3 py-2 text-left text-base font-medium !text-gray-400 shadow-none hover:!bg-white/5 hover:!text-white" type="submit">
                                        Выйти
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </details>
            </div>
        </div>
    </nav>

    <x-ui.drawer id="app-navigation-drawer" side="left" size="auto">
        <x-slot:header>
            <div class="min-w-72">
                <form method="post" action="{{ route('legal-entity-context.update') }}">
                    @csrf
                    <x-ui.select-with-secondary-text
                        id="app-current-legal-id"
                        name="legal_id"
                        :value="(string) $currentLegalContextValue"
                        :options="$legalEntityOptions"
                        selected-layout="stacked"
                        submit-on-change
                    />
                </form>
            </div>
        </x-slot:header>

        <nav class="space-y-7">
            @foreach (collect($navItems)->groupBy(fn ($item) => $item['group'] ?? 'Разделы') as $groupLabel => $groupItems)
                <div>
                    <div class="px-3 text-xs font-medium text-gray-400 dark:text-gray-500">
                        {{ $groupLabel }}
                    </div>

                    <div class="mt-2 space-y-1">
                        @foreach ($groupItems as $item)
                            @php($isActive = request()->routeIs($item['active']))
                            <a
                                class="{{ $isActive ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white' }} block rounded-md px-3 py-2 text-sm font-medium"
                                href="{{ route($item['route']) }}"
                                @if ($isActive) aria-current="page" @endif
                                wire:navigate
                            >
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="mt-8 border-t border-gray-200 pt-6 dark:border-white/10">
            <div class="flex items-center gap-3">
                @if ($currentUser?->avatar)
                    <img src="{{ $currentUser->avatar }}" alt="" class="size-10 shrink-0 rounded-full object-cover outline -outline-offset-1 outline-gray-200 dark:outline-white/10">
                @else
                    <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-gray-900 text-base font-semibold text-white">
                        {{ mb_strtoupper(mb_substr($currentUser?->name ?: $currentUser?->email ?: 'U', 0, 1)) }}
                    </span>
                @endif

                <div class="min-w-0">
                    <div class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ $currentUser?->name ?: 'Пользователь' }}</div>
                    <div class="truncate text-sm text-gray-500 dark:text-gray-400">{{ $currentUser?->email }}</div>
                </div>
            </div>

            <form class="mt-4" method="post" action="{{ route('logout') }}">
                @csrf
                <button class="block w-full rounded-md px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white" type="submit">
                    Выйти
                </button>
            </form>
        </div>
    </x-ui.drawer>

    <header class="relative bg-white shadow-sm dark:bg-gray-800 dark:shadow-none dark:after:pointer-events-none dark:after:absolute dark:after:inset-x-0 dark:after:inset-y-0 dark:after:border-y dark:after:border-white/10">
        <div class="mx-auto flex flex-col gap-4 px-4 py-6 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white" @if ($titleAttribute) title="{{ $titleAttribute }}" @endif>{{ $title }}</h1>
                    @if ($hasTitleAfter)
                        {{ $titleAfter }}
                    @endif
                </div>
                @if ($hasTitleMeta)
                    <div class="mt-2">
                        {{ $titleMeta }}
                    </div>
                @endif
                @if ($titleDescription)
                    <p class="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-300">{{ $titleDescription }}</p>
                @endif
            </div>
            @if ($hasPageActions)
                <div class="flex flex-wrap items-center gap-2">
                    {{ $pageActions }}
                </div>
            @endif
        </div>
    </header>

    <main>
        <div class="mx-auto px-4 py-6 sm:px-6 lg:px-8 pb-0">
            @if ($hasBeforeContent)
                {{ $beforeContent }}
            @endif

            {{ $slot }}
        </div>
    </main>
</div>
