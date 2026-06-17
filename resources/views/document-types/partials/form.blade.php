@if ($errors->any())
    <div class="errors">
        <strong>Проверь поля формы.</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $metadata = old('metadata', json_encode($documentType->metadata ?: new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $booleanFields = [
        'is_active' => 'Активен',
        'is_primary' => 'Первичный документ',
        'is_tax_document' => 'Налоговый документ',
        'is_money_document' => 'Денежный документ',
        'is_inventory_document' => 'ТМЦ / склад',
        'is_contract_document' => 'Договорной документ',
        'creates_accounting_events' => 'Создает бух. события',
        'creates_management_events' => 'Создает упр. события',
        'creates_tax_events' => 'Создает налоговые события',
        'requires_parties' => 'Нужны стороны',
        'requires_lines' => 'Нужны строки',
        'supports_corrections' => 'Есть корректировки',
        'supports_files' => 'Есть файлы',
    ];
@endphp

<div class="grid">
    <div class="field">
        <label for="name">Название</label>
        <input id="name" name="name" value="{{ old('name', $documentType->name) }}" required>
    </div>

    <div class="field">
        <label for="code">Код</label>
        <input id="code" name="code" value="{{ old('code', $documentType->code) }}" required pattern="[a-z0-9_]+">
    </div>

    <div class="field">
        <label for="document_group">Группа</label>
        <input id="document_group" name="document_group" value="{{ old('document_group', $documentType->document_group) }}" required>
    </div>

    <div class="field">
        <label for="default_direction">Направление</label>
        <select id="default_direction" name="default_direction">
            <option value="">Не задано</option>
            @foreach (['incoming' => 'Входящий', 'outgoing' => 'Исходящий', 'internal' => 'Внутренний'] as $value => $label)
                <option value="{{ $value }}" @selected(old('default_direction', $documentType->default_direction) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="field full">
        <label>Признаки</label>
        <div class="checks">
            @foreach ($booleanFields as $field => $label)
                <label class="check">
                    <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $documentType->{$field}))>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="field full">
        <label for="metadata">Metadata JSON</label>
        <textarea id="metadata" name="metadata">{{ $metadata }}</textarea>
    </div>
</div>

<div class="form-actions">
    <a class="button secondary" href="{{ route('document-types.index') }}">Отмена</a>
    <button type="submit">Сохранить</button>
</div>
