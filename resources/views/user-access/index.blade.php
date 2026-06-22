@extends('layouts.app', ['title' => 'Права пользователей'])

@php
    $permissionLabels = [
        'can_view' => 'Смотреть',
        'can_import_bank_statements' => 'Импорт выписок',
        'can_sync_bank_api' => 'API банка',
        'can_manage_api_credentials' => 'API-ключи',
        'can_edit_manual_operations' => 'Ручные операции',
        'can_manage_reference_data' => 'Справочники',
    ];

    $roleLabels = [
        'admin' => 'Админ',
        'manager' => 'Менеджер',
        'accountant' => 'Внешний бухгалтер',
        'viewer' => 'Наблюдатель',
    ];

    $userOptions = $users->map(fn ($user) => [
        'value' => (string) $user->getKey(),
        'label' => $user->name ?: $user->email,
        'secondary' => trim(($roleLabels[$user->role] ?? $user->role) . ' · ' . $user->email),
        'swatch' => $user->role === 'admin' ? '#4f46e5' : ($user->is_active ? '#16a34a' : '#dc2626'),
    ]);

    $scopeRows = collect([
        [
            'key' => 'all_graph',
            'label' => 'Глобальный',
            'secondary' => 'Все юрлица и все контрагенты',
            'swatch' => '#6b7280',
        ],
    ])->merge($legalEntities->map(fn ($legalEntity) => [
        'key' => 'legal:' . $legalEntity->legal_id,
        'label' => $legalEntity->legal_name,
        'secondary' => 'ИНН ' . ($legalEntity->legal_inn ?: $legalEntity->legal_id),
        'swatch' => $legalEntity->legal_color ?: '#e5e7eb',
    ]));

    $scopePermissions = $selectedUser
        ? $selectedUser->accessScopes->keyBy(fn ($scope) => $scope->scope_type === 'all_graph' ? 'all_graph' : 'legal:' . $scope->scope_id)
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
                <form method="post" action="{{ route('user-access.update', $selectedUser) }}">
                    @csrf
                    @method('put')

                    <div class="flex flex-col gap-4 border-b border-gray-200 px-4 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-base font-semibold text-gray-900">{{ $selectedUser->name }}</h2>
                                @if ($isAdmin)
                                    <span class="rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-indigo-600/20">admin</span>
                                @elseif (! $selectedUser->is_active)
                                    <span class="rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-red-600/20">отключен</span>
                                @endif
                            </div>
                            <div class="mt-1 truncate text-sm text-gray-500">{{ $selectedUser->email }}</div>
                        </div>

                        <div class="flex flex-wrap items-end gap-3">
                            <label class="block">
                                <span class="block text-xs font-medium text-gray-500">Роль</span>
                                <select
                                    name="role"
                                    class="mt-1 rounded-md bg-white py-1.5 pr-8 pl-3 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600"
                                >
                                    @foreach ($roleLabels as $role => $label)
                                        <option value="{{ $role }}" @selected($selectedUser->role === $role)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="inline-flex items-center gap-2 pb-2 text-sm font-medium text-gray-700">
                                <input type="hidden" name="is_active" value="0">
                                <input
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    @checked($selectedUser->is_active)
                                    class="size-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                >
                                Активен
                            </label>

                            <x-ui.button type="submit" variant="soft">Сохранить</x-ui.button>
                        </div>
                    </div>

                    @if ($isAdmin)
                        <div class="px-4 py-4 text-sm text-gray-500 sm:px-6">
                            Админ видит весь граф и может выполнять все действия без отдельных строк доступа.
                        </div>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="py-3.5 pr-3 pl-4 text-left font-semibold text-gray-900 sm:pl-6">Призма</th>
                                    @foreach ($permissions as $permission)
                                        <th class="px-3 py-3.5 text-center font-semibold text-gray-900">
                                            {{ $permissionLabels[$permission] }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($scopeRows as $scopeRow)
                                    @php($scope = $scopePermissions->get($scopeRow['key']))
                                    <tr>
                                        <td class="py-3 pr-3 pl-4 sm:pl-6">
                                            <div class="flex items-start gap-3">
                                                <span
                                                    class="mt-1 inline-flex size-3 shrink-0 rounded-full ring-1 ring-black/10"
                                                    style="background-color: {{ $scopeRow['swatch'] }}"
                                                ></span>
                                                <div class="min-w-0">
                                                    <div class="font-medium text-gray-900">{{ $scopeRow['label'] }}</div>
                                                    <div class="mt-0.5 text-xs text-gray-500">{{ $scopeRow['secondary'] }}</div>
                                                </div>
                                            </div>
                                        </td>

                                        @foreach ($permissions as $permission)
                                            <td class="px-3 py-3 text-center">
                                                <input
                                                    type="checkbox"
                                                    name="scopes[{{ $scopeRow['key'] }}][{{ $permission }}]"
                                                    value="1"
                                                    @checked((bool) ($scope?->{$permission} ?? false))
                                                    class="size-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                                >
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </form>
            </section>
        @else
            <div class="rounded-lg bg-white px-6 py-8 text-sm text-gray-500 shadow-sm ring-1 ring-gray-900/5">
                Пользователей пока нет.
            </div>
        @endif
    </div>
@endsection
