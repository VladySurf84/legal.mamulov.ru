@extends('layouts.app', [
    'title' => 'Книги НДС',
    'titleAttribute' => 'Книги покупок и продаж от бухгалтера для сверки с нашими документами.',
])

@section('page_actions')
    <div class="flex flex-wrap items-center gap-2">
        @if (\App\Support\UserAccess::canImportVatBooks(auth()->user()))
        <x-ui.button type="button" size="md" variant="ghost" data-ui-modal-open="vat-book-import-dialog">
            Загрузить книгу
        </x-ui.button>
        @endif

        <x-ui.button href="{{ route('vat-book-entries.index') }}" size="md" variant="ghost" wire:navigate>
            Содержание книг
        </x-ui.button>
    </div>
@endsection

@section('content')
    @if (\App\Support\UserAccess::canImportVatBooks(auth()->user()))
    <x-ui.modal
        id="vat-book-import-dialog"
        title="Загрузка книг НДС"
        description="XML-файлы книг покупок и продаж будут сохранены в архив, импортированы в строки книг и пересчитаны в VAT layer."
        size="xl"
        :open="session('open_modal') === 'vat-book-import-dialog'"
    >
        <form id="vat-book-import-form" method="post" action="{{ route('vat-books.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="redirect_to" value="{{ url()->full() }}">

            <div class="space-y-5 px-6 py-5">
                <x-ui.preline.file-upload
                    id="vat-book-import-files"
                    name="book_files[]"
                    label="Файлы книг"
                    accept=".xml,text/xml,application/xml"
                    hint="Поддерживаются XML-файлы книг покупок и продаж. Максимум 20 MB на файл."
                    required
                    multiple
                    auto-submit
                    :max-size-mb="20"
                />
            </div>
        </form>
    </x-ui.modal>
    @endif

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

    <x-ui.sticky-table
        :contained="false"
        :scrollable="true"
        :viewport-sticky="true"
        :bottom-scrollbar="true"
        scroll-class="overflow-x-auto overflow-y-visible"
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

                <x-ui.sticky-table-td :nowrap="false">
                    <div class="whitespace-normal break-words font-medium text-gray-900 dark:text-white">{{ $import->legal_name }}</div>
                    <div class="mt-1 font-mono text-xs text-gray-400">ИНН {{ $import->legal_inn }}</div>
                </x-ui.sticky-table-td>

                <x-ui.sticky-table-td :nowrap="false">
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
