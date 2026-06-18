@extends('layouts.app', ['title' => 'Вход'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Вход</h1>
            <div class="subtle">Закрытая часть бухгалтерии.</div>
        </div>
    </div>

    <div class="panel" style="max-width: 420px;">
        @if (config('services.google.client_id') && config('services.google.client_secret'))
            <div class="form-actions" style="margin-bottom: 16px;">
                <a class="button" href="{{ route('auth.google.redirect') }}">Войти через Google</a>
            </div>
        @endif

        <form class="form" method="post" action="{{ route('login.store') }}">
            @csrf

            @if ($errors->any())
                <div class="errors">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="grid" style="grid-template-columns: 1fr;">
                <div class="field">
                    <label for="login">Email</label>
                    <input id="login" name="login" value="{{ old('login') }}" autocomplete="email" autofocus>
                </div>

                <div class="field">
                    <label for="password">Пароль</label>
                    <input id="password" name="password" type="password" autocomplete="current-password">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit">Войти</button>
            </div>
        </form>
    </div>
@endsection
