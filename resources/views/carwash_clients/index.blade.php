@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Клиенты автомойки</h1>
        <a href="{{ route('carwash_clients.create') }}" class="btn btn-primary mb-3">Добавить клиента</a>
        <button id="delete-selected" class="btn btn-danger mb-3" disabled>Удалить выбранные</button>
        <div id="filter-panel" class="filter-panel">
            <div class="filter-panel-header">
                <h3>Фильтр</h3>
                <button id="close-filter-panel" class="btn btn-secondary">Закрыть</button>
            </div>
            <div class="filter-panel-body">
                <form id="filter-form">
                    <div class="form-group">
                        <label for="short_name_filter">Краткое имя</label>
                        <input type="text" class="form-control" id="short_name_filter" name="short_name">
                    </div>
                    <div class="form-group">
                        <label for="email_filter">Email</label>
                        <input type="text" class="form-control" id="email_filter" name="email">
                    </div>
                    <div class="form-group">
                        <label for="phone_filter">Телефон</label>
                        <input type="text" class="form-control" id="phone_filter" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="unp_filter">УНП</label>
                        <input type="text" class="form-control" id="unp_filter" name="unp">
                    </div>
                    <button type="submit" class="btn btn-primary">Применить</button>
                    <button type="button" class="btn btn-secondary" id="clear-filter">Очистить</button>
                </form>
            </div>
        </div>
        <table id="clients-table" class="table table-bordered">
            <thead>
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>Краткое имя</th>
                <th>Полное имя</th>
                <th>Email</th>
                <th>Телефон</th>
                <th>УНП</th>
                <th>Кол-во карт</th>
                <th>Действия</th>
            </tr>
            </thead>
        </table>
        <div id="selected-count" class="mt-3"></div>
    </div>

    @push('styles')
        <style>
            .filter-panel {
                position: fixed;
                top: 0;
                right: -300px;
                width: 300px;
                height: 100%;
                background-color: #fff;
                box-shadow: -2px 0 10px rgba(0, 0, 0, 0.2);
                transition: right 0.3s;
                z-index: 1000;
            }
            .filter-panel-header {
                padding: 10px;
                background-color: #f8f9fa;
                border-bottom: 1px solid #dee2e6;
            }
            .filter-panel-body {
                padding: 20px;
            }
            .filter-panel.active {
                right: 0;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            $(document).ready(function() {
                var table = $('#clients-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '{{ route('carwash_clients.data') }}',
                        data: function(d) {
                            d.short_name = $('#short_name_filter').val();
                            d.email = $('#email_filter').val();
                            d.phone = $('#phone_filter').val();
                            d.unp = $('#unp_filter').val();
                        }
                    },
                    columns: [
                        { data: 'id', name: 'id', orderable: false, searchable: false, render: function(data, type, row) {
                                return '<input type="checkbox" class="select-row" value="' + data + '">';
                            }},
                        { data: 'short_name', name: 'short_name' },
                        { data: 'full_name', name: 'full_name' },
                        { data: 'email', name: 'email' },
                        { data: 'phone', name: 'phone' },
                        { data: 'unp', name: 'unp' },
                        { data: 'bonus_cards_count', name: 'bonus_cards_count' },
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

                $('#clients-table').on('change', '.select-row', function() {
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
                        url: '{{ route('carwash_clients.deleteSelected') }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            ids: selected
                        },
                        success: function(response) {
                            table.draw();
                            updateSelectedCount();
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
