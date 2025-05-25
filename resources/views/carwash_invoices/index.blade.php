@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <h1 class="mt-4 mb-4">Счета на оплату</h1>

        {{-- Action Buttons --}}
        <div class="mb-3">
            @if (Route::has('carwash_invoices.create'))
                <a href="{{ route('carwash_invoices.create') }}" class="btn btn-primary">Создать счет вручную</a>
            @endif
            <button id="deleteSelectedInvoices" class="btn btn-danger" disabled>Удалить выбранные</button>
        </div>

        {{-- Filter Offcanvas Trigger --}}
        {{-- The filter button will be dynamically added by DataTables initComplete --}}

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
                    <table id="invoicesTable" class="table table-bordered table-striped table-hover" style="width:100%;">
                        <thead>
                        <tr>
                            <th class="no-sort text-center" style="width: 30px;"><input type="checkbox" id="selectAllInvoices"></th>
                            <th>ID Клиента</th>
                            <th>Название клиента</th>
                            <th>Начало периода</th>
                            <th>Конец периода</th>
                            <th>Всего карт</th>
                            <th>Активных карт</th>
                            <th>Блок. карт</th>
                            <th>Дата счета</th>
                            <th>Файл счета</th>
                            <th class="no-sort text-center" style="width: 100px;">Действия</th>
                        </tr>
                        </thead>
                        <tbody>
                        {{-- Data will be loaded by DataTables --}}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="filterInvoiceOffcanvas" aria-labelledby="filterInvoiceOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="filterInvoiceOffcanvasLabel">Фильтры для счетов</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form id="filterInvoiceForm">
                <div class="mb-3">
                    <label for="filter_client_name" class="form-label">Краткое название клиента</label>
                    <input type="text" class="form-control form-control-sm" id="filter_client_name" name="client_name">
                </div>
                <div class="mb-3">
                    <label for="filter_period_start" class="form-label">Начало периода (месяц)</label>
                    <input type="month" class="form-control form-control-sm" id="filter_period_start" name="period_start">
                </div>
                <div class="mb-3">
                    <label for="filter_period_end" class="form-label">Конец периода (месяц)</label>
                    <input type="month" class="form-control form-control-sm" id="filter_period_end" name="period_end">
                </div>
                <div class="mb-3">
                    <label for="filter_invoice_date" class="form-label">Дата счета</label>
                    <input type="date" class="form-control form-control-sm" id="filter_invoice_date" name="invoice_date">
                </div>
                <button type="submit" class="btn btn-primary btn-sm w-100 mb-2">Применить фильтры</button>
                <button type="button" id="resetInvoiceFilters" class="btn btn-secondary btn-sm w-100">Сбросить фильтры</button>
            </form>
        </div>
    </div>
@endsection

@push('styles')
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .form-label { font-weight: 500; }
        .table th { font-weight: 600; }
        .action-buttons .btn { margin-right: 5px; }
        .dataTables_wrapper .dataTables_filter { margin-bottom: 1rem; } /* Ensure space for custom buttons */
    </style>
@endpush

@push('scripts')
    <script>
        $(document).ready(function () {
            let selectedInvoiceIds = [];
            let allInvoicesGloballySelected = false;

            const invoicesTable = $('#invoicesTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("carwash_invoices.data") }}', // Ensure this route name is correct
                    data: function (d) {
                        d.client_name = $('#filter_client_name').val();
                        d.period_start = $('#filter_period_start').val();
                        d.period_end = $('#filter_period_end').val();
                        d.invoice_date = $('#filter_invoice_date').val();
                    }
                },
                columns: [
                    { data: 'checkbox', name: 'checkbox', orderable: false, searchable: false, className: 'text-center' },
                    { data: 'client_id', name: 'client_id' },
                    { data: 'client_short_name', name: 'client.short_name' }, // Adjust name for server-side sorting if joined
                    { data: 'period_start', name: 'period_start' },
                    { data: 'period_end', name: 'period_end' },
                    { data: 'total_cards_count', name: 'total_cards_count' },
                    { data: 'active_cards_count', name: 'active_cards_count' },
                    { data: 'blocked_cards_count', name: 'blocked_cards_count' },
                    { data: 'sent_at', name: 'sent_at' },
                    { data: 'file_link', name: 'file_link', orderable: false, searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center' }
                ],
                order: [[8, 'desc']], // Default sort by invoice date descending
                language: { // Basic localization example
                    url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Russian.json" // Or your local path
                },
                initComplete: function () {
                    var api = this.api();
                    var $filterBtn = $('<button id="filter-invoice-btn" class="btn btn-secondary btn-sm ms-2"><i class="fas fa-filter"></i> Фильтр</button>')
                        .on('click', function () { $('#filterInvoiceOffcanvas').offcanvas('show'); });
                    var $resetFilterBtnGlobal = $('<button id="filter-invoice-reset-btn" class="btn btn-danger btn-sm ms-1" title="Сбросить фильтры"><i class="fas fa-times"></i></button>')
                        .on('click', function () {
                            $('#filterInvoiceForm')[0].reset();
                            invoicesTable.ajax.reload();
                            toggleGlobalResetButtonVisibility();
                        }).hide(); // Initially hidden

                    $('.dataTables_filter').append($filterBtn).append($resetFilterBtnGlobal);
                    toggleGlobalResetButtonVisibility(); // Initial check
                },
                drawCallback: function () {
                    $('.select-row').prop('checked', false); // Reset checkboxes on draw
                    selectedInvoiceIds.forEach(id => {
                        $('#invoicesTable').find(`.select-row[value="${id}"]`).prop('checked', true);
                    });
                    if (allInvoicesGloballySelected) {
                        $('#invoicesTable').find('.select-row').prop('checked', true);
                    }
                    updateSelectedInvoiceCount();
                    updateSelectAllInvoicesCheckboxState();

                    // Re-initialize delete confirmation for new rows
                    $('.delete-single').off('click').on('click', function (e) {
                        e.preventDefault();
                        const form = $(this).closest('form');
                        const invoiceId = $(this).data('invoice-id');
                        showConfirmModal(`Вы уверены, что хотите удалить счет #${invoiceId}?`, function () {
                            form.submit();
                        });
                    });
                }
            });

            function hasActiveInvoiceFilters() {
                return $('#filter_client_name').val() || $('#filter_period_start').val() || $('#filter_period_end').val() || $('#filter_invoice_date').val();
            }

            function toggleGlobalResetButtonVisibility() {
                if (hasActiveInvoiceFilters()) {
                    $('#filter-invoice-reset-btn').show();
                    $('#filter-invoice-btn').removeClass('btn-secondary').addClass('btn-primary');
                } else {
                    $('#filter-invoice-reset-btn').hide();
                    $('#filter-invoice-btn').removeClass('btn-primary').addClass('btn-secondary');
                }
            }

            $('#filterInvoiceForm input, #filterInvoiceForm select').on('change input', toggleGlobalResetButtonVisibility);


            $('#filterInvoiceForm').on('submit', function (e) {
                e.preventDefault();
                allInvoicesGloballySelected = false; // Reset global selection on new filter
                selectedInvoiceIds = [];
                invoicesTable.ajax.reload(); // Use ajax.reload() for DataTables server-side
                $('#filterInvoiceOffcanvas').offcanvas('hide');
                toggleGlobalResetButtonVisibility();
            });

            $('#resetInvoiceFilters').on('click', function () {
                $('#filterInvoiceForm')[0].reset();
                allInvoicesGloballySelected = false;
                selectedInvoiceIds = [];
                invoicesTable.ajax.reload();
                $('#filterInvoiceOffcanvas').offcanvas('hide');
                toggleGlobalResetButtonVisibility();
            });

            function updateSelectedInvoiceCount() {
                let count = allInvoicesGloballySelected ? invoicesTable.page.info().recordsTotal : selectedInvoiceIds.length;
                let pageInfo = invoicesTable.page.info();
                $('#invoicesTable_info').html(`Выбрано: ${count} (Всего: ${pageInfo.recordsTotal}, на странице: ${pageInfo.recordsDisplay})`);
                $('#deleteSelectedInvoices').prop('disabled', count === 0);
            }

            function updateSelectAllInvoicesCheckboxState() {
                if (allInvoicesGloballySelected) {
                    $('#selectAllInvoices').prop('indeterminate', false).prop('checked', true);
                    return;
                }
                let visibleRows = invoicesTable.rows({ page: 'current' }).nodes().to$().find('.select-row');
                if (visibleRows.length === 0) {
                    $('#selectAllInvoices').prop('indeterminate', false).prop('checked', false);
                    return;
                }
                let visibleChecked = visibleRows.filter(':checked').length;

                if (visibleChecked === 0) {
                    $('#selectAllInvoices').prop('indeterminate', selectedInvoiceIds.length > 0);
                    $('#selectAllInvoices').prop('checked', false);
                } else if (visibleChecked === visibleRows.length) {
                    $('#selectAllInvoices').prop('indeterminate', selectedInvoiceIds.length > visibleChecked && !allInvoicesGloballySelected);
                    $('#selectAllInvoices').prop('checked', true);
                } else {
                    $('#selectAllInvoices').prop('indeterminate', true);
                    $('#selectAllInvoices').prop('checked', false);
                }
            }

            $('#selectAllInvoices').on('change', function () {
                let isChecked = $(this).is(':checked');
                allInvoicesGloballySelected = false; // Reset this first

                if (isChecked) {
                    // Option 1: Select all visible on current page
                    // invoicesTable.rows({ page: 'current' }).nodes().to$().find('.select-row').prop('checked', true).trigger('change');
                    // Option 2: Select ALL records (matching current filter) - requires AJAX to get all IDs
                    $.ajax({
                        url: "{{ route('carwash_invoices.get_all_ids') }}",
                        method: 'GET',
                        data: {
                            client_name: $('#filter_client_name').val(),
                            period_start: $('#filter_period_start').val(),
                            period_end: $('#filter_period_end').val(),
                            invoice_date: $('#filter_invoice_date').val()
                        },
                        success: function(response) {
                            selectedInvoiceIds = response.ids.map(id => String(id));
                            allInvoicesGloballySelected = true;
                            $('#invoicesTable .select-row').prop('checked', true); // Check all visible after confirmation
                            updateSelectedInvoiceCount();
                            updateSelectAllInvoicesCheckboxState();
                        }
                    });
                } else {
                    selectedInvoiceIds = [];
                    allInvoicesGloballySelected = false;
                    $('#invoicesTable .select-row').prop('checked', false);
                    updateSelectedInvoiceCount();
                    updateSelectAllInvoicesCheckboxState();
                }
            });

            $('#invoicesTable tbody').on('change', '.select-row', function () {
                let id = $(this).val();
                if ($(this).is(':checked')) {
                    if (!selectedInvoiceIds.includes(id)) selectedInvoiceIds.push(id);
                } else {
                    selectedInvoiceIds = selectedInvoiceIds.filter(item => item !== id);
                    allInvoicesGloballySelected = false; // If any individual is unchecked, global selection is off
                }
                updateSelectedInvoiceCount();
                updateSelectAllInvoicesCheckboxState();
            });

            $('#deleteSelectedInvoices').on('click', function () {
                if (selectedInvoiceIds.length === 0 && !allInvoicesGloballySelected) {
                    showToast('Внимание', 'Не выбрано ни одного счета для удаления.', 'warning');
                    return;
                }
                let count = allInvoicesGloballySelected ? invoicesTable.page.info().recordsTotal : selectedInvoiceIds.length;

                showConfirmModal(`Вы уверены, что хотите удалить ${count} счет(ов)?`, function () {
                    $.ajax({
                        url: "{{ route('carwash_invoices.deleteSelected') }}",
                        method: 'POST',
                        data: {
                            _token: "{{ csrf_token() }}",
                            ids: allInvoicesGloballySelected ? '_all_filtered_' : selectedInvoiceIds // Server needs to handle '_all_filtered_' if used
                                                                                                     // For now, send only selected IDs.
                        },
                        success: function (response) {
                            showToast('Успех', response.success, 'success');
                            selectedInvoiceIds = [];
                            allInvoicesGloballySelected = false;
                            invoicesTable.draw(false); // draw(false) to keep current page
                            updateSelectedInvoiceCount();
                            updateSelectAllInvoicesCheckboxState();
                        },
                        error: function (xhr) {
                            showToast('Ошибка', xhr.responseJSON?.message || 'Ошибка при удалении счетов.', 'error');
                        }
                    });
                });
            });

            // Assumed global functions (if not in layouts.app, define them or include a common JS file)
            // function showConfirmModal(message, callback) { ... }
            // function showToast(title, message, type) { ... }
        });
    </script>
@endpush
