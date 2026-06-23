@extends('layouts.app', [
    'title' => 'Создать тип документа',
    'titleDescription' => 'Добавление нового типа в справочник документов.',
])

@section('page_actions')
    <x-ui.button :href="route('document-types.index')" variant="ghost" wire:navigate>
        Назад
    </x-ui.button>
@endsection

@section('content')
    <div class="rounded-lg bg-white px-5 py-6 shadow-sm ring-1 ring-gray-900/5 sm:px-6">
        <form method="post" action="{{ route('document-types.store') }}">
            @csrf
            @include('document-types.partials.form')
        </form>
    </div>
@endsection
