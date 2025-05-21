@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Добавить клиента</h1>

        <form action="{{ route('carwash_clients.store') }}" method="POST">
            @csrf

            <div class="row">
                <!-- Левая колонка -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Полное имя</label>
                        <input type="text" name="full_name" id="full_name"
                               class="form-control @error('full_name') is-invalid @enderror"
                               value="{{ old('full_name') }}" required>
                        @error('full_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="short_name" class="form-label">Краткое имя</label>
                        <input type="text" name="short_name" id="short_name"
                               class="form-control @error('short_name') is-invalid @enderror"
                               value="{{ old('short_name') }}" required>
                        @error('short_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" id="email"
                               class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email') }}" required>
                        @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="unp" class="form-label">УНП</label>
                        <input type="text" name="unp" id="unp"
                               class="form-control @error('unp') is-invalid @enderror"
                               value="{{ old('unp') }}" maxlength="9" required>
                        @error('unp')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="postal_address" class="form-label">Почтовый адрес</label>
                        <input type="text" name="postal_address" id="postal_address"
                               class="form-control @error('postal_address') is-invalid @enderror"
                               value="{{ old('postal_address') }}" required>
                        @error('postal_address')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Правая колонка -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="bank_account_number" class="form-label">Номер банковского счета</label>
                        <input type="text" name="bank_account_number" id="bank_account_number"
                               class="form-control @error('bank_account_number') is-invalid @enderror"
                               value="{{ old('bank_account_number') }}" required>
                        @error('bank_account_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="bank_bic" class="form-label">БИК банка</label>
                        <input type="text" name="bank_bic" id="bank_bic"
                               class="form-control @error('bank_bic') is-invalid @enderror"
                               value="{{ old('bank_bic') }}" required>
                        @error('bank_bic')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="bank_postal_address" class="form-label">Почтовый адрес банка</label>
                        <input type="text" name="bank_postal_address" id="bank_postal_address"
                               class="form-control @error('bank_postal_address') is-invalid @enderror"
                               value="{{ old('bank_postal_address') }}" required>
                        @error('bank_postal_address')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="contract" class="form-label">Договор</label>
                        <input type="text" name="contract" id="contract"
                               class="form-control @error('contract') is-invalid @enderror"
                               value="{{ old('contract') }}">
                        @error('contract')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Статус</label>
                        <select name="status" id="status"
                                class="form-control @error('status') is-invalid @enderror" required>
                            <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Активен</option>
                            <option value="blocked" {{ old('status') == 'blocked' ? 'selected' : '' }}>Заблокирован</option>
                        </select>
                        @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="hidden" name="invoice_email_required" value="0">
                            <input type="checkbox" name="invoice_email_required" id="invoice_email_required"
                                   class="form-check-input @error('invoice_email_required') is-invalid @enderror"
                                   value="1" {{ old('invoice_email_required', 1) ? 'checked' : '' }}>
                            <label for="invoice_email_required" class="form-check-label">
                                Требуется отправка счета по email
                            </label>
                            @error('invoice_email_required')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="invoice_email_day" class="form-label">День отправки счета</label>
                        <input type="number" name="invoice_email_day" id="invoice_email_day"
                               class="form-control @error('invoice_email_day') is-invalid @enderror"
                               value="{{ old('invoice_email_day', 5) }}" min="1" max="31">
                        @error('invoice_email_day')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="{{ route('carwash_clients.index') }}" class="btn btn-secondary">Назад</a>
        </form>
    </div>
@endsection
