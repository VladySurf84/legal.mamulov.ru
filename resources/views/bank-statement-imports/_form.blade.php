@php
    $formId = $formId ?? 'bank-statement-import';
    $redirectTo = $redirectTo ?? null;
@endphp

<form id="{{ $formId }}" method="post" action="{{ route('bank-statement-imports.store') }}" enctype="multipart/form-data">
    @csrf

    @if ($redirectTo)
        <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">
    @endif

    <div class="space-y-5 px-6 py-5">
        <x-ui.preline.file-upload
            id="{{ $formId }}-statement-file"
            name="statement_files[]"
            label="Файлы выписок"
            accept=".txt,.1c,.kl_to_1c,.dat"
            hint="Поддерживается текстовый формат 1CClientBankExchange. Максимум 20 MB на файл."
            required
            multiple
            auto-submit
            :max-size-mb="20"
        />

        <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
            <input
                type="checkbox"
                name="auto_create_bank_account"
                value="1"
                class="mt-0.5 size-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600 dark:border-white/10 dark:bg-white/5"
                checked
            >
            <span>
                <span class="block font-medium text-gray-900 dark:text-white">Автоматически добавлять банк и счет</span>
                <span class="mt-1 block text-gray-500 dark:text-gray-400">Если расчетного счета еще нет в справочнике, он будет создан по данным выписки.</span>
            </span>
        </label>
    </div>
</form>
