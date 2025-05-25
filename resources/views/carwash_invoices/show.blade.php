@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Детали счета #{{ $invoice->id }}</h1>

        <div class="card">
            <div class="card-header">Информация о счете</div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">ID Счета</dt>
                    <dd class="col-sm-9">{{ $invoice->id }}</dd>

                    <dt class="col-sm-3">Клиент</dt>
                    <dd class="col-sm-9">
                        @if($invoice->client)
                            <a href="{{ route('carwash_clients.show', $invoice->client_id) }}">{{ $invoice->client->short_name }}</a>
                            (ID: {{ $invoice->client_id }})
                        @else
                            Клиент не найден
                        @endif
                    </dd>

                    <dt class="col-sm-3">Сумма</dt>
                    <dd class="col-sm-9">{{ number_format($invoice->amount, 2, ',', ' ') }} BYN</dd>

                    <dt class="col-sm-3">Период счета</dt>
                    <dd class="col-sm-9">{{ \Carbon\Carbon::parse($invoice->period_start)->isoFormat('MMMM YYYY') }} (с {{ \Carbon\Carbon::parse($invoice->period_start)->format('d.m.Y') }} по {{ \Carbon\Carbon::parse($invoice->period_end)->format('d.m.Y') }})</dd>

                    <dt class="col-sm-3">Дата формирования</dt>
                    <dd class="col-sm-9">{{ $invoice->sent_at ? \Carbon\Carbon::parse($invoice->sent_at)->format('d.m.Y H:i:s') : 'Не отправлен' }}</dd>

                    <dt class="col-sm-3">Всего карт в счете</dt>
                    <dd class="col-sm-9">{{ $invoice->total_cards_count ?? '-' }}</dd>

                    <dt class="col-sm-3">Активных карт в счете</dt>
                    <dd class="col-sm-9">{{ $invoice->active_cards_count ?? '-' }}</dd>

                    <dt class="col-sm-3">Заблокированных карт в счете</dt>
                    <dd class="col-sm-9">{{ $invoice->blocked_cards_count ?? '-' }}</dd>

                    <dt class="col-sm-3">Файл счета (XLS)</dt>
                    <dd class="col-sm-9">
                        @if ($invoice->download_url)
                            <a href="{{ $invoice->download_url }}" target="_blank" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-file-excel me-1"></i> Скачать XLS
                            </a>
                        @elseif($invoice->file_path)
                            Файл указан, но недоступен по публичной ссылке (возможно, ошибка конфигурации). Путь: <small>{{ $invoice->file_path }}</small>
                        @else
                            Файл не прикреплен или отсутствует.
                        @endif
                    </dd>

                    <dt class="col-sm-3">Создан</dt>
                    <dd class="col-sm-9">{{ $invoice->created_at->format('d.m.Y H:i:s') }}</dd>

                    <dt class="col-sm-3">Обновлен</dt>
                    <dd class="col-sm-9">{{ $invoice->updated_at->format('d.m.Y H:i:s') }}</dd>
                </dl>
            </div>
            <div class="card-footer">
                <form action="{{ route('carwash_invoices.destroy', $invoice->id) }}" method="POST" style="display:inline;" class="delete-invoice-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger delete-single" title="Удалить счет" data-invoice-id="{{ $invoice->id }}">
                        <i class="fas fa-trash me-1"></i>Удалить
                    </button>
                </form>
                <a href="{{ route('carwash_invoices.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Назад к списку
                </a>
            </div>
        </div>
    </div>

    {{-- Модальное окно подтверждения удаления (если не глобальное) --}}
    {{-- Предполагается, что showConfirmModal или подобное есть в layouts.app или глобальных скриптах.
         Если нет, то нужно добавить его сюда или в layouts.app.
         Для примера, если его нет, можно добавить простой вариант: --}}
    @if (!isset($__env->getSections()['modals'])) {{-- Пример условия, что модаль не определена глобально --}}
    @push('modals')
        <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="confirmDeleteModalLabel">Подтверждение удаления</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="confirmDeleteModalBody">
                        Вы уверены, что хотите удалить этот элемент?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteButton">Удалить</button>
                    </div>
                </div>
            </div>
        </div>
    @endpush
    @endif
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Логика для модального окна подтверждения удаления
            var formToSubmit;
            // Проверяем, существует ли модальное окно, перед инициализацией
            var confirmDeleteModalElement = document.getElementById('confirmDeleteModal');
            var confirmDeleteModal;
            if (confirmDeleteModalElement) {
                confirmDeleteModal = new bootstrap.Modal(confirmDeleteModalElement);
            }

            $(document).on('click', '.delete-single', function(e) { // Делегирование событий для динамически добавленных элементов (хотя здесь не нужно)
                e.preventDefault();
                if (!confirmDeleteModal) return; // Если модаль не найдена, ничего не делаем
                formToSubmit = $(this).closest('form');
                const invoiceId = $(this).data('invoice-id');
                $('#confirmDeleteModalBody').text(`Вы уверены, что хотите удалить счет #${invoiceId}?`);
                confirmDeleteModal.show();
            });

            $(document).on('click', '#confirmDeleteButton', function() {
                if (formToSubmit) {
                    formToSubmit.submit();
                }
            });
        });
    </script>
@endpush

@push('styles')
    {{-- Font Awesome (если не глобально) --}}
    @if (!isset($__env->getSections()['styles_fontawesome']))
        @push('styles_fontawesome')
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
        @endpush
    @endif
@endpush
