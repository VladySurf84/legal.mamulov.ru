@extends('layouts.app', [
    'title' => 'Электронные подписи',
    'titleDescription' => 'Сертификаты CryptoPro, которыми будем подписывать запросы к ЭДО, СУЗ и другим внешним системам.',
])

@section('page_actions')
    <div class="flex gap-2">
        @if ($canManageElectronicSignatures)
            <x-ui.button type="button" variant="ghost" data-ui-modal-open="cryptopro-import-dialog">
                Импортировать
            </x-ui.button>
        @endif
        <x-ui.button :href="route('internal-api-docs.index')" variant="ghost" wire:navigate>
            Swagger API
        </x-ui.button>
    </div>
@endsection

@section('content')
    @if ($canManageElectronicSignatures)
        <x-ui.modal
            id="cryptopro-import-dialog"
            title="Импорт подписей из CryptoPro"
            description="Система вызовет серверный API, который прочитает сертификаты из хранилища CryptoPro на сервере и вернет их для синхронизации."
            size="lg"
        >
            <div class="px-6 py-5">
                <div class="rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-600 ring-1 ring-gray-900/5 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                    <div class="font-medium text-gray-900 dark:text-white">Что произойдет</div>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li>Будет вызвано внутреннее API сервера.</li>
                        <li>Сервер запустит поиск сертификатов в установленном CryptoPro.</li>
                        <li>Найденные отпечатки будут сохранены как API-credentials.</li>
                        <li>Локальный проект синхронизирует карточки подписей без доступа к закрытым ключам.</li>
                    </ul>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <x-ui.button type="button" size="md" variant="ghost" data-ui-modal-close>
                        Отмена
                    </x-ui.button>

                    <form method="POST" action="{{ route('electronic-signatures.import') }}">
                        @csrf
                        <x-ui.button type="submit" size="md" variant="soft">
                            Запустить импорт
                        </x-ui.button>
                    </form>
                </div>
            </div>
        </x-ui.modal>
    @endif

    @if (session('status'))
        <div class="mb-4 rounded-md bg-indigo-50 px-4 py-3 text-sm font-medium text-indigo-700 ring-1 ring-indigo-600/20">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-md bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 ring-1 ring-rose-600/20">
            {{ session('error') }}
        </div>
    @endif

    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
    >
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th first>Юрлицо</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Подпись</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Тип</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Отпечаток</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Провайдер</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Действует до</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Контейнер</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Статус</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last>Последнее использование</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @forelse ($signatures as $signature)
            <tr class="align-top hover:bg-gray-50">
                <x-ui.sticky-table-td first :nowrap="false">
                    <div class="font-medium text-gray-900">{{ $signature->legal_name ?: '—' }}</div>
                    <div class="mt-1 text-xs text-gray-500">{{ $signature->legal_inn ?: $signature->owner_id }}</div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false">
                    <div class="font-medium text-gray-900">{{ $signature->name ?: 'CryptoPro' }}</div>
                    <div class="mt-1 text-xs text-gray-500">{{ $signature->subject ?: $signature->credential_type }}</div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    @php
                        $typeClasses = match ($signature->subject_type) {
                            'individual_entrepreneur' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
                            'person' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
                            'legal_entity' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                            default => 'bg-gray-50 text-gray-700 ring-gray-600/20',
                        };
                        $typeDetails = match ($signature->subject_type) {
                            'individual_entrepreneur' => $signature->ogrnip ? 'ОГРНИП '.$signature->ogrnip : null,
                            'person' => $signature->snils ? 'СНИЛС '.$signature->snils : null,
                            'legal_entity' => $signature->ogrn ? 'ОГРН '.$signature->ogrn : null,
                            default => null,
                        };
                    @endphp
                    <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 {{ $typeClasses }}">
                        {{ $signature->subject_type_label }}
                    </span>
                    @if ($typeDetails)
                        <div class="mt-1 text-xs text-gray-500">{{ $typeDetails }}</div>
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="font-mono text-xs tracking-wide">
                    {{ $signature->thumbprint_tail ? '...' . $signature->thumbprint_tail : '—' }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    {{ $signature->provider }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    {{ $signature->valid_to ?: '—' }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false" class="font-mono text-xs">
                    {{ $signature->container ?: '—' }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    @if ($signature->status === 'active')
                        <span class="rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-green-600/20">Активна</span>
                    @else
                        <span class="rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-700 ring-1 ring-gray-600/20">{{ $signature->status }}</span>
                    @endif
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td last>
                    {{ $signature->last_used_at?->format('d.m.Y H:i') ?: '—' }}
                </x-ui.sticky-table-td>
            </tr>
        @empty
            <tr>
                <td class="py-12 text-center text-sm text-gray-500" colspan="9">
                    Электронные подписи пока не импортированы.
                </td>
            </tr>
        @endforelse
    </x-ui.sticky-table>
@endsection
