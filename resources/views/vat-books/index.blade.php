@extends('layouts.app', [
    'title' => 'Книги НДС',
    'titleAttribute' => 'Книги покупок и продаж от бухгалтера для сверки с нашими документами.',
])

@section('page_actions')
    <x-ui.button href="{{ route('vat-book-entries.index') }}" size="lg" wire:navigate>
        Содержание книг
    </x-ui.button>
@endsection

@section('content')
    @if (session('status'))
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ session('error') }}
        </div>
    @endif

    @if (($errors ?? null)?->any())
        <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="mb-4 rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <form class="p-4" method="post" action="{{ route('vat-books.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <label class="block">
                    <span class="block text-sm/6 font-medium text-gray-900 dark:text-white">XML-файл книги покупок или продаж</span>
                    <input
                        class="mt-2 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:file:bg-indigo-500/20 dark:file:text-indigo-300 dark:focus-visible:outline-indigo-500"
                        name="book_file"
                        type="file"
                        accept=".xml,text/xml,application/xml"
                        required
                    >
                    <span class="mt-2 block text-sm text-gray-500 dark:text-gray-400">
                        Оригинал файла сохраняется в архив, строки импортируются в legal.vat_book_entries.
                    </span>
                </label>

                <x-ui.button type="submit" size="lg" variant="soft">
                    Загрузить книгу
                </x-ui.button>
            </div>
        </form>
    </div>

    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
        table-class="!min-w-[1300px]"
    >
        <x-slot:head>
            <tr>
                <x-ui.sticky-table-th first>Период</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Тип</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Юрлицо</x-ui.sticky-table-th>
                <x-ui.sticky-table-th>Файл</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Строк</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">Сумма</x-ui.sticky-table-th>
                <x-ui.sticky-table-th align="right">НДС</x-ui.sticky-table-th>
                <x-ui.sticky-table-th last>Статус</x-ui.sticky-table-th>
            </tr>
        </x-slot:head>

        @forelse ($imports as $import)
            <tr class="align-top hover:bg-gray-50 dark:hover:bg-white/5">
                <x-ui.sticky-table-td first nowrap>
                    {{ $import->year }} Q{{ $import->quarter }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td nowrap>
                    <span @class([
                        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1',
                        'bg-cyan-50 text-cyan-700 ring-cyan-200' => $import->book_type === 'purchase',
                        'bg-indigo-50 text-indigo-700 ring-indigo-200' => $import->book_type === 'sales',
                    ])>
                        {{ $import->book_type === 'purchase' ? 'Покупки' : 'Продажи' }}
                    </span>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false" class="min-w-64">
                    <div class="whitespace-normal break-words font-medium text-gray-900 dark:text-white">{{ $import->legal_name }}</div>
                    <div class="mt-1 font-mono text-xs text-gray-400">ИНН {{ $import->legal_inn }}</div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false" class="min-w-[24rem]">
                    <div class="whitespace-normal break-words font-medium text-gray-900 dark:text-white">{{ $import->source_file_name }}</div>
                    <div class="mt-1 whitespace-normal break-words font-mono text-xs text-gray-400">{{ $import->stored_path }}</div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td align="right" nowrap>
                    {{ number_format((int) $import->entries_count, 0, ',', ' ') }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td align="right" nowrap>
                    {{ $import->total_amount !== null ? number_format((float) $import->total_amount, 2, ',', ' ') : '—' }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td align="right" nowrap>
                    {{ $import->total_vat_amount !== null ? number_format((float) $import->total_vat_amount, 2, ',', ' ') : '—' }}
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td last nowrap>
                    <span @class([
                        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1',
                        'bg-emerald-50 text-emerald-700 ring-emerald-200' => $import->is_active,
                        'bg-gray-100 text-gray-600 ring-gray-200' => ! $import->is_active,
                    ])>
                        {{ $import->is_active ? 'Активная' : 'Архив' }}
                    </span>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ \Illuminate\Support\Carbon::parse($import->imported_at)->format('d.m.Y H:i') }}
                    </div>
                </x-ui.sticky-table-td>
            </tr>
        @empty
            <tr>
                <td class="py-8 text-center text-sm text-gray-500 dark:text-gray-400" colspan="8">
                    Книги НДС пока не загружены.
                </td>
            </tr>
        @endforelse
    </x-ui.sticky-table>
@endsection
