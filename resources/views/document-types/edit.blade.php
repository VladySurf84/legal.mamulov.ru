@extends('layouts.app', ['title' => 'Редактировать тип документа'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Редактировать тип документа</h1>
            <div class="subtle">{{ $documentType->name }}</div>
        </div>
        <a class="button secondary" href="{{ route('document-types.index') }}">Назад</a>
    </div>

    <div class="panel">
        <form class="form" method="post" action="{{ route('document-types.update', $documentType) }}">
            @csrf
            @method('put')
            @include('document-types.partials.form')
        </form>
    </div>
@endsection
