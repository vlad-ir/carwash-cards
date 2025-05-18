@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Клиенты</h1>

        <div class="mb-3">
            <a href="{{ route('carwash_clients.create') }}" class="btn btn-primary">Добавить клиента</a>
            <button id="deleteSelected" class="btn btn-danger" disabled>Удалить выбранные</button>
        </div>

        <table id="clientsTable" class="table table-bordered">
            <thead>
            <tr>
                <th class="no-sort"><input type="checkbox" id="selectAll"></th>
                <th>Краткое имя</th>
                <th>Email</th>
                <th>УНП</th>
                <th>Статус</th>
                <th>Счет на email</th>
                <th>День отправки счета</th>
                <th>Договор</th>
                <th>Действия</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>

        <!-- Боковая панель фильтрации -->
        <div class="offcanvas offcanvas-end" tabindex="-1" id="filterOffcanvas" aria-labelledby="filterOffcanvasLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="filterOffcanvasLabel">Фильтры</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <form id="filterForm">
                    <div class="mb-3">
                        <label for="name" class="form-label">Краткое имя</label>
                        <input type="text" name="name" id="name" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="unp" class="form-label">УНП</label>
                        <input type="text" name="unp" id="unp" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Статус</label>
                        <select name="status" id="status" class="form-control">
                            <option value="">Все</option>
                            <option value="active">Активен</option>
                            <option value="blocked">Заблокирован</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="invoice_email_required" class="form-label">Счет на email</label>
                        <select name="invoice_email_required" id="invoice_email_required" class="form-control">
                            <option value="">Все</option>
                            <option value="1">Да</option>
                            <option value="0">Нет</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Применить</button>
                    <button type="button" id="resetFilters" class="btn btn-secondary">Сбросить</button>
                </form>
            </div>
        </div>
    </div>

    @push('styles')
        <style>
            .no-sort,
            .no-sort:hover,
            .no-sort:active,
            .no-sort:focus {
                background-image: none !important;
                background: none !important;
                cursor: default !important;
                user-select: none !important;
            }
            .dataTables_filter {
                display: flex;
                align-items: center;
                justify-content: end;
            }
            .dataTables_filter .btn-filter {
                margin-left: 5px;
            }
            .action-buttons {
                display: flex;
            }
            .action-buttons .btn {
                margin-right: 5px;
            }
            .action-buttons .btn i {
                margin: 0;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            $(document).ready(function() {
                // Хранилище выбранных ID
                let selectedIds = [];

                // Инициализация DataTables
                var table = $('#clientsTable').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: "{{ url('carwash_clients') }}",
                        data: function(d) {
                            d.name = $('#name').val();
                            d.email = $('#email').val();
                            d.unp = $('#unp').val();
                            d.status = $('#status').val();
                            d.invoice_email_required = $('#invoice_email_required').val();
                        }
                    },
                    columns: [
                        { data: 'checkbox', orderable: false, searchable: false },
                        { data: 'short_name' },
                        { data: 'email' },
                        { data: 'unp', defaultContent: '-' },
                        {
                            data: 'status',
                            name: 'status',
                            render: function(data) {
                                return data === 'active'
                                    ? '<i class="fas fa-check text-success"></i>'
                                    : '<i class="fas fa-times text-danger"></i>';
                            },
                            orderData: [4]
                        },
                        {
                            data: 'invoice_email_required',
                            name: 'invoice_email_required',
                            render: function(data) {
                                return data == 1
                                    ? '<i class="fas fa-check text-success"></i>'
                                    : '<i class="fas fa-times text-danger"></i>';
                            },
                            orderData: [5]
                        },
                        { data: 'invoice_email_day', defaultContent: '-' },
                        { data: 'contract', defaultContent: '-' },
                        { data: 'action', orderable: false, searchable: false }
                    ],
                    order: [[1, 'asc']],
                    initComplete: function() {
                        var api = this.api();
                        var $filterBtn = $('<button id="filter-btn" class="btn btn-secondary btn-sm btn-filter"><i class="fas fa-filter"></i> Фильтр</button>')
                            .on('click', function() {
                                $('#filterOffcanvas').offcanvas('show');
                            });
                        $(api.table().container()).find('div.dataTables_filter').append($filterBtn);
                    },
                    drawCallback: function() {
                        $('.select-row').each(function() {
                            $(this).prop('checked', selectedIds.includes($(this).val()));
                        });
                        updateSelectedCount();

                        $('.delete-single').off('click').on('click', function(e) {
                            e.preventDefault();
                            const form = $(this).closest('form');
                            const shortName = $(this).data('short-name');
                            showConfirmModal(`Вы уверены, что хотите удалить клиента ${shortName}?`, function() {
                                form.submit();
                            });
                        });
                    }
                });

                // Обновление количества выбранных записей
                function updateSelectedCount() {
                    let count = selectedIds.length;
                    let info = table.page.info();
                    let statusText = `Выбрано записей: ${count}`;
                    $('.dataTables_info').text(`${statusText} | Всего записей: ${info.recordsTotal}`);
                }

                // Обработка формы фильтрации
                $('#filterForm').on('submit', function(e) {
                    e.preventDefault();
                    table.draw();
                    $('#filterOffcanvas').offcanvas('hide');
                });

                // Сброс фильтров
                $('#resetFilters').on('click', function() {
                    $('#name, #email, #unp, #status, #invoice_email_required').val('');
                    table.draw();
                    $('#filterOffcanvas').offcanvas('hide');
                });

                // Выбор всех чекбоксов
                $('#selectAll').on('change', function() {
                    let checked = this.checked;
                    $('.select-row').each(function() {
                        let id = $(this).val();
                        if (checked && !selectedIds.includes(id)) {
                            selectedIds.push(id);
                        } else if (!checked && selectedIds.includes(id)) {
                            selectedIds = selectedIds.filter(item => item !== id);
                        }
                        $(this).prop('checked', checked);
                    });
                    $('#deleteSelected').prop('disabled', selectedIds.length === 0);
                    updateSelectedCount();
                });

                // Выбор отдельных чекбоксов
                $(document).on('change', '.select-row', function() {
                    let id = $(this).val();
                    if ($(this).is(':checked')) {
                        if (!selectedIds.includes(id)) {
                            selectedIds.push(id);
                        }
                    } else {
                        selectedIds = selectedIds.filter(item => item !== id);
                    }
                    $('#selectAll').prop('checked', $('.select-row:checked').length === $('.select-row').length);
                    $('#deleteSelected').prop('disabled', selectedIds.length === 0);
                    updateSelectedCount();
                });

                // Удаление выбранных клиентов
                $('#deleteSelected').on('click', function() {
                    if (selectedIds.length === 0) {
                        return;
                    }
                    showConfirmModal(`Вы уверены, что хотите удалить ${selectedIds.length} клиента(ов)?`, function() {
                        $.ajax({
                            url: "{{ route('carwash_clients.deleteSelected') }}",
                            method: 'POST',
                            data: {
                                _token: "{{ csrf_token() }}",
                                ids: selectedIds
                            },
                            success: function(response) {
                                showToast('Успех', response.success, 'success');
                                selectedIds = [];
                                $('#deleteSelected').prop('disabled', true);
                                table.draw();
                            },
                            error: function() {
                                showToast('Ошибка', 'Ошибка при удалении клиентов.', 'error');
                            }
                        });
                    });
                });
            });
        </script>
    @endpush
@endsection
