@extends('layouts.app')
@section('content')
    <div class="container">
        <h1>Счета на оплату</h1>
        <div class="mb-3">
            <button id="deleteSelectedInvoices" class="btn btn-danger" disabled>Удалить выбранные</button>
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
                        <label for="client_name" class="form-label">Краткое название клиента</label>
                        <input type="text" name="client_name" id="client_name" class="form-control form-control-sm">
                    </div>
                    <div class="mb-3">
                        <label for="period_start" class="form-label">Начало периода (месяц)</label>
                        <input type="month" name="period_start" id="period_start" class="form-control form-control-sm">
                    </div>
                    <div class="mb-3">
                        <label for="period_end" class="form-label">Конец периода (месяц)</label>
                        <input type="month" name="period_end" id="period_end" class="form-control form-control-sm">
                    </div>
                    <div class="mb-3">
                        <label for="invoice_date" class="form-label">Дата счета</label>
                        <input type="date" name="invoice_date" id="invoice_date" class="form-control form-control-sm">
                    </div>
                    <button type="submit" class="btn btn-primary">Применить</button>
                    <button type="button" id="resetFilters" class="btn btn-secondary">Сбросить</button>
                </form>
            </div>
        </div>

        <!-- Таблица счетов -->
        <table id="invoicesTable" class="table table-bordered table-hover dataTable no-footer">
            <thead class="bg-light">
            <tr>
                <th class="no-sort text-center" style="width: 30px;">
                    <input type="checkbox" id="selectAllInvoices">
                </th>
                <th>ID Клиента</th>
                <th>Название клиента</th>
                <th>Начало периода</th>
                <th>Конец периода</th>
                <th>Всего карт</th>
                <th>Активных карт</th>
                <th>Блок. карт</th>
                <th>Дата счета</th>
                <th>Статус отправки</th>
                <th>Файл счета</th>
                <th class="no-sort text-center" style="width: 150px;">Действия</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <x-reissue-invoice-modal />
    <x-send-email-modal />

    @push('scripts')
        <script>
            $(document).ready(function () {
                let selectedInvoiceIds = [];
                let allInvoicesGloballySelected = false;

                const invoicesTable = $('#invoicesTable').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: "{{ route('carwash_invoices.data') }}",
                        data: function (d) {
                            d.client_name = $('#client_name').val();
                            d.period_start = $('#period_start').val();
                            d.period_end = $('#period_end').val();
                            d.invoice_date = $('#invoice_date').val();
                        }
                    },
                    columns: [
                        {data: 'checkbox', name: 'checkbox', orderable: false, searchable: false, className: 'text-center'},
                        {data: 'client_id', name: 'client_id'},
                        {data: 'client_short_name', name: 'client.short_name'},
                        {data: 'period_start', name: 'period_start'},
                        {data: 'period_end', name: 'period_end'},
                        {data: 'total_cards_count', name: 'total_cards_count'},
                        {data: 'active_cards_count', name: 'active_cards_count'},
                        {data: 'blocked_cards_count', name: 'blocked_cards_count'},
                        {data: 'sent_at', name: 'sent_at'}, // Дата формирования файла
                        {data: 'sent_to_email_at', name: 'sent_to_email_at'}, // Дата отправки на email
                        {data: 'file_link', name: 'file_link', orderable: false, searchable: false},
                        {data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center'}
                    ],
                    order: [[8, 'desc']], // Сортировка по дате счета (sent_at)
                    initComplete: function () {
                        var api = this.api();

                        // Кнопка "Фильтр"
                        var $filterBtn = $('<button id="filter-btn" class="btn btn-secondary btn-sm ms-2"><i class="fas fa-filter"></i> Фильтр</button>')
                            .on('click', function () {
                                $('#filterOffcanvas').offcanvas('show');
                            });

                        // Кнопка "Сбросить фильтры"
                        var $resetFilterBtnGlobal = $('<button id="filter-reset-btn" class="btn btn-danger btn-sm ms-1" title="Сбросить фильтры"><i class="fas fa-times"></i></button>')
                            .on('click', function () {
                                $('#client_name, #period_start, #period_end, #invoice_date').val('');
                                invoicesTable.draw();
                            }).hide();

                        $('.dataTables_filter').append($filterBtn).append($resetFilterBtnGlobal);

                        toggleResetButtonVisibility();

                        $('#client_name, #period_start, #period_end, #invoice_date')
                            .on('change input', toggleResetButtonVisibility);

                        invoicesTable.on('draw', function () {
                            toggleResetButtonVisibility();
                        });
                    },
                    drawCallback: function () {
                        $('.select-row').each(function () {
                            $(this).prop('checked', selectedInvoiceIds.includes($(this).val()));
                        });
                        updateSelectedInvoiceCount();
                        updateSelectAllInvoicesCheckboxState();
                        $('.delete-single').off('click').on('click', function (e) {
                            e.preventDefault();
                            const form = $(this).closest('form');
                            const invoiceId = $(this).data('invoice-id');
                            showConfirmModal(`Вы уверены, что хотите удалить счет #${invoiceId}?`, function () {
                                form.submit();
                            });
                        });

                        invoicesTable.on('click', '.reissue-invoice-btn', function() {
                            const invoiceId = $(this).data('invoice-id');
                            const reissueUrl = '{{ route('carwash_invoices.reissue', ':invoiceId') }}'.replace(':invoiceId', invoiceId);

                            const modal = new bootstrap.Modal('#reissueInvoiceModal');
                            $('#reissueInvoiceMessage').text(`Вы уверены, что хотите перевыставить счет #${invoiceId}? Это действие заменит существующий счет.`);
                            modal.show();

                            $('#confirmReissueButton').off('click').on('click', function() {
                                modal.hide();
                                $.ajax({
                                    url: reissueUrl,
                                    method: 'POST',
                                    data: { _token: "{{ csrf_token() }}" },
                                    success: function(response) {
                                        showToast('Успех', response.success, 'success');
                                        invoicesTable.draw();
                                    },
                                    error: function(xhr) {
                                        showToast('Ошибка', xhr.responseJSON?.error || 'Ошибка при перевыставлении счета.', 'error');
                                    }
                                });
                            });
                        });

                        invoicesTable.on('click', '.send-email-btn', function() {
                            const invoiceId = $(this).data('invoice-id');
                            const mailUrl = '{{ route('carwash_invoices.send_email_manually', ':invoiceId') }}'.replace(':invoiceId', invoiceId);

                            const modal = new bootstrap.Modal('#sendEmailModal');
                            $('#sendEmailMessage').text(`Вы уверены, что хотите отправить счет #${invoiceId} на email клиенту?`);
                            modal.show();

                            $('#confirmSendEmailButton').off('click').on('click', function() {
                                modal.hide();
                                $.ajax({
                                    url: mailUrl,
                                    method: 'POST',
                                    data: { _token: "{{ csrf_token() }}" },
                                    success: function(response) {
                                        showToast('Успех', response.success, 'success');
                                        invoicesTable.draw(); // Обновить таблицу
                                    },
                                    error: function(xhr) {
                                        showToast('Ошибка', xhr.responseJSON?.error || 'Ошибка при отправке счета на email.', 'error');
                                    }
                                });
                            });
                        });
                    }
                });

                function toggleResetButtonVisibility() {
                    const hasFilters = $('#client_name, #period_start, #period_end, #invoice_date')
                        .filter(function () { return $(this).val(); }).length > 0;

                    if (hasFilters) {
                        $('#filter-reset-btn').show();
                        $('#filter-btn').removeClass('btn-secondary').addClass('btn-primary');
                    } else {
                        $('#filter-reset-btn').hide();
                        $('#filter-btn').removeClass('btn-primary').addClass('btn-secondary');
                    }
                }

                // Применение фильтров
                $('#filterForm').on('submit', function (e) {
                    e.preventDefault();
                    allInvoicesGloballySelected = false;
                    selectedInvoiceIds = [];
                    invoicesTable.draw();
                    $('#filterOffcanvas').offcanvas('hide');
                    toggleResetButtonVisibility();
                });

                // Сброс фильтров
                $('#resetFilters').on('click', function () {
                    $('#filterForm')[0].reset();
                    allInvoicesGloballySelected = false;
                    selectedInvoiceIds = [];
                    invoicesTable.draw();
                    $('#filterOffcanvas').offcanvas('hide');
                    toggleResetButtonVisibility();
                });

                // Выбор всех строк
                $('#selectAllInvoices').on('change', function () {
                    let isChecked = $(this).prop('checked');
                    if (isChecked) {
                        $.ajax({
                            url: "{{ route('carwash_invoices.get_all_ids') }}",
                            method: 'GET',
                            data: {
                                client_name: $('#client_name').val(),
                                period_start: $('#period_start').val(),
                                period_end: $('#period_end').val(),
                                invoice_date: $('#invoice_date').val()
                            },
                            success: function (response) {
                                selectedInvoiceIds = response.ids.map(id => String(id));
                                allInvoicesGloballySelected = true;
                                $('.select-row').prop('checked', true);
                                updateSelectedInvoiceCount();
                                updateSelectAllInvoicesCheckboxState();
                            },
                            error: function () {
                                $('#selectAllInvoices').prop('checked', false);
                                showToast('Ошибка', 'Не удалось загрузить все ID.', 'error');
                            }
                        });
                    } else {
                        selectedInvoiceIds = [];
                        allInvoicesGloballySelected = false;
                        $('.select-row').prop('checked', false);
                        updateSelectedInvoiceCount();
                        updateSelectAllInvoicesCheckboxState();
                    }
                });

                // Выбор отдельной строки
                $(document).on('change', '.select-row', function () {
                    allInvoicesGloballySelected = false;
                    let id = $(this).val();
                    if ($(this).prop('checked')) {
                        if (!selectedInvoiceIds.includes(id)) {
                            selectedInvoiceIds.push(id);
                        }
                    } else {
                        selectedInvoiceIds = selectedInvoiceIds.filter(item => item !== id);
                    }
                    updateSelectedInvoiceCount();
                    updateSelectAllInvoicesCheckboxState();
                });

                // Групповое удаление
                $('#deleteSelectedInvoices').on('click', function () {
                    if (selectedInvoiceIds.length === 0) return;
                    showConfirmModal(`Вы уверены, что хотите удалить ${selectedInvoiceIds.length} счет(ов)?`, function () {
                        $.ajax({
                            url: "{{ route('carwash_invoices.deleteSelected') }}",
                            method: 'POST',
                            data: {
                                _token: "{{ csrf_token() }}",
                                ids: selectedInvoiceIds
                            },
                            success: function (response) {
                                if (response.success) {
                                    selectedInvoiceIds = [];
                                    allInvoicesGloballySelected = false;
                                    invoicesTable.draw();
                                    showToast('Успех', 'Выбранные счета успешно удалены.', 'success');
                                }
                            },
                            error: function (xhr) {
                                showToast('Ошибка', xhr.responseJSON?.message || 'Ошибка при удалении.', 'error');
                            }
                        });
                    });
                });

                // Вспомогательные функции
                function updateSelectedInvoiceCount() {
                    const info = invoicesTable.page.info();
                    const count = selectedInvoiceIds.length;
                    $('#deleteSelectedInvoices').prop('disabled', count === 0);
                    $('.dataTables_info').html(`Выбрано записей: ${count} | Всего записей: ${info.recordsTotal}`);
                }

                function updateSelectAllInvoicesCheckboxState() {
                    const visibleRows = invoicesTable.rows({page: 'current'}).nodes().to$().find('.select-row');
                    const visibleChecked = visibleRows.filter(':checked').length;
                    const visibleCount = visibleRows.length;

                    if (allInvoicesGloballySelected) {
                        $('#selectAllInvoices').prop('indeterminate', false).prop('checked', true);
                    } else if (visibleChecked === 0) {
                        $('#selectAllInvoices').prop('indeterminate', selectedInvoiceIds.length > 0);
                        $('#selectAllInvoices').prop('checked', false);
                    } else if (visibleChecked === visibleCount) {
                        $('#selectAllInvoices').prop('indeterminate', selectedInvoiceIds.length > visibleChecked);
                        $('#selectAllInvoices').prop('checked', true);
                    } else {
                        $('#selectAllInvoices').prop('indeterminate', true);
                        $('#selectAllInvoices').prop('checked', false);
                    }
                }
            });
        </script>
    @endpush
@endsection
