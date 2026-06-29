@php
    $date = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse((string) $value)->format('d.m.Y') : '—';
    $dateTime = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse((string) $value)->format('d.m.Y H:i') : '—';
    $text = static fn ($value) => $value !== null && $value !== '' ? $value : '—';
    $jsonArray = static function ($value): array {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    };
    $jsonText = static function ($value) use ($jsonArray): string {
        $data = $jsonArray($value);

        if ($data === []) {
            return '—';
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
    };
@endphp

<div class="space-y-5 px-6 py-5 text-sm">
    <section>
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Документ</h3>
        <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Номер СГР</dt>
                <dd class="mt-1 font-mono text-gray-900 dark:text-white">{{ $record->sgr_number }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Статус</dt>
                <dd class="mt-1 text-gray-900 dark:text-white">{{ $text($record->status_name) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Серия</dt>
                <dd class="mt-1 font-mono text-gray-900 dark:text-white">{{ $text($record->serial_number) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Дата документа</dt>
                <dd class="mt-1 font-mono text-gray-900 dark:text-white">{{ $date($record->document_date) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Действует с</dt>
                <dd class="mt-1 font-mono text-gray-900 dark:text-white">{{ $date($record->date_from) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Действует до</dt>
                <dd class="mt-1 font-mono text-gray-900 dark:text-white">{{ $date($record->date_to) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Страна</dt>
                <dd class="mt-1 text-gray-900 dark:text-white">{{ $text(trim(($record->country_code ? $record->country_code.' · ' : '').($record->country_name ?? ''))) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Подписант</dt>
                <dd class="mt-1 text-gray-900 dark:text-white">{{ $text($record->signer_name) }}</dd>
            </div>
        </dl>
    </section>

    <section>
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Продукция</h3>
        <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-4">
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Наименование</dt>
                <dd class="mt-1 text-gray-900 dark:text-white">{{ $text($record->product_name) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Код продукции</dt>
                <dd class="mt-1 font-mono text-gray-900 dark:text-white">{{ $text($record->product_code) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Применение продукции</dt>
                <dd class="mt-1 whitespace-pre-wrap text-gray-900 dark:text-white">{{ $text($record->product_application) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Область применения</dt>
                <dd class="mt-1 whitespace-pre-wrap text-gray-900 dark:text-white">{{ $text($record->use_area) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Нормативный документ</dt>
                <dd class="mt-1 whitespace-pre-wrap text-gray-900 dark:text-white">{{ $text($record->norm_doc) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Протокол</dt>
                <dd class="mt-1 whitespace-pre-wrap text-gray-900 dark:text-white">{{ $text($record->protocol) }}</dd>
            </div>
        </dl>
    </section>

    <section class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <div>
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Изготовитель</h3>
            <dl class="mt-3 space-y-4">
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Название</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white">{{ $text($record->manufacturer_name) }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Адрес</dt>
                    <dd class="mt-1 whitespace-pre-wrap text-gray-900 dark:text-white">{{ $text($record->manufacturer_address) }}</dd>
                </div>
            </dl>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Получатель</h3>
            <dl class="mt-3 space-y-4">
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Название</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white">{{ $text($record->recipient_name) }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">ИНН</dt>
                    <dd class="mt-1 font-mono text-gray-900 dark:text-white">{{ $text($record->recipient_inn) }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Адрес</dt>
                    <dd class="mt-1 whitespace-pre-wrap text-gray-900 dark:text-white">{{ $text($record->recipient_address) }}</dd>
                </div>
            </dl>
        </div>
    </section>

    <section>
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Синхронизация</h3>
        <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">NSI ID</dt>
                <dd class="mt-1 break-all font-mono text-xs text-gray-900 dark:text-white">{{ $text($record->nsi_id) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Version ID</dt>
                <dd class="mt-1 break-all font-mono text-xs text-gray-900 dark:text-white">{{ $text($record->version_id) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Обновлено в НСИ</dt>
                <dd class="mt-1 font-mono text-gray-900 dark:text-white">{{ $dateTime($record->update_date_time) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Список синхронизирован</dt>
                <dd class="mt-1 font-mono text-gray-900 dark:text-white">{{ $dateTime($record->list_synced_at) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Карточка синхронизирована</dt>
                <dd class="mt-1 font-mono text-gray-900 dark:text-white">{{ $dateTime($record->detail_synced_at) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Попыток детализации</dt>
                <dd class="mt-1 font-mono text-gray-900 dark:text-white">{{ number_format((int) $record->detail_attempts, 0, ',', ' ') }}</dd>
            </div>
            @if ($record->detail_sync_error)
                <div class="sm:col-span-2">
                    <dt class="font-medium text-rose-600 dark:text-rose-300">Ошибка детализации</dt>
                    <dd class="mt-1 whitespace-pre-wrap text-rose-700 dark:text-rose-300">{{ $record->detail_sync_error }}</dd>
                </div>
            @endif
        </dl>
    </section>

    <section class="space-y-3">
        <details class="rounded-md bg-gray-50 p-3 ring-1 ring-gray-900/5 dark:bg-white/5 dark:ring-white/10">
            <summary class="cursor-pointer text-sm font-semibold text-gray-900 dark:text-white">JSON карточки</summary>
            <pre class="mt-3 max-h-80 overflow-auto whitespace-pre-wrap break-words text-xs text-gray-700 dark:text-gray-200">{{ $jsonText($record->detail_payload) }}</pre>
        </details>

        <details class="rounded-md bg-gray-50 p-3 ring-1 ring-gray-900/5 dark:bg-white/5 dark:ring-white/10">
            <summary class="cursor-pointer text-sm font-semibold text-gray-900 dark:text-white">JSON строки списка</summary>
            <pre class="mt-3 max-h-80 overflow-auto whitespace-pre-wrap break-words text-xs text-gray-700 dark:text-gray-200">{{ $jsonText($record->source_list_payload) }}</pre>
        </details>
    </section>
</div>
