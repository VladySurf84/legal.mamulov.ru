@extends('layouts.app', [
    'title' => 'Редактировать тип документа',
    'titleDescription' => $documentType->name,
])

@section('page_actions')
    <x-ui.button :href="route('document-types.index')" variant="ghost" wire:navigate>
        Назад
    </x-ui.button>
@endsection

@section('content')
    <div class="rounded-lg bg-white px-5 py-6 shadow-sm ring-1 ring-gray-900/5 sm:px-6">
        <form method="post" action="{{ route('document-types.update', $documentType) }}">
            @csrf
            @method('put')
            @include('document-types.partials.form')
        </form>
    </div>
@endsection
