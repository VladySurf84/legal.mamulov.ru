@extends('layouts.app', ['title' => 'Импорт Ozon'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Импорт Ozon</h1>
            <div class="subtle">Загрузка банковской выписки Ozon из файла 1CClientBankExchange.</div>
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

    <div class="panel">
        <form class="form" method="post" action="{{ route('ozon-bank-statements.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="grid">
                <div class="field full">
                    <label for="statement_file">Файл выписки</label>
                    <input id="statement_file" name="statement_file" type="file" accept=".txt,.1c,.kl_to_1c,.dat" required>
                    <div class="subtle">Поддерживается текстовый формат 1CClientBankExchange. Максимум 20 MB.</div>
                </div>

                <div class="field">
                    <label for="bank_id">БИК банка</label>
                    <input id="bank_id" name="bank_id" value="{{ old('bank_id') }}" placeholder="044525068">
                    <div class="subtle">Можно оставить пустым, если счет из файла уже есть в банковских счетах.</div>
                </div>

                <div class="field">
                    <label>&nbsp;</label>
                    <label class="check" style="min-height: 38px;">
                        <input type="checkbox" name="rebuild_money_layer" value="1" @checked(old('rebuild_money_layer', true))>
                        Пересчитать Money layer после импорта
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit">Загрузить файл</button>
            </div>
        </form>
    </div>
@endsection
