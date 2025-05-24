@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Просмотр статистики: {{ $stat->card->card_number ?? 'N/A' }} - {{ $stat->start_time->format('d.m.Y H:i') }}</h1>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Детали записи</h3>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">Бонусная карта</dt>
                    <dd class="col-sm-9">{{ $stat->card->name ?? 'N/A' }} ({{ $stat->card->card_number ?? 'N/A' }})</dd>

                    <dt class="col-sm-3">Время начала</dt>
                    <dd class="col-sm-9">{{ $stat->start_time->format('d.m.Y H:i:s') }}</dd>

                    <dt class="col-sm-3">Длительность</dt>
                    <dd class="col-sm-9">{{ gmdate("H:i:s", $stat->duration_seconds) }}</dd>

                    <dt class="col-sm-3">Остаток</dt>
                    <dd class="col-sm-9">{{ $stat->remaining_balance_seconds !== null ? gmdate("H:i:s", $stat->remaining_balance_seconds) : 'Не указано' }}</dd>

                    <dt class="col-sm-3">Дата импорта</dt>
                    <dd class="col-sm-9">{{ $stat->import_date ? $stat->import_date->format('d.m.Y') : 'Не указана' }}</dd>
                </dl>
            </div>
            <div class="card-footer">
                <a href="{{ route('carwash_bonus_card_stats.edit', $stat->id) }}" class="btn btn-warning">Редактировать</a>
                <form action="{{ route('carwash_bonus_card_stats.destroy', $stat->id) }}" method="POST" style="display:inline;" id="deleteStatForm">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger" id="deleteStatButton">Удалить</button>
                </form>
                <a href="{{ route('carwash_bonus_card_stats.index') }}" class="btn btn-secondary">Назад</a>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            $(document).ready(function() {
                $('#deleteStatButton').on('click', function(e) {
                    e.preventDefault();
                    const cardInfo = "{{ htmlspecialchars($stat->card?->card_number . ' (' . $stat->start_time->format('d.m.Y H:i') . ')' ?? 'Запись') }}";
                    showConfirmModal(`Вы уверены, что хотите удалить запись статистики для ${cardInfo}?`, function() {
                        $('#deleteStatForm').submit();
                    });
                });
            });
        </script>
    @endpush
@endsection
