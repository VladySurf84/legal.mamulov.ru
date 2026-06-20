@extends('layouts.app', ['title' => 'Книги НДС'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Книги НДС</h1>
            <div class="subtle">Книги покупок и продаж от бухгалтера для последующей сверки с нашими документами.</div>
        </div>
    </div>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="errors">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="errors">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="panel" style="margin-bottom: 16px;">
        <form class="form" method="post" action="{{ route('vat-books.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="grid">
                <div class="field full">
                    <label for="book_file">XML-файл книги покупок или продаж</label>
                    <input id="book_file" name="book_file" type="file" accept=".xml,text/xml,application/xml" required>
                    <div class="subtle">Оригинал файла сохраняется в архив, строки импортируются в legal.vat_book_entries.</div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit">Загрузить книгу</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <table>
            <thead>
            <tr>
                <th>Период</th>
                <th>Тип</th>
                <th>Юрлицо</th>
                <th>Файл</th>
                <th class="money">Строк</th>
                <th class="money">Сумма</th>
                <th class="money">НДС</th>
                <th>Статус</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($imports as $import)
                <tr>
                    <td>{{ $import->year }} Q{{ $import->quarter }}</td>
                    <td>{{ $import->book_type === 'purchase' ? 'Покупки' : 'Продажи' }}</td>
                    <td>
                        <strong>{{ $import->legal_name }}</strong>
                        <div class="subtle">ИНН {{ $import->legal_inn }}</div>
                    </td>
                    <td>
                        <div>{{ $import->source_file_name }}</div>
                        <div class="subtle code">{{ $import->stored_path }}</div>
                    </td>
                    <td class="money">{{ number_format((int) $import->entries_count, 0, ',', ' ') }}</td>
                    <td class="money">{{ $import->total_amount !== null ? number_format((float) $import->total_amount, 2, ',', ' ') : '—' }}</td>
                    <td class="money">{{ $import->total_vat_amount !== null ? number_format((float) $import->total_vat_amount, 2, ',', ' ') : '—' }}</td>
                    <td>
                        <span class="badge">{{ $import->is_active ? 'Активная' : 'Архив' }}</span>
                        <div class="subtle">{{ \Illuminate\Support\Carbon::parse($import->imported_at)->format('d.m.Y H:i') }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">Книги НДС пока не загружены.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
