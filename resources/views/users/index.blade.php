@extends('layouts.app', ['title' => 'Пользователи'])

@php
    $roleLabels = [
        'admin' => 'Админ',
        'manager' => 'Менеджер',
        'accountant' => 'Бухгалтер',
        'viewer' => 'Наблюдатель',
    ];
@endphp

@section('page_actions')
    <x-ui.button href="{{ route('user-access.index') }}" variant="soft">
        Настроить права
    </x-ui.button>
@endsection

@section('content')
    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
        table-class="!min-w-[1000px]"
    >
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th first>Пользователь</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Роль</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Статус</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Google</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Последний вход</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Призм</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last align="right">Действия</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @forelse ($users as $user)
            <tr class="align-top hover:bg-gray-50">
                <x-ui.sticky-table-td class="min-w-80" first :nowrap="false">
                    <div class="flex items-center gap-3">
                        @if ($user->avatar)
                            <img src="{{ $user->avatar }}" alt="" class="size-10 shrink-0 rounded-full object-cover outline -outline-offset-1 outline-gray-200">
                        @else
                            <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-gray-900 text-sm font-semibold text-white">
                                {{ mb_strtoupper(mb_substr($user->name ?: $user->email ?: 'U', 0, 1)) }}
                            </span>
                        @endif

                        <div class="min-w-0">
                            <div class="font-medium text-gray-900">{{ $user->name }}</div>
                            <div class="mt-0.5 text-xs text-gray-500">{{ $user->email }}</div>
                        </div>
                    </div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    <span @class([
                        'rounded-md px-2 py-1 text-xs font-medium ring-1',
                        'bg-indigo-50 text-indigo-700 ring-indigo-600/20' => $user->role === 'admin',
                        'bg-gray-50 text-gray-700 ring-gray-600/20' => $user->role !== 'admin',
                    ])>
                        {{ $roleLabels[$user->role] ?? $user->role }}
                    </span>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    @if ($user->is_active)
                        <span class="rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-green-600/20">Активен</span>
                    @else
                        <span class="rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-red-600/20">Отключен</span>
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    @if ($user->google_id)
                        <span class="text-gray-900">Подключен</span>
                    @else
                        <span class="text-gray-400">Нет</span>
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    {{ $user->last_login_at?->format('d.m.Y H:i') ?: '—' }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td align="right" class="tabular-nums">
                    {{ $user->role === 'admin' ? '∞' : $user->access_scopes_count }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td last align="right">
                    <x-ui.button href="{{ route('user-access.index', ['user_id' => $user->getKey()]) }}" size="md" variant="ghost">
                        Права
                    </x-ui.button>
                </x-ui.sticky-table-td>
            </tr>
        @empty
            <tr>
                <td class="py-8 text-center text-sm text-gray-500" colspan="7">
                    Пользователей пока нет.
                </td>
            </tr>
        @endforelse
    </x-ui.sticky-table>
@endsection
