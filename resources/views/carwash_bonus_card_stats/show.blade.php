@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Просмотр записи статистики</h1>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Детали записи</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Бонусная карта:</strong> {{ $stat->card->name }} ({{ $stat->card->card_number }})</p>
                        <p><strong>Название карты:</strong> {{ $stat->card_name ?? 'Не указано' }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Время начала:</strong> {{ $stat->start_time->format('d.m.Y H:i') }}</p>
                        <p><strong>Длительность (сек):</strong> {{ $stat->duration_seconds }}</p>
                        <p><strong>Остаток (сек):</strong> {{ $stat->remaining_balance_seconds ?? 'Не указано' }}</p>
                        <p><strong>Дата импорта:</strong> {{ $stat->import_date->format('d.m.Y') }}</p>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="{{ route('carwash_bonus_card_stats.edit', $stat->id) }}" class="btn btn-warning">Редактировать</a>
                <form action="{{ route('carwash_bonus_card_stats.destroy', $stat->id) }}" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Вы уверены?')">Удалить</button>
                </form>
                <a href="{{ route('carwash_bonus_card_stats.index') }}" class="btn btn-secondary">Назад</a>
            </div>
        </div>
    </div>
@endsection
