@if (($errors ?? null)?->any())
    <div class="mb-6 rounded-md bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-rose-600/20">
        <div class="font-medium">Проверь поля формы.</div>
        <ul class="mt-2 list-disc space-y-1 pl-5">
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
    $inputClass = 'block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6';
@endphp

<div class="grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-2">
    <x-ui.input
        id="name"
        name="name"
        label="Название"
        :value="old('name', $documentType->name)"
        required
    />

    <x-ui.input
        id="code"
        name="code"
        label="Код"
        :value="old('code', $documentType->code)"
        class="font-mono"
        required
        pattern="[a-z0-9_]+"
    />

    <x-ui.input
        id="document_group"
        name="document_group"
        label="Группа"
        :value="old('document_group', $documentType->document_group)"
        required
    />

    <div>
        <label for="default_direction" class="block text-sm/6 font-medium text-gray-900">Направление</label>
        <div class="mt-2 grid grid-cols-1">
            <select id="default_direction" name="default_direction" class="col-start-1 row-start-1 w-full appearance-none rounded-md bg-white py-1.5 pr-8 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6">
                <option value="">Не задано</option>
                @foreach (['incoming' => 'Входящий', 'outgoing' => 'Исходящий', 'internal' => 'Внутренний'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('default_direction', $documentType->default_direction) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end text-gray-500 sm:size-4">
                <path d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
            </svg>
        </div>
    </div>

    <div class="sm:col-span-2">
        <div class="block text-sm/6 font-medium text-gray-900">Признаки</div>
        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($booleanFields as $field => $label)
                <label class="flex items-center gap-3 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700 ring-1 ring-gray-900/5">
                    <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $documentType->{$field})) class="size-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="sm:col-span-2">
        <label for="metadata" class="block text-sm/6 font-medium text-gray-900">Metadata JSON</label>
        <div class="mt-2">
            <textarea id="metadata" name="metadata" rows="12" class="{{ $inputClass }} font-mono text-xs">{{ $metadata }}</textarea>
        </div>
    </div>
</div>

<div class="mt-6 flex justify-end gap-2">
    <x-ui.button :href="route('document-types.index')" variant="ghost" wire:navigate>
        Отмена
    </x-ui.button>
    <x-ui.button type="submit" variant="soft">
        Сохранить
    </x-ui.button>
</div>
