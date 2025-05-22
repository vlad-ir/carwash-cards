@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Статистика бонусных карт</h1>
        <div class="mb-3">
            <a href="{{ route('carwash_bonus_card_stats.create') }}" class="btn btn-primary">Добавить запись</a>
            <a href="{{ route('carwash_bonus_card_stats.upload') }}" class="btn btn-success">Загрузить CSV</a>
            <button id="delete-selected" class="btn btn-danger" disabled>Удалить выбранные</button>
        </div>

        <!-- Боковая панель фильтрации -->
        <div class="offcanvas offcanvas-end" tabindex="-1" id="filterOffcanvas" aria-labelledby="filterOffcanvasLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="filterOffcanvasLabel">Фильтры</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <form id="filterForm">
                    <div class="mb-3">
                        <label for="start_time_filter" class="form-label">Дата начала</label>
                        <input type="date" class="form-control" id="start_time_filter" name="start_time">
                    </div>
                    <button type="submit" class="btn btn-primary">Применить</button>
                    <button type="button" id="resetFilters" class="btn btn-secondary">Сбросить</button>
                </form>
            </div>
        </div>

        <table id="stats-table" class="table table-bordered">
            <thead>
            <tr>
                <th class="no-sort"><input type="checkbox" id="select-all"></th>
                <th>Номер карты</th>
                <th>Время начала</th>
                <th>Длительность (сек)</th>
                <th>Остаток (сек)</th>
                <th>Дата импорта</th>
                <th>Действия</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>

        <div id="selected-count" class="mt-3"></div>
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

                var table = $('#stats-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '{{ route('carwash_bonus_card_stats.data') }}',
                        data: function(d) {
                            d.start_time = $('#start_time_filter').val();
                        }
                    },
                    columns: [
                        { data: 'checkbox', orderable: false, searchable: false },
                        { data: 'card_number', name: 'card_number' },
                        { data: 'start_time', name: 'start_time' },
                        { data: 'duration_seconds', name: 'duration_seconds' },
                        { data: 'remaining_balance_seconds', name: 'remaining_balance_seconds' },
                        { data: 'import_date', name: 'import_date' },
                        { data: 'action', orderable: false, searchable: false }
                    ],
                    order: [[1, 'asc']],
                    initComplete: function () {
                        const api = this.api();

                        // Кнопка "Фильтр"
                        var $filterBtn = $('<button id="filter-btn" class="btn btn-secondary btn-sm btn-filter"><i class="fas fa-filter"></i> Фильтр</button>')
                            .on('click', function () {
                                $('#filterOffcanvas').offcanvas('show');
                            });

                        // Кнопка "Сбросить фильтры"
                        var $resetFilterBtn = $('<button id="filter-reset-btn" class="btn btn-danger btn-sm ms-1" title="Сбросить фильтры"><i class="fas fa-times"></i></button>')
                            .on('click', function () {
                                $('#start_time_filter').val('');
                                table.draw();
                            });

                        const $filterContainer = $(api.table().container()).find('div.dataTables_filter');
                        $filterContainer.append($filterBtn).append($resetFilterBtn);

                        // Проверяем, есть ли активные фильтры
                        function hasActiveFilters() {
                            return $('#start_time_filter').val() !== '';
                        }

                        function toggleResetButtonVisibility() {
                            if (hasActiveFilters()) {
                                $resetFilterBtn.show();
                                $filterBtn.removeClass('btn-secondary').addClass('btn-primary');
                            } else {
                                $resetFilterBtn.hide();
                                $filterBtn.removeClass('btn-primary').addClass('btn-secondary');
                            }
                        }

                        toggleResetButtonVisibility();

                        $('#start_time_filter').on('change input', function () {
                            toggleResetButtonVisibility();
                        });

                        table.on('draw', function () {
                            toggleResetButtonVisibility();
                        });
                    },
                    drawCallback: function () {
                        $('.select-row').each(function () {
                            $(this).prop('checked', selectedIds.includes($(this).val()));
                        });
                        updateSelectedCount();
                    }
                });

                function updateSelectedCount() {
                    const count = selectedIds.length;
                    const info = table.page.info();
                    $('#selected-count').text(`Выбрано записей: ${count} | Всего записей: ${info.recordsTotal}`);
                    $('#delete-selected').prop('disabled', count === 0);
                }

                $('#select-all').on('change', function () {
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
                    updateSelectedCount();
                });

                $('#stats-table').on('change', '.select-row', function () {
                    const id = $(this).val();
                    if ($(this).is(':checked')) {
                        if (!selectedIds.includes(id)) selectedIds.push(id);
                    } else {
                        selectedIds = selectedIds.filter(item => item !== id);
                    }
                    $('#select-all').prop('checked', $('.select-row:checked').length === $('.select-row').length);
                    updateSelectedCount();
                });

                $('#delete-selected').on('click', function () {
                    if (selectedIds.length === 0) return;

                    showConfirmModal(`Вы уверены, что хотите удалить ${selectedIds.length} записей?`, function () {
                        $.ajax({
                            url: "{{ route('carwash_bonus_card_stats.deleteSelected') }}",
                            method: 'POST',
                            data: {
                                _token: "{{ csrf_token() }}",
                                ids: selectedIds
                            },
                            success: function (response) {
                                showToast('Успех', response.success, 'success');
                                selectedIds = [];
                                table.draw();
                                updateSelectedCount();
                            },
                            error: function () {
                                showToast('Ошибка', 'Ошибка при удалении записей.', 'error');
                            }
                        });
                    });
                });

                // Применение фильтров
                $('#filterForm').on('submit', function(e) {
                    e.preventDefault();
                    table.draw();
                    $('#filterOffcanvas').offcanvas('hide');
                });

                // Сброс фильтров через кнопку формы
                $('#resetFilters').on('click', function () {
                    $('#start_time_filter').val('');
                    table.draw();
                    $('#filterOffcanvas').offcanvas('hide');
                });

                // Также обновляем состояние кнопки "Сбросить фильтры"
                function toggleResetButtonVisibility() {
                    const hasFilters = $('#start_time_filter').val() !== '';
                    $('#clear-filter-btn').toggle(hasFilters);
                    $('#filter-btn').toggleClass('btn-secondary btn-primary', hasFilters).toggleClass('btn-primary btn-secondary', !hasFilters);
                }
            });
        </script>
    @endpush
@endsection
