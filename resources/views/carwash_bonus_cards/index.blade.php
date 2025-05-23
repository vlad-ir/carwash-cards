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
                <th>Клиент</th>
                <th>Ставка за минуту, BYN</th>
                <th>Статус</th>
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
                        <label for="name_filter" class="form-label">Название</label>
                        <input type="text" name="name" id="name_filter" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="card_number_filter" class="form-label">Номер карты</label>
                        <input type="text" name="card_number" id="card_number_filter" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="client_short_name_filter" class="form-label">Краткое имя клиента</label>
                        <input type="text" name="client_short_name" id="client_short_name_filter" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="status_filter" class="form-label">Статус</label>
                        <select name="status" id="status_filter" class="form-control">
                            <option value="">Все</option>
                            <option value="active">Активна</option>
                            <option value="blocked">Заблокирована</option>
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
            $(document).ready(function () {
                let selectedIds = [];

                const table = $('#bonusCardsTable').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '{{ route('carwash_bonus_cards.data') }}',
                        data: function (d) {
                            d.name = $('#name_filter').val();
                            d.card_number = $('#card_number_filter').val();
                            d.client_short_name = $('#client_short_name_filter').val();
                            d.status = $('#status_filter').val();
                        }
                    },
                    columns: [
                        { data: 'checkbox', orderable: false, searchable: false },
                        { data: 'name' },
                        { data: 'card_number' },
                        { data: 'client_short_name' },
                        { data: 'rate_per_minute' },
                        {
                            data: 'status',
                            render: function (data) {
                                return data === 'active'
                                    ? '<i class="fas fa-check text-success"></i>'
                                    : data === 'blocked'
                                        ? '<i class="fas fa-ban text-danger"></i>'
                                        : '<i class="fas fa-pause text-muted"></i>';
                            }
                        },
                        { data: 'action', orderable: false, searchable: false }
                    ],
                    order: [[1, 'asc']],
                    initComplete: function () {
                        var api = this.api();

                        // Кнопка "Фильтр"
                        var $filterBtn = $('<button id="filter-btn" class="btn btn-secondary btn-sm btn-filter"><i class="fas fa-filter"></i> Фильтр</button>')
                            .on('click', function () {
                                $('#filterOffcanvas').offcanvas('show');
                            });

                        // Кнопка "Сбросить фильтры" (по умолчанию скрыта)
                        var $resetFilterBtn = $('<button id="filter-reset-btn" class="btn btn-danger btn-sm ms-1" title="Сбросить фильтры"><i class="fas fa-times"></i></button>')
                            .on('click', function () {
                                $('#name_filter, #card_number_filter, #client_short_name_filter, #status_filter').val('');
                                table.draw();
                            });

                        // Добавляем кнопки к фильтру DataTables
                        var $filterContainer = $(api.table().container()).find('div.dataTables_filter');
                        $filterContainer.append($filterBtn).append($resetFilterBtn);

                        // Проверяем, есть ли заполненные фильтры
                        function hasActiveFilters() {
                            return $('#name_filter, #card_number_filter, #client_short_name_filter, #status_filter')
                                .filter(function () { return $(this).val(); }).length > 0;
                        }

                        // Обновляем видимость кнопки "Сбросить фильтры"
                        function toggleResetButtonVisibility() {
                            if (hasActiveFilters()) {
                                $resetFilterBtn.show();
                                $filterBtn.removeClass('btn-secondary').addClass('btn-primary');
                            } else {
                                $resetFilterBtn.hide();
                                $filterBtn.removeClass('btn-primary').addClass('btn-secondary');
                            }
                        }

                        // Вызываем проверку при загрузке
                        toggleResetButtonVisibility();

                        // При применении фильтров через форму — обновляем статус кнопки
                        $('#filterForm').on('submit', function () {
                            setTimeout(toggleResetButtonVisibility, 100);
                        });

                        // При изменении любого поля фильтрации — обновляем статус кнопки
                        $('#name_filter, #card_number_filter, #client_short_name_filter, #status_filter').on('change input', function () {
                            toggleResetButtonVisibility();
                        });

                        // Также проверяем после перерисовки таблицы
                        table.on('draw', function () {
                            toggleResetButtonVisibility();
                        });
                    },
                    drawCallback: function () {
                        $('.select-row').each(function () {
                            $(this).prop('checked', selectedIds.includes($(this).val()));
                        });
                        updateSelectedCount();

                        $('.delete-single').off('click').on('click', function (e) {
                            e.preventDefault();
                            const form = $(this).closest('form');
                            const cardName = $(this).data('card-name');
                            const cardNumber = $(this).data('card-number');
                            showConfirmModal(`Вы уверены, что хотите удалить бонусную карту ${cardName} (${cardNumber})?`, function () {
                                form.submit();
                            });
                        });
                    }
                });

                function updateSelectedCount() {
                    const count = selectedIds.length;
                    const info = table.page.info();
                    const statusText = `Выбрано записей: ${count}`;
                    $('.dataTables_info').text(`${statusText} | Всего записей: ${info.recordsTotal}`);
                }

                // Применение фильтров
                $('#filterForm').on('submit', function(e) {
                    e.preventDefault();
                    table.draw();
                    $('#filterOffcanvas').offcanvas('hide');
                });

                // Сброс фильтров
                $('#resetFilters').on('click', function() {
                    $('#name_filter, #card_number_filter, #client_short_name_filter, #status_filter').val('');
                    table.draw();
                    $('#filterOffcanvas').offcanvas('hide');
                });

                $('#selectAll').on('change', function () {
                    const checked = this.checked;
                    $('.select-row').each(function () {
                        const id = $(this).val();
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

                $(document).on('change', '.select-row', function () {
                    const id = $(this).val();
                    if ($(this).is(':checked')) {
                        if (!selectedIds.includes(id)) selectedIds.push(id);
                    } else {
                        selectedIds = selectedIds.filter(item => item !== id);
                    }
                    $('#selectAll').prop('checked', $('.select-row:checked').length === $('.select-row').length);
                    $('#deleteSelected').prop('disabled', selectedIds.length === 0);
                    updateSelectedCount();
                });

                $('#deleteSelected').on('click', function () {
                    if (selectedIds.length === 0) return;

                    showConfirmModal(`Вы уверены, что хотите удалить ${selectedIds.length} бонусных карт(ы)?`, function () {
                        $.ajax({
                            url: "{{ route('carwash_bonus_cards.deleteSelected') }}",
                            method: 'POST',
                            data: {
                                _token: "{{ csrf_token() }}",
                                ids: selectedIds
                            },
                            success: function (response) {
                                showToast('Успех', response.success, 'success');
                                selectedIds = [];
                                $('#deleteSelected').prop('disabled', true);
                                table.draw();
                            },
                            error: function () {
                                showToast('Ошибка', 'Ошибка при удалении бонусных карт.', 'error');
                            }
                        });
                    });
                });
            });
        </script>
    @endpush
@endsection
