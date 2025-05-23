@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Редактировать бонусную карту</h1>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('carwash_bonus_cards.update', $bonusCard->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label for="name">Название</label>
                <input type="text" name="name" id="name" value="{{ old('name', $bonusCard->name) }}" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="card_number">Номер карты</label>
                <input type="text" name="card_number" id="card_number" value="{{ old('card_number', $bonusCard->card_number) }}" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="client_id">Клиент</label>
                <select name="client_id" id="client_id" class="form-control" required>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" {{ old('client_id', $bonusCard->client_id) == $client->id ? 'selected' : '' }}>{{ $client->short_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="rate_per_minute">Ставка за минуту, BYN</label>
                <input type="number" step="0.01" name="rate_per_minute" id="rate_per_minute" value="{{ old('rate_per_minute', $bonusCard->rate_per_minute) }}" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="status">Статус</label>
                <select name="status" id="status" class="form-control" required>
                    <option value="active" {{ old('status', $bonusCard->status) == 'active' ? 'selected' : '' }}>Активна</option>
                    <option value="blocked" {{ old('status', $bonusCard->status) == 'blocked' ? 'selected' : '' }}>Заблокирована</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="{{ route('carwash_bonus_cards.index') }}" class="btn btn-secondary">Отмена</a>
        </form>
    </div>
@endsection
