@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Загрузка CSV-файла</h1>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('carwash_bonus_card_stats.upload') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label for="csv_file">Выберите CSV-файл</label>
                <input type="file" name="csv_file" id="csv_file" class="form-control-file" accept=".csv" required>
            </div>
            <button type="submit" class="btn btn-primary">Загрузить</button>
            <a href="{{ route('carwash_bonus_card_stats.index') }}" class="btn btn-secondary">Назад</a>
        </form>
    </div>
@endsection
