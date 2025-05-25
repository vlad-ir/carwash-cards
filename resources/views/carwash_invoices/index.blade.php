@extends('layouts.app') {{-- Assuming you have a layouts.app or similar --}}

@section('content')
    <div class="container-fluid">
        <h1 class="mt-4 mb-4">Счета на оплату</h1>

        {{-- Filter Form --}}
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-filter me-1"></i>
                Фильтры
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('carwash_invoices.index') }}">
                    <div class="row align-items-end">
                        <div class="col-md-3 mb-3">
                            <label for="client_name" class="form-label">Краткое название клиента</label>
                            <input type="text" class="form-control form-control-sm" id="client_name" name="client_name" value="{{ $filters['client_name'] ?? '' }}">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="period_start" class="form-label">Начало периода (месяц)</label>
                            <input type="month" class="form-control form-control-sm" id="period_start" name="period_start" value="{{ isset($filters['period_start']) && !empty($filters['period_start']) ? \Carbon\Carbon::parse($filters['period_start'])->format('Y-m') : '' }}">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="period_end" class="form-label">Конец периода (месяц)</label>
                            <input type="month" class="form-control form-control-sm" id="period_end" name="period_end" value="{{ isset($filters['period_end']) && !empty($filters['period_end']) ? \Carbon\Carbon::parse($filters['period_end'])->format('Y-m') : '' }}">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="invoice_date" class="form-label">Дата счета</label>
                            <input type="date" class="form-control form-control-sm" id="invoice_date" name="invoice_date" value="{{ $filters['invoice_date'] ?? '' }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i>Применить</button>
                            <a href="{{ route('carwash_invoices.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-times me-1"></i>Сбросить</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Invoices Table --}}
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-file-invoice-dollar me-1"></i>
                Список счетов
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="table-light">
                        <tr>
                            <th>ID Клиента</th>
                            <th>Название клиента</th>
                            <th>Начало периода</th>
                            <th>Конец периода</th>
                            <th>Всего карт</th>
                            <th>Активных карт</th>
                            <th>Блок. карт</th>
                            <th>Дата счета</th>
                            <th>Файл счета</th>
                            <th style="width: 120px;">Действия</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->client_id }}</td>
                                <td>
                                    @if($invoice->client)
                                        <a href="{{ route('carwash_clients.show', $invoice->client_id) }}">{{ $invoice->client->short_name }}</a>
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td>{{ \Carbon\Carbon::parse($invoice->period_start)->isoFormat('MMMM YYYY') }}</td>
                                <td>{{ \Carbon\Carbon::parse($invoice->period_end)->isoFormat('MMMM YYYY') }}</td>
                                <td>{{ $invoice->total_cards_count }}</td>
                                <td>{{ $invoice->active_cards_count }}</td>
                                <td>{{ $invoice->blocked_cards_count }}</td>
                                <td>{{ \Carbon\Carbon::parse($invoice->sent_at)->format('d.m.Y') }}</td>
                                <td>
                                    @if ($invoice->file_path)
                                        @php
                                            $fileName = basename($invoice->file_path);
                                            $publicUrl = Illuminate\Support\Facades\Storage::disk('public')->url('invoices/' . $fileName);
                                        @endphp
                                        @if (Illuminate\Support\Facades\Storage::disk('public')->exists('invoices/' . $fileName))
                                            <a href="{{ $publicUrl }}" target="_blank" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-file-excel me-1"></i> Скачать XLS
                                            </a>
                                        @else
                                            <span class="text-muted">Файл не найден</span>
                                        @endif
                                    @else
                                        <span class="text-muted">Нет файла</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('carwash_invoices.show', $invoice->id) }}" class="btn btn-info btn-sm" title="Просмотр">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <form action="{{ route('carwash_invoices.destroy', $invoice->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Вы уверены, что хотите удалить этот счет #{{ $invoice->id }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" title="Удалить">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center">Счета не найдены. Попробуйте изменить критерии фильтрации.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Links --}}
                @if ($invoices->hasPages())
                    <div class="mt-3 d-flex justify-content-center">
                        {{ $invoices->appends(request()->query())->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('styles')
    {{-- Font Awesome for icons (if not already included in layouts.app) --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .form-label {
            font-weight: 500;
        }
        .table th {
            font-weight: 600;
        }
    </style>
@endpush

@push('scripts')
    {{-- Add any specific scripts for this page if needed, e.g., for date pickers or DataTables if used --}}
@endpush
