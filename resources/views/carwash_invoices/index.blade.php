@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Счета</h1>
        <a href="{{ route('carwash_invoices.create') }}" class="btn btn-primary mb-3">Создать счет</a>
        <button id="delete-selected" class="btn btn-danger mb-3" disabled>Удалить выбранные</button>
        <div id="filter-panel" class="filter-panel">
            <div class="filter-panel-header">
                <h3>Фильтр</h3>
                <button id="close-filter-panel" class="btn btn-secondary">Закрыть</button>
            </div>
            <div class="filter-panel-body">
                <form id="filter-form">
                    <div class="form-group">
                        <label for="client_name_filter">Имя клиента</label>
                        <input type="text" class="form-control" id="client_name_filter" name="client_name">
                    </div>
                    <div class="form-group">
                        <label for="period_start_filter">Начало периода</label>
                        <input type="date" class="form-control" id="period_start_filter" name="period_start">
                    </div>
                    <div class="form-group">
                        <label for="period_end_filter">Конец периода</label>
                        <input type="date" class="form-control" id="period_end_filter" name="period_end">
                    </div>
                    <div class="form-group">
                        <label for="sent_status_filter">Статус отправки</label>
                        <select class="form-control" id="sent_status_filter" name="sent_status">
                            <option value="">Все</option>
                            <option value="sent">Отправлен</option>
                            <option value="not_sent">Не отправлен</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Применить</button>
                    <button type="button" class="btn btn-secondary" id="clear-filter">Очистить</button>
                </form>
            </div>
        </div>
        <table id="invoices-table" class="table table-bordered">
            <thead>
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>ID</th>
                <th>Клиент</th>
                <th>Сумма (руб.)</th>
                <th>Период</th>
                <th>Отправлен</th>
                <th>Действия</th>
            </tr>
            </thead>
        </table>
        <div id="selected-count" class="mt-3"></div>
    </div>

    @push('scripts')
        <script>
            $(document).ready(function() {
                var table = $('#invoices-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '{{ route('carwash_invoices.data') }}',
                        data: function(d) {
                            d.client_name = $('#client_name_filter').val();
                            d.period_start = $('#period_start_filter').val();
                            d.period_end = $('#period_end_filter').val();
                            d.sent_status = $('#sent_status_filter').val();
                        }
                    },
                    columns: [
                        {
                            data: 'checkbox',
                            name: 'checkbox',
                            orderable: false,
                            searchable: false,
                            render: function(data, type, row) {
                                return '<input type="checkbox" class="select-row" value="' + row.id + '">';
                            }
                        },
                        { data: 'id', name: 'id' },
                        { data: 'client_name', name: 'client_name' },
                        { data: 'amount', name: 'amount' },
                        { data: 'period_start', name: 'period_start', render: function(data, type, row) {
                                return `${row.period_start} - ${row.period_end}`;
                            }},
                        { data: 'sent_at', name: 'sent_at' },
                        { data: 'action', name: 'action', orderable: false, searchable: false }
                    ],
                    initComplete: function() {
                        var api = this.api();
                        var $filterBtn = $('<button id="filter-btn" class="btn btn-secondary btn-sm">Фильтр</button>')
                            .on('click', function() {
                                $('#filter-panel').toggleClass('active');
                            });
                        $(api.table().container()).find('div.dataTables_filter').append($filterBtn);
                    }
                });

                $('#select-all').on('change', function() {
                    $('.select-row').prop('checked', this.checked);
                    updateSelectedCount();
                });

                $('#invoices-table').on('change', '.select-row', function() {
                    updateSelectedCount();
                });

                function updateSelectedCount() {
                    var selected = $('.select-row:checked').length;
                    $('#selected-count').text('Выбрано записей: ' + selected);
                    $('#delete-selected').prop('disabled', selected === 0);
                }

                $('#delete-selected').on('click', function() {
                    var selected = $('.select-row:checked').map(function() {
                        return $(this).val();
                    }).get();

                    $.ajax({
                        url: '{{ route('carwash_invoices.deleteSelected') }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            ids: selected
                        },
                        success: function(response) {
                            table.draw();
                            updateSelectedCount();
                        },
                        error: function(xhr) {
                            alert('Ошибка при удалении: ' + xhr.responseJSON.message);
                        }
                    });
                });

                $('#filter-btn').on('click', function() {
                    $('#filter-panel').toggleClass('active');
                });

                $('#close-filter-panel').on('click', function() {
                    $('#filter-panel').removeClass('active');
                });

                $('#filter-form').on('submit', function(e) {
                    e.preventDefault();
                    table.draw();
                });

                $('#clear-filter').on('click', function() {
                    $('#filter-form')[0].reset();
                    table.draw();
                });
            });
        </script>
    @endpush
@endsection
