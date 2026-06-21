@extends('layouts.app', ['title' => 'Типы документов'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Типы документов</h1>
            <div class="subtle">Справочник документов для бухгалтерского, управленческого и налогового учета.</div>
        </div>
        <a class="button" href="{{ route('document-types.create') }}" wire:navigate>Создать тип</a>
    </div>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    <div class="panel">
        <table>
            <thead>
            <tr>
                <th>Название</th>
                <th>Код</th>
                <th>Группа</th>
                <th>Назначение</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($documentTypes as $documentType)
                <tr>
                    <td>
                        <strong>{{ $documentType->name }}</strong>
                        @unless ($documentType->is_active)
                            <span class="badge">выключен</span>
                        @endunless
                    </td>
                    <td class="code">{{ $documentType->code }}</td>
                    <td>{{ $documentType->document_group }}</td>
                    <td>
                        <div class="badges">
                            @if ($documentType->is_primary)<span class="badge">первичный</span>@endif
                            @if ($documentType->is_tax_document)<span class="badge">налоги</span>@endif
                            @if ($documentType->is_money_document)<span class="badge">деньги</span>@endif
                            @if ($documentType->is_inventory_document)<span class="badge">ТМЦ</span>@endif
                            @if ($documentType->is_contract_document)<span class="badge">договор</span>@endif
                            @if ($documentType->creates_accounting_events)<span class="badge">бухучет</span>@endif
                            @if ($documentType->creates_management_events)<span class="badge">упручёт</span>@endif
                            @if ($documentType->creates_tax_events)<span class="badge">налоговый учет</span>@endif
                        </div>
                    </td>
                    <td>
                        <div class="actions">
                            <a class="button secondary" href="{{ route('document-types.edit', $documentType) }}" wire:navigate>Изменить</a>
                            <form method="post" action="{{ route('document-types.destroy', $documentType) }}" onsubmit="return confirm('Удалить тип документа?')">
                                @csrf
                                @method('delete')
                                <button class="danger" type="submit">Удалить</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Типы документов пока не созданы.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
