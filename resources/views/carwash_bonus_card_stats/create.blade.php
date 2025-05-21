@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Создать запись статистики</h1>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('carwash_bonus_card_stats.store') }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="card_id" class="form-label">Бонусная карта</label>
                        <select name="card_id" id="card_id" class="form-control" required>
                            @foreach ($cards as $card)
                                <option value="{{ $card->id }}">{{ $card->name }} ({{ $card->card_number }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="card_name" class="form-label">Название карты</label>
                        <input type="text" name="card_name" id="card_name" value="{{ old('card_name') }}" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="start_time" class="form-label">Время начала</label>
                        <input type="datetime-local" name="start_time" id="start_time" value="{{ old('start_time') }}" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="duration_seconds" class="form-label">Длительность (сек)</label>
                        <input type="number" name="duration_seconds" id="duration_seconds" value="{{ old('duration_seconds') }}" class="form-control" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="remaining_balance_seconds" class="form-label">Остаток (сек)</label>
                        <input type="number" name="remaining_balance_seconds" id="remaining_balance_seconds" value="{{ old('remaining_balance_seconds') }}" class="form-control" min="0">
                    </div>
                    <div class="mb-3">
                        <label for="import_date" class="form-label">Дата импорта</label>
                        <input type="date" name="import_date" id="import_date" value="{{ old('import_date') }}" class="form-control" required>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Создать</button>
            <a href="{{ route('carwash_bonus_card_stats.index') }}" class="btn btn-secondary">Отмена</a>
        </form>
    </div>
@endsection
