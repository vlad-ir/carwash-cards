@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Добавить клиента</h1>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('carwash_clients.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="short_name">Краткое имя</label>
                <input type="text" name="short_name" id="short_name" class="form-control @error('short_name') is-invalid @enderror" value="{{ old('short_name') }}" required>
                @error('short_name')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="full_name">Полное имя</label>
                <input type="text" name="full_name" id="full_name" class="form-control @error('full_name') is-invalid @enderror" value="{{ old('full_name') }}" required>
                @error('full_name')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="unp">УНП</label>
                <input type="text" name="unp" id="unp" class="form-control @error('unp') is-invalid @enderror" value="{{ old('unp') }}">
                @error('unp')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="bank_account_number">Номер банковского счета</label>
                <input type="text" name="bank_account_number" id="bank_account_number" class="form-control @error('bank_account_number') is-invalid @enderror" value="{{ old('bank_account_number') }}">
                @error('bank_account_number')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="bank_bic">БИК банка</label>
                <input type="text" name="bank_bic" id="bank_bic" class="form-control @error('bank_bic') is-invalid @enderror" value="{{ old('bank_bic') }}">
                @error('bank_bic')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="status">Статус</label>
                <select name="status" id="status" class="form-control @error('status') is-invalid @enderror" required>
                    <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Активен</option>
                    <option value="blocked" {{ old('status') == 'blocked' ? 'selected' : '' }}>Заблокирован</option>
                </select>
                @error('status')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="invoice_email_required">
                    <input type="checkbox" name="invoice_email_required" id="invoice_email_required" value="1" {{ old('invoice_email_required') ? 'checked' : '' }}>
                    Требуется отправка счета на email
                </label>
            </div>
            <div class="form-group">
                <label for="invoice_email_day">День для отправки счета на email (1-31)</label>
                <input type="number" name="invoice_email_day" id="invoice_email_day" class="form-control @error('invoice_email_day') is-invalid @enderror" value="{{ old('invoice_email_day') }}" min="1" max="31">
                @error('invoice_email_day')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="postal_address">Почтовый адрес</label>
                <input type="text" name="postal_address" id="postal_address" class="form-control @error('postal_address') is-invalid @enderror" value="{{ old('postal_address') }}">
                @error('postal_address')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="bank_postal_address">Банковский почтовый адрес</label>
                <input type="text" name="bank_postal_address" id="bank_postal_address" class="form-control @error('bank_postal_address') is-invalid @enderror" value="{{ old('bank_postal_address') }}">
                @error('bank_postal_address')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="contract">Договор</label>
                <input type="text" name="contract" id="contract" class="form-control @error('contract') is-invalid @enderror" value="{{ old('contract') }}" placeholder="Например, Договор аренды оборудования № 4 от 01.07.2024">
                @error('contract')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary">Создать</button>
            <a href="{{ route('carwash_clients.index') }}" class="btn btn-secondary">Назад</a>
        </form>
    </div>
@endsection
