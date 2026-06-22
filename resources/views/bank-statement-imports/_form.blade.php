@php
    $formId = $formId ?? 'bank-statement-import';
    $redirectTo = $redirectTo ?? null;
    $submitLabel = $submitLabel ?? 'Загрузить файл';
@endphp

<form id="{{ $formId }}" method="post" action="{{ route('bank-statement-imports.store') }}" enctype="multipart/form-data">
    @csrf

    @if ($redirectTo)
        <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">
    @endif

    <div class="space-y-5 px-6 py-5">
        <div>
            <label for="{{ $formId }}-statement-file" class="block text-sm/6 font-medium text-gray-900 dark:text-white">
                Файл выписки
            </label>
            <input
                id="{{ $formId }}-statement-file"
                name="statement_file"
                type="file"
                accept=".txt,.1c,.kl_to_1c,.dat"
                required
                class="mt-2 block w-full rounded-md bg-white text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 file:mr-4 file:border-0 file:bg-gray-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-100 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:file:bg-white/10 dark:file:text-white dark:hover:file:bg-white/20"
            >
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Поддерживается текстовый формат 1CClientBankExchange. Максимум 20 MB.
            </p>
        </div>

        <div>
            <label for="{{ $formId }}-bank-id" class="block text-sm/6 font-medium text-gray-900 dark:text-white">
                БИК банка
            </label>
            <input
                id="{{ $formId }}-bank-id"
                name="bank_id"
                value="{{ old('bank_id') }}"
                placeholder="044525068"
                inputmode="numeric"
                autocomplete="off"
                class="mt-2 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus-visible:outline-indigo-500"
            >
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Можно оставить пустым, если счет из файла уже есть в банковских счетах.
            </p>
        </div>

        <div>
            <label class="flex items-start gap-3 text-sm/6 font-medium text-gray-900 dark:text-white">
                <span class="grid size-5 shrink-0 place-items-center">
                    <input
                        type="checkbox"
                        name="rebuild_money_layer"
                        value="1"
                        @checked(old('rebuild_money_layer', true))
                        class="peer col-start-1 row-start-1 size-4 appearance-none rounded-sm border border-gray-300 bg-white checked:border-indigo-600 checked:bg-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:border-white/10 dark:bg-white/5 dark:checked:border-indigo-500 dark:checked:bg-indigo-500"
                    >
                    <svg viewBox="0 0 14 14" fill="none" class="pointer-events-none col-start-1 row-start-1 size-3.5 stroke-white opacity-0 peer-checked:opacity-100">
                        <path d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <span>Пересчитать Money layer после импорта</span>
            </label>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 bg-gray-50 px-6 py-4 dark:bg-white/5">
        <x-ui.button type="button" size="lg" data-ui-modal-close>
            Отмена
        </x-ui.button>
        <x-ui.button type="submit" size="lg">{{ $submitLabel }}</x-ui.button>
    </div>
</form>
