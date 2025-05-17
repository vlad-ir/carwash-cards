@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Создать бонусную карту</h1>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('carwash_bonus_cards.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="name">Название</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="card_number">Номер карты</label>
                <input type="text" name="card_number" id="card_number" value="{{ old('card_number') }}" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="discount_percentage">Скидка (%)</label>
                <input type="number" step="0.01" name="discount_percentage" id="discount_percentage" value="{{ old('discount_percentage') }}" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="balance">Баланс (HH:MM:SS)</label>
                <input type="text" name="balance" id="balance" value="{{ old('balance', '00:00:00') }}" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="status">Статус</label>
                <select name="status" id="status" class="form-control" required>
                    <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Активна</option>
                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Неактивна</option>
                    <option value="blocked" {{ old('status') == 'blocked' ? 'selected' : '' }}>Заблокирована</option>
                </select>
            </div>
            <div class="form-group">
                <label for="car_license_plate">Номер автомобиля</label>
                <input type="text" name="car_license_plate" id="car_license_plate" value="{{ old('car_license_plate') }}" class="form-control">
            </div>
            <div class="form-group">
                <label for="rate_per_minute">Ставка за минуту</label>
                <input type="number" step="0.01" name="rate_per_minute" id="rate_per_minute" value="{{ old('rate_per_minute') }}" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="invoice_required">
                    <input type="checkbox" name="invoice_required" id="invoice_required" value="1" {{ old('invoice_required') ? 'checked' : '' }}>
                    Требуется счет
                </label>
            </div>
            <div class="form-group">
                <label for="client_id">Клиент</label>
                <select name="client_id" id="client_id" class="form-control" required>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>{{ $client->short_name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Создать</button>
            <a href="{{ route('carwash_bonus_cards.index') }}" class="btn btn-secondary">Отмена</a>
        </form>
    </div>
@endsection
