@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Клиенты</h1>
        <div class="mb-3">
            <a href="{{ route('carwash_clients.create') }}" class="btn btn-primary">Добавить клиента</a>
            <button id="deleteSelected" class="btn btn-danger" disabled>Удалить выбранные</button>
        </div>

        <table id="clientsTable" class="table table-bordered table-hover dataTable no-footer">
            <thead class="bg-light">
            <tr>
                <th class="no-sort"><input type="checkbox" id="selectAll"></th>
                <th>Краткое имя</th>
                <th>Email</th>
                <th>УНП</th>
                <th>Статус</th>
                <th>Счет на email</th>
                <th>День отправки счета</th>
                <th>Кол-во карт</th>
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

    @push('scripts')
        <script>
            $(document).ready(function () {
                let selectedIds = [];
                let allRecordsGloballySelected = false;
                let openedRow = null;

                var table = $('#clientsTable').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '{{ route('carwash_clients.data') }}',
                        data: function (d) {
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
                            className: 'text-center',
                            render: function (data) {
                                return data === 'active'
                                    ? '<i class="fas fa-check text-success"></i>'
                                    : '<i class="fas fa-ban text-danger"></i>';
                            }
                        },
                        {
                            data: 'invoice_email_required',
                            className: 'text-center',
                            render: function (data) {
                                return data == 1
                                    ? '<i class="fas fa-check text-success"></i>'
                                    : '<i class="fas fa-ban text-danger"></i>';
                            }
                        },
                        { data: 'invoice_email_day', defaultContent: '-', className: 'text-center' },
                        {
                            data: 'bonus_cards_count',
                            orderable: true,
                            searchable: false,
                            className: 'sub-table-arr text-center',
                            defaultContent: '0',
                            render: function (data, type, row) {
                                if (type === 'display' && data > 0) {
                                    return '<button class="btn btn-sm btn-outline-primary" title="Показать/скрыть список карт">'+ data + ' <i class="fas toggle-details fa-chevron-right"></i></button>';
                                }
                                return data;
                            }
                        },
                        { data: 'contract', defaultContent: '-' },
                        { data: 'action', orderable: false, searchable: false }
                    ],
                    order: [[1, 'asc']],
                    initComplete: function () {
                        const api = this.api();
                        const $filterBtn = $('<button id="filter-btn" class="btn btn-secondary btn-sm btn-filter"><i class="fas fa-filter"></i> Фильтр</button>')
                            .on('click', function () {
                                $('#filterOffcanvas').offcanvas('show');
                            });
                        const $resetFilterBtn = $('<button id="filter-reset-btn" class="btn btn-danger btn-sm ms-1" title="Сбросить фильтры"><i class="fas fa-times"></i></button>')
                            .on('click', function () {
                                $('#name, #email, #unp, #status, #invoice_email_required').val('');
                                table.draw();
                            });

                        const $filterContainer = $(api.table().container()).find('div.dataTables_filter');
                        $filterContainer.append($filterBtn).append($resetFilterBtn);

                        function hasActiveFilters() {
                            return $('#name, #email, #unp, #status, #invoice_email_required').filter(function () {
                                return $(this).val();
                            }).length > 0;
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

                        $('#filterForm').on('submit', function () {
                            setTimeout(toggleResetButtonVisibility, 100);
                        });

                        $('#name, #email, #unp, #status, #invoice_email_required').on('change input', function () {
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
                        updateSelectAllCheckboxState();
                        $('.delete-single').off('click').on('click', function (e) {
                            e.preventDefault();
                            const form = $(this).closest('form');
                            const shortName = $(this).data('short-name');
                            showConfirmModal(`Вы уверены, что хотите удалить клиента ${shortName}?`, function () {
                                form.submit();
                            });
                        });
                    }
                });

                function updateSelectedCount() {
                    let count = selectedIds.length;
                    let info = table.page.info();
                    let statusText = `Выбрано записей: ${count}`;
                    $('.dataTables_info').text(`${statusText} | Всего записей: ${info.recordsTotal}`);
                }

                function updateSelectAllCheckboxState() {
                    if (allRecordsGloballySelected) {
                        $('#selectAll').prop('indeterminate', false).prop('checked', true);
                        return;
                    }
                    let allVisibleRows = table.rows({ page: 'current' }).nodes().to$().find('.select-row');
                    if (allVisibleRows.length === 0) {
                        $('#selectAll').prop('indeterminate', false).prop('checked', false);
                        return;
                    }
                    let allVisibleChecked = allVisibleRows.filter(':checked').length;
                    let allVisibleCount = allVisibleRows.length;
                    if (allVisibleChecked === 0) {
                        if (selectedIds.length > 0) {
                            $('#selectAll').prop('indeterminate', true).prop('checked', false);
                        } else {
                            $('#selectAll').prop('indeterminate', false).prop('checked', false);
                        }
                    } else if (allVisibleChecked === allVisibleCount) {
                        if (selectedIds.length > allVisibleChecked && !allRecordsGloballySelected) {
                            $('#selectAll').prop('indeterminate', true).prop('checked', false);
                        } else {
                            $('#selectAll').prop('indeterminate', false).prop('checked', true);
                        }
                    } else {
                        $('#selectAll').prop('indeterminate', true).prop('checked', false);
                    }
                }

                $('#filterForm').on('submit', function (e) {
                    e.preventDefault();
                    table.draw();
                    $('#filterOffcanvas').offcanvas('hide');
                });

                $('#resetFilters').on('click', function () {
                    $('#name, #email, #unp, #status, #invoice_email_required').val('');
                    table.draw();
                    $('#filterOffcanvas').offcanvas('hide');
                });

                $('#selectAll').on('change', function () {
                    let isChecked = $(this).prop('checked');
                    if (isChecked) {
                        $.ajax({
                            url: "{{ route('carwash_clients.get_all_ids') }}",
                            method: 'GET',
                            data: {
                                name: $('#name').val(),
                                email: $('#email').val(),
                                unp: $('#unp').val(),
                                status: $('#status').val(),
                                invoice_email_required: $('#invoice_email_required').val()
                            },
                            success: function (response) {
                                selectedIds = response.ids.map(id => String(id));
                                allRecordsGloballySelected = true;
                                $('.select-row').prop('checked', true);
                                updateSelectedCount();
                                updateSelectAllCheckboxState();
                                $('#deleteSelected').prop('disabled', selectedIds.length === 0);
                            },
                            error: function (xhr) {
                                console.error("Ошибка при получении всех ID клиентов:", xhr);
                                alert('Не удалось получить все ID для выбора клиентов.');
                                $('#selectAll').prop('checked', false);
                                allRecordsGloballySelected = false;
                            }
                        });
                    } else {
                        selectedIds = [];
                        allRecordsGloballySelected = false;
                        $('.select-row').prop('checked', false);
                        updateSelectedCount();
                        updateSelectAllCheckboxState();
                        $('#deleteSelected').prop('disabled', true);
                    }
                });

                $(document).on('change', '.select-row', function () {
                    allRecordsGloballySelected = false;
                    let id = $(this).val();
                    if ($(this).prop('checked')) {
                        if (!selectedIds.includes(id)) {
                            selectedIds.push(id);
                        }
                    } else {
                        selectedIds = selectedIds.filter(item => item !== id);
                    }
                    updateSelectedCount();
                    updateSelectAllCheckboxState();
                    $('#deleteSelected').prop('disabled', selectedIds.length === 0);
                });

                $('#deleteSelected').on('click', function () {
                    if (selectedIds.length === 0) return;

                    showConfirmModal(`Вы уверены, что хотите удалить выбранные записи: ${selectedIds.length} шт.?`, function () {
                        $.ajax({
                            url: "{{ route('carwash_clients.deleteSelected') }}",
                            method: 'POST',
                            data: {
                                _token: "{{ csrf_token() }}",
                                ids: selectedIds
                            },
                            success: function (response) {
                                showToast('Успех', response.success, 'success');
                                selectedIds = [];
                                allRecordsGloballySelected = false; // Added line
                                $('#deleteSelected').prop('disabled', true);
                                table.draw();
                            },
                            error: function () {
                                showToast('Ошибка', 'Ошибка при удалении клиентов.', 'error');
                            }
                        });
                    });
                });


                // Обработчик клика для открытия подтаблицы
                $('#clientsTable tbody').on('click', 'td.sub-table-arr', function () {
                    const tr = $(this).closest('tr');
                    const row = table.row(tr);

                    if (row.child.isShown()) {
                        // Закрываем уже открытую подтаблицу
                        tr.find('.sub-table-arr .btn').removeClass('btn-primary').addClass('btn-outline-primary');
                        tr.find('i.toggle-details').removeClass('fa-chevron-down').addClass('fa-chevron-right');
                        row.child.hide();
                        openedRow = null;
                    } else {
                        // Если есть другая открытая строка — закрываем её
                        if (openedRow) {
                            const prevTr = $(openedRow.node());
                            prevTr.find('.sub-table-arr .btn').removeClass('btn-primary').addClass('btn-outline-primary');
                            prevTr.find('i.toggle-details').removeClass('fa-chevron-down').addClass('fa-chevron-right');
                            openedRow.child.hide();
                        }

                        const clientId = row.data().id;
                        if (!clientId) {
                            console.error('ID клиента не найден.');
                            return;
                        }

                        const detailContainerId = `clientBonusCardsTable_${clientId}`;

                        // Показываем спинер
                        row.child(`<div style="padding:10px;display: flex;justify-content: center;">
                                      <i class="fas fa-spinner custom-spin fa-2x"></i>
                                      <span style="vertical-align:middle; margin-left:10px;">Загрузка данных...</span>
                                   </div>`).show();

                        tr.find('.sub-table-arr .btn').removeClass('btn-outline-primary').addClass('btn-primary');
                        tr.find('i.toggle-details').removeClass('fa-chevron-right').addClass('fa-chevron-down');

                        openedRow = row;

                        // URL для запроса
                        const url = '{{ route('carwash_clients.bonus_cards_data', ':clientId') }}'.replace(':clientId', clientId);

                        // Выполняем AJAX-запрос вручную
                        $.ajax({
                            url: url,
                            method: 'GET',
                            dataType: 'json',
                            success: function (response) {
                                // Удаляем спинер и вставляем таблицу
                                const tableHtml = `
                                <table id="${detailContainerId}" class="table table-sm table-bordered" style="width:100%; font-size: small;">
                                    <thead>
                                        <tr>
                                            <th>Название карты</th>
                                            <th>Номер карты</th>
                                            <th>Цена за минуту, BYN</th>
                                            <th class="text-center">Статус</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${response.data.map(card => `
                                            <tr>
                                                <td>${card.name || '-'}</td>
                                                <td>${card.card_number || '-'}</td>
                                                <td>${card.rate_per_minute || '-'}</td>
                                                <td class="text-center">${card.status || '-'}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            `;

                                row.child(tableHtml).show();
                            },
                            error: function (xhr) {
                                console.error("Ошибка при загрузке данных бонусных карт:", xhr);
                                row.child(`
                                    <div class="text-danger text-center" style="padding: 10px;">
                                        Не удалось загрузить данные.
                                    </div>
                                `).show();
                            }
                        });
                    }
                });

            });

        </script>
    @endpush
@endsection
