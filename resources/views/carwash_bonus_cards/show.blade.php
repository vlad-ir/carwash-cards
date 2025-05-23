@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Просмотр бонусной карты</h1>

        <div class="card">
            <div class="card-header">Детали бонусной карты</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Название:</strong> {{ $bonusCard->name }}</p>
                        <p><strong>Номер карты:</strong> {{ $bonusCard->card_number }}</p>
                        <p><strong>Клиент:</strong> {{ $bonusCard->client->short_name }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Ставка за минуту, BYN:</strong> {{ $bonusCard->rate_per_minute }}</p>
                        <p><strong>Статус:</strong>
                            @if ($bonusCard->status == 'active')
                                Активна
                            @else
                                Заблокирована
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <a href="{{ route('carwash_bonus_cards.edit', $bonusCard->id) }}" class="btn btn-warning">Редактировать</a>

                <!-- Форма удаления с кнопкой для модального подтверждения -->
                <form action="{{ route('carwash_bonus_cards.destroy', $bonusCard->id) }}" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger delete-single"
                            data-card-name="{{ htmlspecialchars($bonusCard->name) }}"
                            data-card-number="{{ htmlspecialchars($bonusCard->card_number) }}">
                        Удалить
                    </button>
                </form>

                <a href="{{ route('carwash_bonus_cards.index') }}" class="btn btn-secondary">Назад</a>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            $(document).ready(function () {
                $('.delete-single').on('click', function (e) {
                    e.preventDefault();
                    const form = $(this).closest('form');
                    const cardName = $(this).data('card-name');
                    const cardNumber = $(this).data('card-number');

                    showConfirmModal(`Вы уверены, что хотите удалить бонусную карту ${cardName} (${cardNumber})?`, function () {
                        form.submit();
                    });
                });
            });
        </script>
    @endpush
@endsection
