@extends('layouts.app', ['title' => 'Права пользователей'])

@php
    $moduleLabels = [
        'legal_entities' => [
            'label' => 'Наши юридические лица',
            'secondary' => 'Страница списка ИП и юрлиц',
        ],
        'bank_accounts' => [
            'label' => 'Банковские счета',
            'secondary' => 'Страница банковских счетов',
        ],
        'bank_accounts.import' => [
            'label' => 'Обновить из mamulov.ru',
            'secondary' => 'Действие внутри раздела банковских счетов',
        ],
        'bank_transactions' => [
            'label' => 'Транзакции',
            'secondary' => 'Страница банковских операций',
        ],
        'bank_transactions.import' => [
            'label' => 'Загрузить выписку',
            'secondary' => 'Действие внутри раздела транзакций',
        ],
        'bank_transactions.sync' => [
            'label' => 'Обновить Тинек',
            'secondary' => 'Действие внутри раздела транзакций',
        ],
        'kassa' => [
            'label' => 'Касса',
            'secondary' => 'Страница кассовых операций и ручных записей',
        ],
        'kassa.create' => [
            'label' => 'Добавлять, править и удалять свежие записи',
            'secondary' => 'Можно добавлять записи, править и удалять ручные записи кассы не старше одной недели',
        ],
        'kassa.delete_any' => [
            'label' => 'Править и удалять любые записи',
            'secondary' => 'Снимает ограничение одной недели для правки и удаления ручных записей',
        ],
        'documents' => [
            'label' => 'Документы',
            'secondary' => 'Страница документов',
        ],
        'counterparties' => [
            'label' => 'Контрагенты',
            'secondary' => 'Страница контрагентов',
        ],
        'counterparties.rebuild_links' => [
            'label' => 'Пересчитать связи',
            'secondary' => 'Действие внутри раздела контрагентов',
        ],
        'money_layer' => [
            'label' => 'Money layer',
            'secondary' => 'Слой денежных связей',
        ],
        'money_layer.rebuild' => [
            'label' => 'Пересчитать слой',
            'secondary' => 'Действие внутри Money layer',
        ],
        'vat_layer' => [
            'label' => 'VAT layer',
            'secondary' => 'Слой НДС',
        ],
        'vat_layer.rebuild' => [
            'label' => 'Пересчитать слой',
            'secondary' => 'Действие внутри VAT layer',
        ],
        'vat_layer.rebuild_bank' => [
            'label' => 'Пересчитать банковский НДС',
            'secondary' => 'Действие внутри VAT layer',
        ],
        'vat_books' => [
            'label' => 'Книги НДС',
            'secondary' => 'Страница книг НДС',
        ],
        'vat_books.import' => [
            'label' => 'Импортировать книги',
            'secondary' => 'Действие внутри раздела книг НДС',
        ],
        'vat_book_entries' => [
            'label' => 'Содержание книг',
            'secondary' => 'Страница строк книг НДС',
        ],
        'currencies' => [
            'label' => 'Валюты',
            'secondary' => 'Справочник валют',
        ],
        'exchange_rates' => [
            'label' => 'Курсы валют',
            'secondary' => 'Справочник курсов валют',
        ],
        'exchange_rates.sync' => [
            'label' => 'Обновить курсы',
            'secondary' => 'Действие внутри раздела курсов валют',
        ],
        'document_types' => [
            'label' => 'Типы документов',
            'secondary' => 'Справочник типов документов',
        ],
        'document_types.create' => [
            'label' => 'Создать тип',
            'secondary' => 'Действие внутри справочника типов документов',
        ],
        'document_types.edit' => [
            'label' => 'Редактировать тип',
            'secondary' => 'Действие внутри справочника типов документов',
        ],
        'document_types.delete' => [
            'label' => 'Удалить тип',
            'secondary' => 'Действие внутри справочника типов документов',
        ],
        'electronic_signatures' => [
            'label' => 'Электронные подписи',
            'secondary' => 'Страница сертификатов и импорта подписей CryptoPro',
        ],
        'electronic_signatures.import' => [
            'label' => 'Импортировать',
            'secondary' => 'Действие внутри раздела электронных подписей',
        ],
        'hh_resumes' => [
            'label' => 'HH резюме',
            'secondary' => 'Страница таблицы резюме и оценок кандидатов',
        ],
        'users' => [
            'label' => 'Пользователи',
            'secondary' => 'Страница списка пользователей',
        ],
    ];

    $dataPermissionLabels = [
        'can_view' => 'Смотреть',
    ];
    $moduleDepth = [
        'kassa.delete_any' => 2,
    ];

    $userOptions = $users->map(fn ($user) => [
        'value' => (string) $user->getKey(),
        'label' => $user->name ?: $user->email,
        'secondary' => trim(($user->isAdmin() ? 'Админ' : 'Пользователь') . ' · ' . $user->email),
        'swatch' => $user->isAdmin() ? '#4f46e5' : ($user->is_active ? '#16a34a' : '#dc2626'),
    ]);

    $scopeGroups = $legalEntities->map(function ($legalEntity) {
        return [
            'key' => 'legal:' . $legalEntity->legal_id,
            'label' => $legalEntity->legal_name,
            'secondary' => 'ИНН ' . ($legalEntity->legal_inn ?: $legalEntity->legal_id),
            'swatch' => $legalEntity->legal_color ?: '#e5e7eb',
        ];
    })->values();

    $scopePermissions = $selectedUser
        ? $selectedUser->accessScopes
            ->where('scope_type', '!=', 'all_graph')
            ->keyBy(fn ($scope) => $scope->scope_type . ':' . $scope->scope_id)
        : collect();
    $selectedModulePermissions = $selectedUser
        ? $selectedUser->modulePermissions->keyBy(fn ($permission) => $permission->module . ':' . $permission->scope_id)
        : collect();
    $selectedGlobalModulePermissions = $selectedUser
        ? $selectedUser->modulePermissions
            ->where('scope_type', 'global')
            ->keyBy('module')
        : collect();
    $isAdmin = $selectedUser?->isAdmin() ?? false;
@endphp

@section('content')
    @if (session('status'))
        <div class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-800 ring-1 ring-green-600/20">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-6">
        <form method="get" action="{{ route('user-access.index') }}" class="max-w-xl">
            <x-ui.select-with-secondary-text
                name="user_id"
                label="Пользователь"
                :value="$selectedUser?->getKey()"
                :options="$userOptions"
                selected-layout="stacked"
                submit-on-change
            />
        </form>

        @if ($selectedUser)
            <section class="rounded-lg bg-white shadow-sm ring-1 ring-gray-900/5">
                <form method="post" action="{{ route('user-access.update', $selectedUser) }}" data-user-access-autosave-form>
                    @csrf
                    @method('put')

                    <div class="flex flex-col gap-4 border-b border-gray-200 px-4 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-base font-semibold text-gray-900">{{ $selectedUser->name }}</h2>
                                @if ($isAdmin)
                                    <span class="rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-indigo-600/20">Админ</span>
                                @elseif (! $selectedUser->is_active)
                                    <span class="rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-red-600/20">Отключен</span>
                                @endif
                            </div>
                            <div class="mt-1 truncate text-sm text-gray-500">{{ $selectedUser->email }}</div>
                        </div>

                        <div class="flex flex-wrap items-end gap-3">
                            <div class="pb-2">
                                <input type="hidden" name="is_admin" value="0">
                                <x-ui.native-checkbox
                                    name="is_admin"
                                    value="1"
                                    :checked="$isAdmin"
                                    label="Администратор"
                                />
                            </div>

                            <div class="pb-2">
                                <input type="hidden" name="is_active" value="0">
                                <x-ui.native-checkbox
                                    name="is_active"
                                    value="1"
                                    :checked="$selectedUser->is_active"
                                    label="Активен"
                                />
                            </div>
                        </div>
                    </div>

                    @if ($isAdmin)
                        <div class="px-4 py-4 text-sm text-gray-500 sm:px-6">
                            Администратор видит весь граф и может выполнять все действия без отдельных строк доступа.
                        </div>
                    @endif

                    <div class="px-4 py-5 sm:px-6">
                        <x-ui.tabs
                            :tabs="[
                                ['id' => 'global-sections', 'label' => 'Общие разделы'],
                                ['id' => 'legal-sections', 'label' => 'Разделы по ИП'],
                                ['id' => 'data-scopes', 'label' => 'Данные'],
                            ]"
                            active="global-sections"
                        >
                        <section data-ui-tabs-panel="global-sections" class="mt-5">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">Общие разделы</h3>
                                <p class="mt-1 text-sm text-gray-500">Страницы, которые открываются без выбора конкретного ИП.</p>
                            </div>

                            <div class="mt-4 overflow-x-auto rounded-lg ring-1 ring-gray-200">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="py-3.5 pr-3 pl-4 text-left font-semibold text-gray-900 sm:pl-6">Раздел</th>
                                            <th class="min-w-36 px-3 py-3.5 text-center font-semibold text-gray-900">Открыть</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        @foreach ($globalModules as $module)
                                            @php($modulePermission = $selectedGlobalModulePermissions->get($module))
                                            @php($moduleIndentLevel = $moduleDepth[$module] ?? (str_contains($module, '.') ? 1 : 0))
                                            <tr class="cursor-pointer hover:bg-gray-50" data-user-access-checkbox-row>
                                                <td class="py-3 pr-3 pl-4 sm:pl-6">
                                                    <div @class([
                                                        'flex items-start gap-2',
                                                        'pl-5' => $moduleIndentLevel === 1,
                                                        'pl-10' => $moduleIndentLevel > 1,
                                                    ])>
                                                        @if ($moduleIndentLevel > 0)
                                                            <span class="mt-0.5 text-gray-400">↳</span>
                                                        @endif
                                                        <div class="min-w-0">
                                                            <div class="font-medium text-gray-900">{{ $moduleLabels[$module]['label'] ?? $module }}</div>
                                                            <div class="mt-0.5 text-xs text-gray-500">{{ $moduleLabels[$module]['secondary'] ?? '' }}</div>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td class="cursor-pointer px-3 py-3 text-center hover:bg-gray-50" data-user-access-checkbox-cell>
                                                    <x-ui.native-checkbox
                                                        name="modules_global[{{ $module }}]"
                                                        value="1"
                                                        :checked="(bool) ($modulePermission?->can_view ?? false)"
                                                    />
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section data-ui-tabs-panel="legal-sections" class="mt-5" hidden>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">Разделы по ИП</h3>
                                <p class="mt-1 text-sm text-gray-500">Страницы, которые открываются в контексте выбранного ИП.</p>
                            </div>

                            <div class="mt-4 overflow-x-auto rounded-lg ring-1 ring-gray-200">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="py-3.5 pr-3 pl-4 text-left font-semibold text-gray-900 sm:pl-6">Раздел</th>
                                            @foreach ($legalEntities as $legalEntity)
                                                <th class="min-w-44 px-3 py-3.5 text-center font-semibold text-gray-900">
                                                    <div class="flex flex-col items-center gap-1">
                                                        <span
                                                            class="inline-flex size-2.5 rounded-full ring-1 ring-black/10"
                                                            style="background-color: {{ $legalEntity->legal_color ?: '#e5e7eb' }}"
                                                        ></span>
                                                        <span>{{ $legalEntity->legal_name }}</span>
                                                        <span class="text-xs font-normal text-gray-500">ИНН {{ $legalEntity->legal_inn ?: $legalEntity->legal_id }}</span>
                                                    </div>
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        @foreach ($scopedModules as $module)
                                            @php($isAction = str_contains($module, '.'))
                                            <tr>
                                                <td class="py-3 pr-3 pl-4 sm:pl-6">
                                                    <div class="flex items-start gap-2 @if ($isAction) pl-5 @endif">
                                                        @if ($isAction)
                                                            <span class="mt-0.5 text-gray-400">↳</span>
                                                        @endif
                                                        <div class="min-w-0">
                                                            <div class="font-medium text-gray-900">{{ $moduleLabels[$module]['label'] ?? $module }}</div>
                                                            <div class="mt-0.5 text-xs text-gray-500">{{ $moduleLabels[$module]['secondary'] ?? '' }}</div>
                                                        </div>
                                                    </div>
                                                </td>

                                                @foreach ($legalEntities as $legalEntity)
                                                    @php($modulePermission = $selectedModulePermissions->get($module . ':' . $legalEntity->legal_id))
                                                    <td class="cursor-pointer px-3 py-3 text-center hover:bg-gray-50" data-user-access-checkbox-cell>
                                                        <x-ui.native-checkbox
                                                            name="modules[{{ $module }}][{{ $legalEntity->legal_id }}]"
                                                            value="1"
                                                            :checked="(bool) ($modulePermission?->can_view ?? false)"
                                                        />
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section data-ui-tabs-panel="data-scopes" class="mt-5" hidden>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">Данные</h3>
                                <p class="mt-1 text-sm text-gray-500">Ограничивает, какие ИП и юрлица доступны внутри разрешенных разделов. Счета выбранного ИП доступны целиком.</p>
                            </div>

                            <div class="mt-4 overflow-x-auto rounded-lg ring-1 ring-gray-200">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="sticky left-0 z-20 min-w-72 bg-gray-50 py-3.5 pr-3 pl-4 text-left font-semibold text-gray-900 sm:pl-6">Объект</th>
                                            @foreach ($dataPermissions as $permission)
                                                <th class="min-w-36 px-3 py-3.5 text-center font-semibold text-gray-900">
                                                    {{ $dataPermissionLabels[$permission] }}
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        @foreach ($scopeGroups as $scopeGroup)
                                            @php($scope = $scopePermissions->get($scopeGroup['key']))
                                            <tr class="cursor-pointer bg-gray-50/70 hover:bg-gray-100" data-user-access-checkbox-row>
                                                <td class="sticky left-0 z-10 bg-gray-50 py-3 pr-3 pl-4 sm:pl-6">
                                                    <div class="flex items-start gap-3">
                                                        <span
                                                            class="mt-1 inline-flex size-3 shrink-0 rounded-full ring-1 ring-black/10"
                                                            style="background-color: {{ $scopeGroup['swatch'] }}"
                                                        ></span>
                                                        <div class="min-w-0">
                                                            <div class="font-semibold text-gray-900">{{ $scopeGroup['label'] }}</div>
                                                            <div class="mt-0.5 text-xs text-gray-500">{{ $scopeGroup['secondary'] }}</div>
                                                        </div>
                                                    </div>
                                                </td>

                                                @foreach ($dataPermissions as $permission)
                                                    <td class="cursor-pointer bg-gray-50/70 px-3 py-3 text-center hover:bg-gray-100" data-user-access-checkbox-cell>
                                                        <x-ui.native-checkbox
                                                            name="scopes[{{ $scopeGroup['key'] }}][{{ $permission }}]"
                                                            value="1"
                                                            :checked="(bool) ($scope?->{$permission} ?? false)"
                                                        />
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                        </x-ui.tabs>
                    </div>
                </form>
            </section>
        @else
            <div class="rounded-lg bg-white px-6 py-8 text-sm text-gray-500 shadow-sm ring-1 ring-gray-900/5">
                Пользователей пока нет.
            </div>
        @endif
    </div>

    @once
        <script>
            (() => {
                const initUserAccessAutosave = () => {
                    document.querySelectorAll('[data-user-access-autosave-form]').forEach((form) => {
                        if (form.dataset.userAccessAutosaveInitialized === 'true') {
                            return;
                        }

                        form.dataset.userAccessAutosaveInitialized = 'true';

                        let saveTimer = null;
                        let isSaving = false;
                        let hasPendingSave = false;
                        let reloadAfterSave = false;
                        const pendingCheckboxes = new Set();

                        const setCheckboxPending = (checkbox, pending) => {
                            if (! checkbox) {
                                return;
                            }

                            const wrapper = checkbox.closest('[data-ui-native-checkbox]');
                            const pendingIcon = wrapper?.querySelector('[data-ui-native-checkbox-pending-icon]');

                            checkbox.classList.toggle('opacity-50', pending);

                            if (pendingIcon) {
                                pendingIcon.classList.toggle('hidden', ! pending || checkbox.checked);
                            }

                            checkbox.style.backgroundColor = pending && ! checkbox.checked ? '#eef2ff' : '';
                            checkbox.style.borderColor = pending && ! checkbox.checked ? '#a5b4fc' : '';
                        };

                        const addPendingCheckbox = (checkbox) => {
                            if (! checkbox) {
                                return;
                            }

                            pendingCheckboxes.add(checkbox);
                            setCheckboxPending(checkbox, true);
                        };

                        const clearPendingCheckboxes = () => {
                            pendingCheckboxes.forEach((checkbox) => {
                                setCheckboxPending(checkbox, false);
                            });
                            pendingCheckboxes.clear();
                        };

                        const save = async () => {
                            if (isSaving) {
                                hasPendingSave = true;
                                return;
                            }

                            isSaving = true;
                            hasPendingSave = false;

                            try {
                                const response = await fetch(form.action, {
                                    method: form.method || 'POST',
                                    body: new FormData(form),
                                    headers: {
                                        Accept: 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                });

                                if (! response.ok) {
                                    throw new Error('Autosave failed');
                                }

                                if (reloadAfterSave) {
                                    window.location.reload();
                                    return;
                                }
                            } catch (error) {
                                console.error(error);
                            } finally {
                                isSaving = false;

                                if (hasPendingSave) {
                                    save();
                                    return;
                                }

                                clearPendingCheckboxes();
                            }
                        };

                        const scheduleSave = (event) => {
                            addPendingCheckbox(event.target);

                            if (event.target.name === 'is_admin') {
                                reloadAfterSave = true;
                            }

                            window.clearTimeout(saveTimer);
                            saveTimer = window.setTimeout(save, 250);
                        };

                        form.querySelectorAll('input[type="checkbox"]').forEach((input) => {
                            input.addEventListener('change', scheduleSave);
                        });

                        form.querySelectorAll('[data-user-access-checkbox-cell]').forEach((cell) => {
                            cell.addEventListener('click', (event) => {
                                if (event.target.closest('input, label, button, a')) {
                                    return;
                                }

                                const checkbox = cell.querySelector('input[type="checkbox"]');

                                if (! checkbox || checkbox.disabled) {
                                    return;
                                }

                                checkbox.checked = ! checkbox.checked;
                                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                            });
                        });

                        form.querySelectorAll('[data-user-access-checkbox-row]').forEach((row) => {
                            row.addEventListener('click', (event) => {
                                if (event.target.closest('input, label, button, a, [data-user-access-checkbox-cell]')) {
                                    return;
                                }

                                const checkbox = row.querySelector('input[type="checkbox"]');

                                if (! checkbox || checkbox.disabled) {
                                    return;
                                }

                                checkbox.checked = ! checkbox.checked;
                                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                            });
                        });
                    });
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initUserAccessAutosave, { once: true });
                } else {
                    initUserAccessAutosave();
                }

                document.addEventListener('livewire:navigated', initUserAccessAutosave);
            })();
        </script>
    @endonce
@endsection
