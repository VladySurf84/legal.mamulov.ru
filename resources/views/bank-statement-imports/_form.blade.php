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
    </div>
</form>
