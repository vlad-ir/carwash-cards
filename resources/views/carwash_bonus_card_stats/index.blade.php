@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Статистика бонусных карт</h1>
        <a href="{{ route('carwash_bonus_card_stats.create') }}" class="btn btn-primary mb-3">Добавить запись</a>
        <a href="{{ route('carwash_bonus_card_stats.upload') }}" class="btn btn-success mb-3">Загрузить CSV</a>
        <button id="delete-selected" class="btn btn-danger mb-3" disabled>Удалить выбранные</button>
        <div id="filter-panel" class="filter-panel">
            <div class="filter-panel-header">
                <h3>Фильтр</h3>
                <button id="close-filter-panel" class="btn btn-secondary">Закрыть</button>
            </div>
            <div class="filter-panel-body">
                <form id="filter-form">
                    <div class="form-group">
                        <label for="start_time_filter">Дата начала</label>
                        <input type="date" class="form-control" id="start_time_filter" name="start_time">
                    </div>
                    <button type="submit" class="btn btn-primary">Применить</button>
                    <button type="button" class="btn btn-secondary" id="clear-filter">Очистить</button>
                </form>
            </div>
        </div>
        <table id="stats-table" class="table table-bordered">
            <thead>
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>Номер карты</th>
                <th>Время начала</th>
                <th>Длительность (сек)</th>
                <th>Остаток (сек)</th>
                <th>Дата импорта</th>
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
                        { data: 'checkbox', name: 'checkbox', orderable: false, searchable: false },
                        { data: 'card_number', name: 'card_number' },
                        { data: 'start_time', name: 'start_time' },
                        { data: 'duration_seconds', name: 'duration_seconds' },
                        { data: 'remaining_balance_seconds', name: 'remaining_balance_seconds' },
                        { data: 'import_date', name: 'import_date' },
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

                $('#stats-table').on('change', '.select-row', function() {
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
                        url: '{{ route('carwash_bonus_card_stats.deleteSelected') }}',
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
