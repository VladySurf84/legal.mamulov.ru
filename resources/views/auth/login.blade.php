<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
<main class="flex min-h-full">
    <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24">
        <div class="mx-auto w-full max-w-sm lg:w-96">
            <div>
                <a href="{{ route('login') }}" class="inline-flex items-center gap-3 text-gray-900">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-indigo-600 text-sm font-semibold text-white shadow-xs">Б</span>
                    <span class="text-base font-semibold">— значит Бухгалтерия</span>
                </a>

                <h1 class="mt-8 text-2xl/9 font-bold tracking-tight text-gray-900">Вам сюда нельзя</h1>
                <p class="mt-2 text-sm/6 text-gray-500">Доступ к банку, документам, сверкам и НДС.</p>
            </div>

            <div class="mt-10">
                @if (isset($errors) && $errors->any())
                    <div class="mb-6 rounded-md bg-red-50 p-4 text-sm/6 text-red-700 ring-1 ring-red-600/20">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form action="{{ route('login.store') }}" method="POST" class="space-y-6">
                    @csrf

                    <x-ui.input
                        id="login"
                        type="email"
                        name="login"
                        label="Email"
                        :value="old('login')"
                        required
                        autocomplete="email"
                        autofocus
                    />

                    <x-ui.input
                        id="password"
                        type="password"
                        name="password"
                        label="Пароль"
                        required
                        autocomplete="current-password"
                    />

                    <div class="flex items-center justify-between">
                        <div class="flex gap-3">
                            <div class="flex h-6 shrink-0 items-center">
                                <div class="group grid size-4 grid-cols-1">
                                    <input
                                        id="remember-me"
                                        type="checkbox"
                                        name="remember"
                                        class="col-start-1 row-start-1 appearance-none rounded-sm border border-gray-300 bg-white checked:border-indigo-600 checked:bg-indigo-600 indeterminate:border-indigo-600 indeterminate:bg-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:border-gray-300 disabled:bg-gray-100 disabled:checked:bg-gray-100 forced-colors:appearance-auto"
                                    >
                                    <svg viewBox="0 0 14 14" fill="none" class="pointer-events-none col-start-1 row-start-1 size-3.5 self-center justify-self-center stroke-white group-has-disabled:stroke-gray-950/25">
                                        <path d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-0 group-has-checked:opacity-100"/>
                                        <path d="M3 7H11" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-0 group-has-indeterminate:opacity-100"/>
                                    </svg>
                                </div>
                            </div>
                            <label for="remember-me" class="block text-sm/6 text-gray-900">Запомнить меня</label>
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm/6 font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            Войти
                        </button>
                    </div>
                </form>

                <div class="mt-4">
                    <button
                        type="button"
                        id="passkey-login-button"
                        class="flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:inset-ring-transparent"
                        data-options-url="{{ route('passkeys.login.options') }}"
                        data-login-url="{{ route('passkeys.login') }}"
                        data-csrf-token="{{ csrf_token() }}"
                    >
                        Войти по отпечатку или PIN
                    </button>
                    <p id="passkey-login-status" class="mt-2 hidden text-sm/6 text-gray-500"></p>
                </div>

                @if (config('services.google.client_id') && config('services.google.client_secret'))
                    <div class="mt-10">
                        <div class="relative">
                            <div aria-hidden="true" class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-200"></div>
                            </div>
                            <div class="relative flex justify-center text-sm/6 font-medium">
                                <span class="bg-white px-6 text-gray-900">Или продолжить через</span>
                            </div>
                        </div>

                        <div class="mt-6">
                            <a
                                href="{{ route('auth.google.redirect') }}"
                                class="flex w-full items-center justify-center gap-3 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:inset-ring-transparent"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true" class="h-5 w-5">
                                    <path d="M12.0003 4.75C13.7703 4.75 15.3553 5.36002 16.6053 6.54998L20.0303 3.125C17.9502 1.19 15.2353 0 12.0003 0C7.31028 0 3.25527 2.69 1.28027 6.60998L5.27028 9.70498C6.21525 6.86002 8.87028 4.75 12.0003 4.75Z" fill="#EA4335"/>
                                    <path d="M23.49 12.275C23.49 11.49 23.415 10.73 23.3 10H12V14.51H18.47C18.18 15.99 17.34 17.25 16.08 18.1L19.945 21.1C22.2 19.01 23.49 15.92 23.49 12.275Z" fill="#4285F4"/>
                                    <path d="M5.26498 14.2949C5.02498 13.5699 4.88501 12.7999 4.88501 11.9999C4.88501 11.1999 5.01998 10.4299 5.26498 9.7049L1.275 6.60986C0.46 8.22986 0 10.0599 0 11.9999C0 13.9399 0.46 15.7699 1.28 17.3899L5.26498 14.2949Z" fill="#FBBC05"/>
                                    <path d="M12.0004 24.0001C15.2404 24.0001 17.9654 22.935 19.9454 21.095L16.0804 18.095C15.0054 18.82 13.6204 19.245 12.0004 19.245C8.8704 19.245 6.21537 17.135 5.2654 14.29L1.27539 17.385C3.25539 21.31 7.3104 24.0001 12.0004 24.0001Z" fill="#34A853"/>
                                </svg>
                                <span class="text-sm/6 font-semibold">Google</span>
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="relative hidden w-0 flex-1 lg:block">
        <img
            src="{{ asset('images/login-painting.png') }}"
            alt=""
            class="absolute inset-0 size-full object-cover"
        >
    </div>
</main>
<script>
    (() => {
        const button = document.getElementById('passkey-login-button');
        const status = document.getElementById('passkey-login-status');
        const loginInput = document.getElementById('login');

        if (!button || !status || !loginInput) {
            return;
        }

        const setStatus = (message, isError = false) => {
            status.textContent = message;
            status.classList.remove('hidden', 'text-gray-500', 'text-red-600');
            status.classList.add(isError ? 'text-red-600' : 'text-gray-500');
        };

        const arrayBufferToBase64 = (buffer) => {
            let binary = '';
            const bytes = new Uint8Array(buffer);
            for (let index = 0; index < bytes.byteLength; index += 1) {
                binary += String.fromCharCode(bytes[index]);
            }

            return window.btoa(binary);
        };

        const convertBinaryFields = (value) => {
            const prefix = '=?BINARY?B?';
            const suffix = '?=';

            if (!value || typeof value !== 'object') {
                return;
            }

            Object.keys(value).forEach((key) => {
                const item = value[key];

                if (typeof item === 'string' && item.startsWith(prefix) && item.endsWith(suffix)) {
                    const raw = window.atob(item.slice(prefix.length, -suffix.length));
                    const bytes = new Uint8Array(raw.length);
                    for (let index = 0; index < raw.length; index += 1) {
                        bytes[index] = raw.charCodeAt(index);
                    }
                    value[key] = bytes.buffer;
                } else if (item && typeof item === 'object') {
                    convertBinaryFields(item);
                }
            });
        };

        const postJson = async (url, payload) => {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': button.dataset.csrfToken,
                },
                body: JSON.stringify(payload),
            });

            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(body.message || 'Не удалось выполнить вход по ключу.');
            }

            return body;
        };

        button.addEventListener('click', async () => {
            try {
                if (!window.PublicKeyCredential || !navigator.credentials) {
                    throw new Error('Браузер не поддерживает вход по ключу.');
                }

                button.disabled = true;
                setStatus('Откроется системное окно проверки...');

                const login = loginInput.value.trim();
                const options = await postJson(button.dataset.optionsUrl, login ? {login} : {});
                convertBinaryFields(options);

                const credential = await navigator.credentials.get(options);
                const result = await postJson(button.dataset.loginUrl, {
                    id: credential.rawId ? arrayBufferToBase64(credential.rawId) : null,
                    clientDataJSON: credential.response.clientDataJSON ? arrayBufferToBase64(credential.response.clientDataJSON) : null,
                    authenticatorData: credential.response.authenticatorData ? arrayBufferToBase64(credential.response.authenticatorData) : null,
                    signature: credential.response.signature ? arrayBufferToBase64(credential.response.signature) : null,
                });

                window.location.href = result.redirect || '{{ route('bank-accounts.index') }}';
            } catch (error) {
                setStatus(error.message || 'Не удалось войти по ключу.', true);
                button.disabled = false;
            }
        });
    })();
</script>
</body>
</html>
