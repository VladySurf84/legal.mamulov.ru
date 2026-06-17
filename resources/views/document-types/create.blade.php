@extends('layouts.app', ['title' => 'Создать тип документа'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Создать тип документа</h1>
            <div class="subtle">Добавление нового типа в справочник.</div>
        </div>
        <a class="button secondary" href="{{ route('document-types.index') }}">Назад</a>
    </div>

    <div class="panel">
        <form class="form" method="post" action="{{ route('document-types.store') }}">
            @csrf
            @include('document-types.partials.form')
        </form>
    </div>
@endsection
