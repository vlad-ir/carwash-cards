@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Добавить клиента</h1>
        <form action="{{ route('carwash_clients.store') }}" method="POST" id="create-client-form">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="short_name">Краткое имя</label>
                        <input type="text" class="form-control @error('short_name') is-invalid @enderror" id="short_name" name="short_name" value="{{ old('short_name') }}" required>
                        @error('short_name')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="full_name">Полное имя</label>
                        <input type="text" class="form-control @error('full_name') is-invalid @enderror" id="full_name" name="full_name" value="{{ old('full_name') }}" required>
                        @error('full_name')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required>
                        @error('email')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="phone">Телефон</label>
                        <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" required>
                        @error('phone')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="unp">УНП</label>
                        <input type="text" class="form-control @error('unp') is-invalid @enderror" id="unp" name="unp" value="{{ old('unp') }}" required>
                        @error('unp')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="bank_account_number">Номер банковского счета</label>
                        <input type="text" class="form-control @error('bank_account_number') is-invalid @enderror" id="bank_account_number" name="bank_account_number" value="{{ old('bank_account_number') }}" required>
                        @error('bank_account_number')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="bank_bic">БИК банка</label>
                        <input type="text" class="form-control @error('bank_bic') is-invalid @enderror" id="bank_bic" name="bank_bic" value="{{ old('bank_bic') }}" required>
                        @error('bank_bic')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="postal_address">Почтовый адрес</label>
                        <input type="text" class="form-control @error('postal_address') is-invalid @enderror" id="postal_address" name="postal_address" value="{{ old('postal_address') }}" required>
                        @error('postal_address')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="bank_postal_address">Почтовый адрес банка</label>
                        <input type="text" class="form-control @error('bank_postal_address') is-invalid @enderror" id="bank_postal_address" name="bank_postal_address" value="{{ old('bank_postal_address') }}" required>
                        @error('bank_postal_address')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="status">Статус</label>
                        <select class="form-control @error('status') is-invalid @enderror" id="status" name="status" required>
                            <option value="active">Активный</option>
                            <option value="inactive">Неактивный</option>
                            <option value="blocked">Заблокирован</option>
                        </select>
                        @error('status')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="invoice_email_required">Требуется счет-фактура на email</label>
                        <input type="checkbox" class="form-check-input" id="invoice_email_required" name="invoice_email_required" {{ old('invoice_email_required') ? 'checked' : '' }}>
                    </div>
                    <div class="form-group">
                        <label for="invoice_email_date">Дата счет-фактуры на email</label>
                        <input type="date" class="form-control @error('invoice_email_date') is-invalid @enderror" id="invoice_email_date" name="invoice_email_date" value="{{ old('invoice_email_date') }}">
                        @error('invoice_email_date')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Бонусные карты</label>
                <div id="bonus-cards">
                    <div class="bonus-card row">
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="bonus_cards[0][card_number]" placeholder="Номер карты" required>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="bonus_cards[0][name]" placeholder="Имя владельца карты" required>
                        </div>
                        <div class="col-md-2">
                            <input type="number" class="form-control" name="bonus_cards[0][discount_percentage]" placeholder="Процент скидки" step="0.01" required>
                        </div>
                        <div class="col-md-2">
                            <input type="time" class="form-control" name="bonus_cards[0][balance]" placeholder="Баланс (время)" required>
                        </div>
                        <div class="col-md-2">
                            <select class="form-control" name="bonus_cards[0][status]" required>
                                <option value="active">Активная</option>
                                <option value="inactive">Неактивная</option>
                                <option value="blocked">Заблокирована</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="bonus_cards[0][car_license_plate]" placeholder="Номерной знак автомобиля">
                        </div>
                        <div class="col-md-3">
                            <input type="number" class="form-control" name="bonus_cards[0][rate_per_minute]" placeholder="Тариф за минуту" step="0.01" required>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="bonus_cards[0][invoice_required]" id="invoice_required_0">
                                <label class="form-check-label" for="invoice_required_0">Требуется счет-фактура</label>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-danger remove-card">Удалить</button>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-primary add-card">Добавить карту</button>
            </div>
            <button type="submit" class="btn btn-success">Сохранить</button>
            <a href="{{ route('carwash_clients.index') }}" class="btn btn-secondary">Отмена</a>
        </form>
    </div>

    @push('scripts')
        <script>
            $(document).ready(function() {
                let cardIndex = 1;

                $('.add-card').on('click', function() {
                    var newCard = `
                    <div class="bonus-card row">
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="bonus_cards[${cardIndex}][card_number]" placeholder="Номер карты" required>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="bonus_cards[${cardIndex}][name]" placeholder="Имя владельца карты" required>
                        </div>
                        <div class="col-md-2">
                            <input type="number" class="form-control" name="bonus_cards[${cardIndex}][discount_percentage]" placeholder="Процент скидки" step="0.01" required>
                        </div>
                        <div class="col-md-2">
                            <input type="time" class="form-control" name="bonus_cards[${cardIndex}][balance]" placeholder="Баланс (время)" required>
                        </div>
                        <div class="col-md-2">
                            <select class="form-control" name="bonus_cards[${cardIndex}][status]" required>
                                <option value="active">Активная</option>
                                <option value="inactive">Неактивная</option>
                                <option value="blocked">Заблокирована</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="bonus_cards[${cardIndex}][car_license_plate]" placeholder="Номерной знак автомобиля">
                        </div>
                        <div class="col-md-3">
                            <input type="number" class="form-control" name="bonus_cards[${cardIndex}][rate_per_minute]" placeholder="Тариф за минуту" step="0.01" required>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="bonus_cards[${cardIndex}][invoice_required]" id="invoice_required_${cardIndex}">
                                <label class="form-check-label" for="invoice_required_${cardIndex}">Требуется счет-фактура</label>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-danger remove-card">Удалить</button>
                        </div>
                    </div>
                `;
                    $('#bonus-cards').append(newCard);
                    cardIndex++;
                });

                $('#bonus-cards').on('click', '.remove-card', function() {
                    $(this).parent().parent().remove();
                });
            });
        </script>
    @endpush
@endsection
