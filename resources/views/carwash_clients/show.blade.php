@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Информация о клиенте</h1>
        <p><strong>Краткое имя:</strong> {{ $client->short_name }}</p>
        <p><strong>Полное имя:</strong> {{ $client->full_name }}</p>
        <p><strong>Email:</strong> {{ $client->email }}</p>
        <p><strong>Телефон:</strong> {{ $client->phone }}</p>
        <p><strong>UNP:</strong> {{ $client->unp }}</p>
        <p><strong>Номер банковского счета:</strong> {{ $client->bank_account_number }}</p>
        <p><strong>БИК банка:</strong> {{ $client->bank_bic }}</p>
        <p><strong>Почтовый адрес:</strong> {{ $client->postal_address }}</p>
        <p><strong>Почтовый адрес банка:</strong> {{ $client->bank_postal_address }}</p>
        <a href="{{ route('carwash_clients.edit', $client->id) }}" class="btn btn-warning">Редактировать</a>
        <form action="{{ route('carwash_clients.destroy', $client->id) }}" method="POST" style="display:inline;">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger" onclick="return confirm('Вы уверены?')">Удалить</button>
        </form>
        <a href="{{ route('carwash_clients.index') }}" class="btn btn-secondary">Назад</a>
    </div>
@endsection
