@extends('layouts.app', [
    'title' => 'Типы документов',
    'titleDescription' => 'Справочник типов документов для первичных данных, бухгалтерских книг и интерпретационных слоев.',
])

@section('page_actions')
    <x-ui.button :href="route('document-types.create')" variant="ghost" wire:navigate>
        Создать тип
    </x-ui.button>
@endsection

@section('content')
    @if (session('status'))
        <div class="mb-4 rounded-md bg-indigo-50 px-4 py-3 text-sm font-medium text-indigo-700 ring-1 ring-indigo-600/20">
            {{ session('status') }}
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
                <x-ui.sticky-table-th first>Название</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Код</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Группа</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Направление</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Назначение</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last align="right"></x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @forelse ($documentTypes as $documentType)
            <tr class="align-top hover:bg-gray-50 dark:hover:bg-white/5">
                <x-ui.sticky-table-td first :nowrap="false" strong>
                    <div>{{ $documentType->name }}</div>
                    @unless ($documentType->is_active)
                        <span class="mt-1 inline-flex rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-700 ring-1 ring-gray-600/20">
                            выключен
                        </span>
                    @endunless
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td class="font-mono text-xs tracking-wide">
                    {{ $documentType->code }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    {{ $documentType->document_group }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td>
                    @php
                        $direction = match ($documentType->default_direction) {
                            'incoming' => 'Входящий',
                            'outgoing' => 'Исходящий',
                            'internal' => 'Внутренний',
                            default => 'Не задано',
                        };
                    @endphp
                    {{ $direction }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false">
                    <div class="flex flex-wrap gap-1.5">
                        @if ($documentType->is_primary)<span class="rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-700 ring-1 ring-gray-600/20">первичный</span>@endif
                        @if ($documentType->is_tax_document)<span class="rounded-md bg-sky-50 px-2 py-1 text-xs font-medium text-sky-700 ring-1 ring-sky-600/20">налоги</span>@endif
                        @if ($documentType->is_money_document)<span class="rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-green-600/20">деньги</span>@endif
                        @if ($documentType->is_inventory_document)<span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-amber-600/20">ТМЦ</span>@endif
                        @if ($documentType->is_contract_document)<span class="rounded-md bg-violet-50 px-2 py-1 text-xs font-medium text-violet-700 ring-1 ring-violet-600/20">договор</span>@endif
                        @if ($documentType->creates_accounting_events)<span class="rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-indigo-600/20">бухучет</span>@endif
                        @if ($documentType->creates_management_events)<span class="rounded-md bg-cyan-50 px-2 py-1 text-xs font-medium text-cyan-700 ring-1 ring-cyan-600/20">упручёт</span>@endif
                        @if ($documentType->creates_tax_events)<span class="rounded-md bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 ring-1 ring-rose-600/20">налоговый учет</span>@endif
                    </div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td last align="right">
                    <div class="flex justify-end gap-2">
                        <x-ui.button :href="route('document-types.edit', $documentType)" variant="ghost" wire:navigate>
                            Изменить
                        </x-ui.button>

                        <form method="post" action="{{ route('document-types.destroy', $documentType) }}" onsubmit="return confirm('Удалить тип документа?')">
                            @csrf
                            @method('delete')
                            <x-ui.button type="submit" variant="ghost">
                                Удалить
                            </x-ui.button>
                        </form>
                    </div>
                </x-ui.sticky-table-td>
            </tr>
        @empty
            <tr>
                <td class="py-12 text-center text-sm text-gray-500" colspan="6">
                    Типы документов пока не созданы.
                </td>
            </tr>
        @endforelse
    </x-ui.sticky-table>
@endsection
