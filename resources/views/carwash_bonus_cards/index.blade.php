@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Бонусные карты</h1>
        <a href="{{ route('carwash_bonus_cards.create') }}" class="btn btn-primary mb-3">Добавить карту</a>
        <button id="delete-selected" class="btn btn-danger mb-3" disabled>Удалить выбранные</button>
        <div id="filter-panel" class="filter-panel">
            <div class="filter-panel-header">
                <h3>Фильтр</h3>
                <button id="close-filter-panel" class="btn btn-secondary">Закрыть</button>
            </div>
            <div class="filter-panel-body">
                <form id="filter-form">
                    <div class="form-group">
                        <label for="name_filter">Название</label>
                        <input type="text" class="form-control" id="name_filter" name="name">
                    </div>
                    <div class="form-group">
                        <label for="card_number_filter">Номер карты</label>
                        <input type="text" class="form-control" id="card_number_filter" name="card_number">
                    </div>
                    <div class="form-group">
                        <label for="car_license_plate_filter">Номер автомобиля</label>
                        <input type="text" class="form-control" id="car_license_plate_filter" name="car_license_plate">
                    </div>
                    <div class="form-group">
                        <label for="client_short_name_filter">Клиент</label>
                        <input type="text" class="form-control" id="client_short_name_filter" name="client_short_name">
                    </div>
                    <button type="submit" class="btn btn-primary">Применить</button>
                    <button type="button" class="btn btn-secondary" id="clear-filter">Очистить</button>
                </form>
            </div>
        </div>
        <table id="bonus-cards-table" class="table table-bordered">
            <thead>
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>Название</th>
                <th>Номер карты</th>
                <th>Скидка (%)</th>
                <th>Баланс</th>
                <th>Статус</th>
                <th>Клиент</th>
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
                var table = $('#bonus-cards-table').DataTable({
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

                $('#bonus-cards-table').on('change', '.select-row', function() {
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
                        url: '{{ route('carwash_bonus_cards.deleteSelected') }}',
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
