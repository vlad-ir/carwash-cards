@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Редактировать клиента</h1>
        <form action="{{ route('carwash_clients.update', $client->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label for="short_name">Краткое имя</label>
                <input type="text" class="form-control" id="short_name" name="short_name" value="{{ $client->short_name }}" required>
            </div>
            <div class="form-group">
                <label for="full_name">Полное имя</label>
                <input type="text" class="form-control" id="full_name" name="full_name" value="{{ $client->full_name }}" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="{{ $client->email }}" required>
            </div>
            <div class="form-group">
                <label for="phone">Телефон</label>
                <input type="text" class="form-control" id="phone" name="phone" value="{{ $client->phone }}" required>
            </div>
            <div class="form-group">
                <label for="unp">UNP</label>
                <input type="text" class="form-control" id="unp" name="unp" value="{{ $client->unp }}" required>
            </div>
            <div class="form-group">
                <label for="bank_account_number">Номер банковского счета</label>
                <input type="text" class="form-control" id="bank_account_number" name="bank_account_number" value="{{ $client->bank_account_number }}" required>
            </div>
            <div class="form-group">
                <label for="bank_bic">БИК банка</label>
                <input type="text" class="form-control" id="bank_bic" name="bank_bic" value="{{ $client->bank_bic }}" required>
            </div>
            <div class="form-group">
                <label for="postal_address">Почтовый адрес</label>
                <input type="text" class="form-control" id="postal_address" name="postal_address" value="{{ $client->postal_address }}" required>
            </div>
            <div class="form-group">
                <label for="bank_postal_address">Почтовый адрес банка</label>
                <input type="text" class="form-control" id="bank_postal_address" name="bank_postal_address" value="{{ $client->bank_postal_address }}" required>
            </div>
            <button type="submit" class="btn btn-primary">Обновить клиента</button>
        </form>
    </div>
@endsection
