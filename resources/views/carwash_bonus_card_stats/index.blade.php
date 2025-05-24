@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Статистика по бонусным картам</h1>
        <div class="mb-3">
            <a href="{{ route('carwash_bonus_card_stats.create') }}" class="btn btn-primary">Добавить запись</a>
            <a href="{{ route('carwash_bonus_card_stats.upload') }}" class="btn btn-success">Загрузить CSV</a>
            <button id="deleteSelected" class="btn btn-danger" disabled>Удалить выбранные</button>
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
                    <div class="mb-3">
                        <label for="import_date_filter" class="form-label">Дата импорта</label>
                        <input type="date" class="form-control" id="import_date_filter" name="import_date">
                    </div>
                    <div class="mb-3">
                        <label for="card_id_filter" class="form-label">Бонусная карта</label>
                        <select class="form-select" id="card_id_filter" name="card_id">
                            <option value="">Все карты</option>
                            @foreach(App\Models\CarwashBonusCard::orderBy('name')->get() as $card)
                                <option value="{{ $card->id }}">{{ $card->name }} ({{ $card->card_number }})</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Применить</button>
                    <button type="button" id="resetFilters" class="btn btn-secondary">Сбросить</button>
                </form>
            </div>
        </div>

        <table id="stats-table" class="table table-bordered">
            <thead>
            <tr>
                <th class="no-sort"><input type="checkbox" id="selectAll"></th>
                <th>Карта / Клиент</th>
                <th>Время начала</th>
                <th>Длительность</th>
                <th>Остаток</th>
                <th>Дата импорта</th>
                <th>Действия</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
        {{-- Удален div#selected-count --}}
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
                margin-right: 5px; /* Уменьшен отступ для компактности */
            }
            .action-buttons .btn i {
                margin: 0; /* Убираем лишние отступы у иконок */
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
                            d.card_id = $('#card_id_filter').val();
                            d.import_date = $('#import_date_filter').val(); // Фильтр по дате импорта
                        }
                    },
                    columns: [
                        { data: 'checkbox', orderable: false, searchable: false },
                        { data: 'card_details', name: 'card.name' },
                        { data: 'start_time', name: 'start_time' },
                        { data: 'duration_seconds', name: 'duration_seconds' },
                        { data: 'remaining_balance_seconds', name: 'remaining_balance_seconds' },
                        { data: 'import_date', name: 'import_date' },
                        { data: 'action', orderable: false, searchable: false }
                    ],
                    order: [[5, 'desc']], // Сортировка по "Дата импорта" (индекс 5)
                    initComplete: function () {
                        var api = this.api();

                        var $filterBtn = $('<button id="filter-btn" class="btn btn-secondary btn-sm btn-filter"><i class="fas fa-filter"></i> Фильтр</button>')
                            .on('click', function () {
                                $('#filterOffcanvas').offcanvas('show');
                            });

                        var $resetFilterBtn = $('<button id="filter-reset-btn" class="btn btn-danger btn-sm ms-1" title="Сбросить фильтры"><i class="fas fa-times"></i></button>')
                            .on('click', function () {
                                $('#start_time_filter').val('');
                                $('#card_id_filter').val('');
                                $('#import_date_filter').val(''); // Сброс даты импорта
                                table.draw();
                            });

                        var $filterContainer = $(api.table().container()).find('div.dataTables_filter');
                        $filterContainer.append($filterBtn).append($resetFilterBtn);

                        function hasActiveFilters() {
                            return $('#start_time_filter').val() !== '' ||
                                $('#card_id_filter').val() !== '' ||
                                $('#import_date_filter').val() !== ''; // Проверка даты импорта
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

                        $('#start_time_filter, #card_id_filter, #import_date_filter').on('change input', function () { // Включаем import_date_filter
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

                        // Логика подтверждения для одиночного удаления
                        $('.delete-single').off('click').on('click', function (e) {
                            e.preventDefault();
                            const form = $(this).closest('form');
                            const cardInfo = $(this).data('card-name')+ ' (' + $(this).data('card-number') + ')';
                            showConfirmModal(`Вы уверены, что хотите удалить запись для карты ${cardInfo}?`, function () {
                                form.submit();
                            });
                        });
                    }
                });

                function updateSelectedCount() {
                    let count = selectedIds.length;
                    let pageInfo = table.page.info(); // Получаем информацию о странице
                    // Формируем текст для info, как в carwash_clients
                    let infoText = `Выбрано записей: ${count} | Всего записей: ${pageInfo.recordsDisplay}`;
                    // Обновляем стандартный info блок DataTables
                    $(table.table().container()).find('.dataTables_info').html(infoText);
                    $('#deleteSelected').prop('disabled', count === 0);
                }


                $('#selectAll').on('change', function () {
                    let checked = this.checked;
                    $('.select-row').each(function () {
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

                $(document).on('change', '.select-row', function () {
                    let id = $(this).val();
                    if ($(this).is(':checked')) {
                        if (!selectedIds.includes(id)) selectedIds.push(id);
                    } else {
                        selectedIds = selectedIds.filter(item => item !== id);
                    }
                    $('#selectAll').prop('checked', $('.select-row:checked').length === $('.select-row').length && $('.select-row').length > 0);
                    $('#deleteSelected').prop('disabled', selectedIds.length === 0);
                    updateSelectedCount();
                });

                $('#deleteSelected').on('click', function () {
                    if (selectedIds.length === 0) return;

                    showConfirmModal(`Вы уверены, что хотите удалить ${selectedIds.length} записей статистики?`, function () {
                        $.ajax({
                            url: "{{ route('carwash_bonus_card_stats.deleteSelected') }}",
                            method: 'POST',
                            data: {
                                _token: "{{ csrf_token() }}",
                                ids: selectedIds
                            },
                            success: function (response) {
                                showToast('Успех', response.success, 'success'); // Используем response.success
                                selectedIds = [];
                                $('#deleteSelected').prop('disabled', true);
                                $('#selectAll').prop('checked', false); // Сбрасываем главный чекбокс
                                table.draw(); // Перерисовываем таблицу для обновления информации
                            },
                            error: function (xhr) {
                                let errorMsg = 'Ошибка при удалении записей статистики.';
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    errorMsg = xhr.responseJSON.message;
                                }
                                showToast('Ошибка', errorMsg, 'error');
                            }
                        });
                    });
                });

                $('#filterForm').on('submit', function (e) {
                    e.preventDefault();
                    table.draw();
                    $('#filterOffcanvas').offcanvas('hide');
                });

                $('#resetFilters').on('click', function () {
                    $('#start_time_filter').val('');
                    $('#card_id_filter').val('');
                    $('#import_date_filter').val('');
                    table.draw();
                    $('#filterOffcanvas').offcanvas('hide');
                });
            });
        </script>
    @endpush
@endsection
