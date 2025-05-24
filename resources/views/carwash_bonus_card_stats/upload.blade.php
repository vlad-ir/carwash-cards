@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Загрузка CSV статистики</h1>

        @if (session('success'))
            <div class="alert alert-success mt-3">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('carwash_bonus_card_stats.upload') }}" method="POST" enctype="multipart/form-data" class="mt-3">
            @csrf
            <div class="mb-3">
                <label for="csv_file" class="form-label">Выберите CSV файл</label>
                <input type="file" name="csv_file" id="csv_file" class="form-control @error('csv_file') is-invalid @enderror" accept=".csv" required>
                @error('csv_file')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary">Загрузить</button>
            <a href="{{ route('carwash_bonus_card_stats.index') }}" class="btn btn-secondary">Отмена</a>
        </form>
    </div>
@endsection
