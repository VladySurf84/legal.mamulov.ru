@props([
    'title' => 'Бухгалтерия',
    'titleAttribute' => null,
    'navItems' => [],
    'currentUser' => null,
])

@php
    $hasPageActions = isset($pageActions) && trim($pageActions->toHtml()) !== '';
    $hasBeforeContent = isset($beforeContent) && trim($beforeContent->toHtml()) !== '';
@endphp

<div>
    <nav class="bg-gray-800 dark:bg-gray-800/50">
        <div class="mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <div class="flex min-w-0 items-center">
                    <a class="flex shrink-0 items-center gap-3 text-white hover:no-underline" href="{{ route('bank-accounts.index') }}" wire:navigate>
                        <span class="inline-flex size-8 items-center justify-center rounded-md bg-indigo-500 text-sm font-bold text-white">Б</span>
                        <span class="text-base font-semibold">Бухгалтерия</span>
                    </a>

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
                        <details class="relative ml-3">
                            <summary class="relative flex max-w-xs cursor-pointer list-none items-center rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 [&::-webkit-details-marker]:hidden">
                                <span class="absolute -inset-1.5"></span>
                                <span class="sr-only">Открыть меню пользователя</span>
                                @if ($currentUser?->avatar)
                                    <img src="{{ $currentUser->avatar }}" alt="" class="size-8 rounded-full outline -outline-offset-1 outline-white/10">
                                @else
                                    <span class="inline-flex size-8 items-center justify-center rounded-full bg-gray-900 text-sm font-semibold text-white outline -outline-offset-1 outline-white/10">
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
                                        <img src="{{ $currentUser->avatar }}" alt="" class="size-10 rounded-full outline -outline-offset-1 outline-white/10">
                                    @else
                                        <span class="inline-flex size-10 items-center justify-center rounded-full bg-gray-900 text-base font-semibold text-white outline -outline-offset-1 outline-white/10">
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

    <header class="relative bg-white shadow-sm dark:bg-gray-800 dark:shadow-none dark:after:pointer-events-none dark:after:absolute dark:after:inset-x-0 dark:after:inset-y-0 dark:after:border-y dark:after:border-white/10">
        <div class="mx-auto flex flex-col gap-4 px-4 py-6 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white" @if ($titleAttribute) title="{{ $titleAttribute }}" @endif>{{ $title }}</h1>
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
