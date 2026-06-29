@extends('layouts.app', [
    'title' => 'Ключи входа',
    'titleDescription' => 'Вход без пароля через Windows Hello, Touch ID, отпечаток пальца, PIN или аппаратный ключ.',
])

@section('content')
    <div class="max-w-4xl space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 text-sm/6 text-green-700 ring-1 ring-green-600/20">
                {{ session('status') }}
            </div>
        @endif

        <section class="rounded-md bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-white/10">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Добавить ключ входа</h2>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                <label class="sr-only" for="passkey-name">Название ключа</label>
                <input
                    id="passkey-name"
                    type="text"
                    class="block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10"
                    placeholder="Например: ноутбук Windows"
                    autocomplete="off"
                >
                <button
                    type="button"
                    id="passkey-register-button"
                    class="inline-flex shrink-0 items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                    data-options-url="{{ route('passkeys.register.options') }}"
                    data-register-url="{{ route('passkeys.register') }}"
                    data-csrf-token="{{ csrf_token() }}"
                >
                    Добавить
                </button>
            </div>
            <p id="passkey-register-status" class="mt-3 hidden text-sm/6 text-gray-500"></p>
        </section>

        <section class="overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-white/10">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Привязанные ключи</h2>
            </div>

            @if ($passkeys->isEmpty())
                <div class="px-6 py-8 text-sm text-gray-500 dark:text-gray-400">
                    Пока нет ни одного ключа входа.
                </div>
            @else
                <div class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($passkeys as $passkey)
                        <div class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ $passkey->name }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Добавлен {{ $passkey->created_at?->format('d.m.Y H:i') }}
                                    @if ($passkey->last_used_at)
                                        · последний вход {{ $passkey->last_used_at->format('d.m.Y H:i') }}
                                    @endif
                                </div>
                            </div>
                            <form method="post" action="{{ route('passkeys.destroy', $passkey) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-red-700 shadow-xs ring-1 ring-red-200 hover:bg-red-50 dark:bg-white/5 dark:text-red-300 dark:ring-red-500/30 dark:hover:bg-red-500/10">
                                    Удалить
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <script>
        (() => {
            const button = document.getElementById('passkey-register-button');
            const nameInput = document.getElementById('passkey-name');
            const status = document.getElementById('passkey-register-status');

            if (!button || !nameInput || !status) {
                return;
            }

            const setStatus = (message, isError = false) => {
                status.textContent = message;
                status.classList.remove('hidden', 'text-gray-500', 'text-red-600', 'text-green-700');
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

            const postJson = async (url, payload = {}) => {
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
                    throw new Error(body.message || 'Не удалось добавить ключ входа.');
                }

                return body;
            };

            button.addEventListener('click', async () => {
                try {
                    if (!window.PublicKeyCredential || !navigator.credentials) {
                        throw new Error('Браузер не поддерживает ключи входа.');
                    }

                    button.disabled = true;
                    setStatus('Откроется системное окно проверки...');

                    const options = await postJson(button.dataset.optionsUrl);
                    convertBinaryFields(options);

                    const credential = await navigator.credentials.create(options);
                    await postJson(button.dataset.registerUrl, {
                        name: nameInput.value.trim(),
                        transports: credential.response.getTransports ? credential.response.getTransports() : [],
                        clientDataJSON: credential.response.clientDataJSON ? arrayBufferToBase64(credential.response.clientDataJSON) : null,
                        attestationObject: credential.response.attestationObject ? arrayBufferToBase64(credential.response.attestationObject) : null,
                    });

                    window.location.reload();
                } catch (error) {
                    setStatus(error.message || 'Не удалось добавить ключ входа.', true);
                    button.disabled = false;
                }
            });
        })();
    </script>
@endsection
