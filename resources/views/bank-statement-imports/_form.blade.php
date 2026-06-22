@php
    $formId = $formId ?? 'bank-statement-import';
    $redirectTo = $redirectTo ?? null;
    $submitLabel = $submitLabel ?? 'Загрузить файл';
@endphp

<form class="form" method="post" action="{{ route('bank-statement-imports.store') }}" enctype="multipart/form-data">
    @csrf

    @if ($redirectTo)
        <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">
    @endif

    <div class="grid">
        <div class="field full">
            <label for="{{ $formId }}-statement-file">Файл выписки</label>
            <input id="{{ $formId }}-statement-file" name="statement_file" type="file" accept=".txt,.1c,.kl_to_1c,.dat" required>
            <div class="subtle">Поддерживается текстовый формат 1CClientBankExchange. Максимум 20 MB.</div>
        </div>

        <div class="field">
            <label for="{{ $formId }}-bank-id">БИК банка</label>
            <input id="{{ $formId }}-bank-id" name="bank_id" value="{{ old('bank_id') }}" placeholder="044525068">
            <div class="subtle">Можно оставить пустым, если счет из файла уже есть в банковских счетах.</div>
        </div>

        <div class="field">
            <label>&nbsp;</label>
            <label class="check" style="min-height: 38px;">
                <input type="checkbox" name="rebuild_money_layer" value="1" @checked(old('rebuild_money_layer', true))>
                Пересчитать Money layer после импорта
            </label>
        </div>
    </div>

    <div class="form-actions">
        <x-ui.button type="submit" size="lg">{{ $submitLabel }}</x-ui.button>
    </div>
</form>
