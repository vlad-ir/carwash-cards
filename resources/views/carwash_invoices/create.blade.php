@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Создать счет</h1>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('carwash_invoices.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="client_id">Клиент</label>
                <select name="client_id" id="client_id" class="form-control" required>
                    <option value="">Выберите клиента</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="period_start">Начало периода</label>
                <input type="date" name="period_start" id="period_start" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="period_end">Конец периода</label>
                <input type="date" name="period_end" id="period_end" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Создать</button>
            <a href="{{ route('carwash_invoices.index') }}" class="btn btn-secondary">Назад</a>
        </form>
    </div>
@endsection
