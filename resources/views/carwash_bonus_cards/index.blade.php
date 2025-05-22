@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Бонусные карты</h1>

        <div class="mb-3">
            <a href="{{ route('carwash_bonus_cards.create') }}" class="btn btn-primary">Добавить карту</a>
            <button id="deleteSelected" class="btn btn-danger" disabled>Удалить выбранные</button>
        </div>

        <table id="bonusCardsTable" class="table table-bordered">
            <thead>
            <tr>
                <th class="no-sort"><input type="checkbox" id="selectAll"></th>
                <th>Название</th>
                <th>Номер карты</th>
                <th>Скидка (%)</th>
                <th>Баланс</th>
                <th>Статус</th>
                <th>Клиент</th>
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
                var table = $('#bonusCardsTable').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '{{ route('carwash_bonus_cards.data') }}',
                        data: function(d) {
                            d.name = $('#name_filter').val();
                            d.card_number = $('#card_number_filter').val();
                            d.car_license_plate = $('#car_license_plate_filter').val();
                            d.client_short_name = $('#client_short_name_filter').val();
                        }
                    },
                    columns: [
                        { data: 'checkbox', name: 'checkbox', orderable: false, searchable: false },
                        { data: 'name', name: 'name' },
                        { data: 'card_number', name: 'card_number' },
                        { data: 'discount_percentage', name: 'discount_percentage' },
                        { data: 'balance', name: 'balance' },
                        { data: 'status', name: 'status' },
                        { data: 'client_short_name', name: 'client_short_name' },
                        { data: 'action', name: 'action', orderable: false, searchable: false }
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
                            const cardName = $(this).data('name');
                            showConfirmModal(`Вы уверены, что хотите удалить бонусную карту ${cardName}?`, function() {
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
                            url: "{{ route('carwash_bonus_cards.deleteSelected') }}",
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
                                showToast('Ошибка', 'Ошибка при удалении бонусных карт.', 'error');
                            }
                        });
                    });
                });
            });
        </script>
    @endpush
@endsection
