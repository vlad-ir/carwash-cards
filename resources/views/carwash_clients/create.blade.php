@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Добавить клиента</h1>
        <form action="{{ route('carwash_clients.store') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label for="full_name" class="form-label">Полное имя</label>
                <input type="text" name="full_name" id="full_name" class="form-control @error('full_name') is-invalid @enderror" value="{{ old('full_name') }}">
                @error('full_name')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label for="short_name" class="form-label">Краткое имя</label>
                <input type="text" name="short_name" id="short_name" class="form-control @error('short_name') is-invalid @enderror" value="{{ old('short_name') }}">
                @error('short_name')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label for="unp" class="form-label">УНП</label>
                <input type="text" name="unp" id="unp" class="form-control @error('unp') is-invalid @enderror" value="{{ old('unp') }}">
                @error('unp')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label for="status" class="form-label">Статус</label>
                <select name="status" id="status" class="form-control @error('status') is-invalid @enderror">
                    <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Активен</option>
                    <option value="blocked" {{ old('status') == 'blocked' ? 'selected' : '' }}>Заблокирован</option>
                </select>
                @error('status')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label for="invoice_email_day" class="form-label">День отправки счета</label>
                <input type="number" name="invoice_email_day" id="invoice_email_day" class="form-control @error('invoice_email_day') is-invalid @enderror" value="{{ old('invoice_email_day') }}">
                @error('invoice_email_day')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label for="contract" class="form-label">Договор</label>
                <input type="text" name="contract" id="contract" class="form-control @error('contract') is-invalid @enderror" value="{{ old('contract') }}">
                @error('contract')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="{{ route('carwash_clients.index') }}" class="btn btn-secondary">Отмена</a>
        </form>
    </div>
@endsection
