@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Счет #{{ $invoice->id }}</h1>
        <div class="card">
            <div class="card-body">
                <p><strong>Клиент:</strong> {{ $invoice->client->name }}</p>
                <p><strong>Сумма:</strong> {{ number_format($invoice->amount, 2) }} руб.</p>
                <p><strong>Период:</strong> {{ $invoice->period_start->format('d.m.Y') }} - {{ $invoice->period_end->format('d.m.Y') }}</p>
                <p><strong>Отправлен:</strong> {{ $invoice->sent_at ? $invoice->sent_at->format('d.m.Y H:i') : 'Не отправлен' }}</p>
                @if ($invoice->pdf_path)
                    <p><a href="{{ Storage::url($invoice->pdf_path) }}" class="btn btn-primary" target="_blank">Скачать PDF</a></p>
                @endif
            </div>
        </div>
        <a href="{{ route('carwash_invoices.index') }}" class="btn btn-secondary mt-3">Назад</a>
    </div>
@endsection
