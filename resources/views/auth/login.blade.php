<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-slate-950 antialiased">
<main class="grid min-h-screen lg:grid-cols-2">
    <section class="flex min-h-screen items-center justify-center px-6 py-10 sm:px-10">
        <div class="w-full max-w-sm">
            <a class="mb-10 inline-flex items-center gap-3 text-slate-950" href="{{ route('login') }}">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-slate-950 text-sm font-semibold text-white">Б</span>
                <span class="text-base font-semibold">— значит Бухгалтерия</span>
            </a>

            <div class="mb-8">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Вам сюда нельзя</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">Доступ к банку, документам, сверкам и НДС.</p>
            </div>

            @if (isset($errors) && $errors->any())
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="post" action="{{ route('login.store') }}" class="grid gap-5">
                @csrf

                <label class="grid gap-2 text-sm font-medium text-slate-700" for="login">
                    Email
                    <input
                        class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-950 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-slate-950 focus:ring-4 focus:ring-slate-950/10"
                        id="login"
                        name="login"
                        value="{{ old('login') }}"
                        autocomplete="email"
                        inputmode="email"
                        autofocus
                    >
                </label>

                <label class="grid gap-2 text-sm font-medium text-slate-700" for="password">
                    Пароль
                    <input
                        class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-950 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-slate-950 focus:ring-4 focus:ring-slate-950/10"
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                    >
                </label>

                <button
                    class="mt-1 flex h-10 w-full items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-950/15"
                    type="submit"
                >
                    Войти
                </button>
            </form>

            @if (config('services.google.client_id') && config('services.google.client_secret'))
                <div class="my-6 flex items-center gap-4">
                    <div class="h-px flex-1 bg-slate-200"></div>
                    <span class="text-xs font-medium uppercase text-slate-400">или</span>
                    <div class="h-px flex-1 bg-slate-200"></div>
                </div>

                <a
                    class="flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-900 shadow-sm transition hover:bg-slate-50"
                    href="{{ route('auth.google.redirect') }}"
                >
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.1c-.22-.66-.35-1.36-.35-2.1s.13-1.44.35-2.1V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l3.66-2.84z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06L5.84 9.9C6.71 7.3 9.14 5.38 12 5.38z"/>
                    </svg>
                    Войти через Google
                </a>
            @endif
        </div>
    </section>

    <section class="relative hidden min-h-screen overflow-hidden bg-[#f6d34f] lg:block">
        <img
            class="h-screen w-full object-cover"
            src="{{ asset('images/login-painting.png') }}"
            alt="Картина"
        >
    </section>
</main>
</body>
</html>
