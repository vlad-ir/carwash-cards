@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Управление пользователями</h1>

    <div class="mb-3">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">Добавить пользователя</button>
        <button id="deleteSelected" class="btn btn-danger" disabled>Удалить выбранные</button>
    </div>


    <table id="usersTable" class="table table-bordered">
        <thead>
        <tr>
            <th class="no-sort"><input type="checkbox" id="selectAll"></th>
            <th>Имя</th>
            <th>Email</th>
            <th>Роли</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody></tbody>
    </table>
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
                <label for="name" class="form-label">Имя</label>
                <input type="text" name="name" id="name" class="form-control">
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" id="email" class="form-control">
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Роль</label>
                <select name="role" id="role" class="form-control">
                    <option value="">Все</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}">{{ $role->description }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Применить</button>
            <button type="button" id="resetFilters" class="btn btn-secondary">Сбросить</button>
        </form>
    </div>
</div>

<!-- Модальное окно создания -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('users.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Добавить пользователя</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Имя</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Пароль</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Роли</label>
                        @foreach($roles as $role)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="roles[]"
                                       value="{{ $role->id }}" id="role{{ $role->id }}">
                                <label class="form-check-label" for="role{{ $role->id }}">
                                    {{ $role->description }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Создать</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach($users as $user)
<!-- Модальное окно редактирования -->
<div class="modal fade" id="editUserModal{{ $user->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('users.update', $user) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Редактировать пользователя</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name{{ $user->id }}" class="form-label">Имя</label>
                        <input type="text" class="form-control" id="name{{ $user->id }}"
                               name="name" value="{{ $user->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label for="email{{ $user->id }}" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email{{ $user->id }}"
                               name="email" value="{{ $user->email }}" required>
                    </div>
                    <div class="mb-3">
                        <label for="password{{ $user->id }}" class="form-label">Новый пароль (оставьте пустым, чтобы не менять)</label>
                        <input type="password" class="form-control" id="password{{ $user->id }}"
                               name="password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Роли</label>
                        @foreach($roles as $role)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="roles[]" value="{{ $role->id }}"
                                       id="role{{ $user->id }}{{ $role->id }}"
                                       {{ $user->roles->contains($role->id) ? 'checked' : '' }}>
                                <label class="form-check-label" for="role{{ $user->id }}{{ $role->id }}">
                                    {{ $role->description }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

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
    $(document).ready(function() {
        // Хранилище выбранных ID
        let selectedIds = [];

        // Инициализация DataTables
        var table = $('#usersTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('users.index') }}",
                data: function(d) {
                    d.name = $('#name').val();
                    d.email = $('#email').val();
                    d.role = $('#role').val();
                }
            },
            columns: [
                {data: 'checkbox', name: 'checkbox', orderable: false, searchable: false},
                {data: 'name', name: 'name'},
                {data: 'email', name: 'email'},
                {data: 'roles', name: 'roles', orderable: true},
                {data: 'action', name: 'action', orderable: false, searchable: false}
            ],
            order: [[1, 'asc']], // Сортировка по умолчанию по имени

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
                        $('#name, #email, #role').val('');
                        table.draw();
                    });

                // Добавляем кнопки к фильтру DataTables
                var $filterContainer = $(api.table().container()).find('div.dataTables_filter');
                $filterContainer.append($filterBtn).append($resetFilterBtn);

                // Проверяем, есть ли заполненные фильтры
                function hasActiveFilters() {
                    return $('#name, #email, #role')
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
                $('#name, #email, #role').on('change input', function () {
                    toggleResetButtonVisibility();
                });

                // Также проверяем после перерисовки таблицы
                table.on('draw', function () {
                    toggleResetButtonVisibility();
                });
            },
            drawCallback: function() {
                $('.select-row').each(function() {
                    $(this).prop('checked', selectedIds.includes($(this).val()));
                });
                updateSelectedCount();

                $('.delete-single').off('click').on('click', function(e) {
                    e.preventDefault();
                    const form = $(this).closest('form');
                    const userName = $(this).data('user-name');
                    showConfirmModal(`Вы уверены, что хотите удалить пользователя ${userName}?`, function() {
                        form.submit();
                    });
                });
            }
        });

        // Обновление количества выбранных записей
        function updateSelectedCount() {
            let count = selectedIds.length;
            let info = table.page.info();
            let statusText = `Выбрано записей: ${count}`;
            $('.dataTables_info').text(`${statusText} | Всего записей: ${info.recordsTotal}`);
        }

        // Обработка формы фильтрации
        $('#filterForm').on('submit', function(e) {
            e.preventDefault();
            table.draw();
            $('#filterOffcanvas').offcanvas('hide');
        });

        // Сброс фильтров
        $('#resetFilters').on('click', function() {
            $('#name, #email, #role').val('');
            table.draw();
            $('#filterOffcanvas').offcanvas('hide');
        });

        // Выбор всех чекбоксов
        $('#selectAll').on('change', function() {
            let isChecked = $(this).prop('checked');
            $('.select-row').prop('checked', isChecked);

            if (isChecked) {
                $('.select-row').each(function() {
                    let id = $(this).val();
                    if (!selectedIds.includes(id)) {
                        selectedIds.push(id);
                    }
                });
            } else {
                selectedIds = [];
            }

            updateSelectedCount();
            $('#deleteSelected').prop('disabled', selectedIds.length === 0);
        });

        // Выбор отдельных чекбоксов
        $(document).on('change', '.select-row', function() {
            let id = $(this).val();
            if ($(this).prop('checked')) {
                if (!selectedIds.includes(id)) {
                    selectedIds.push(id);
                }
            } else {
                selectedIds = selectedIds.filter(item => item !== id);
            }

            updateSelectedCount();
            $('#deleteSelected').prop('disabled', selectedIds.length === 0);
            $('#selectAll').prop('checked', selectedIds.length === $('.select-row').length);
        });

        // Групповое удаление
        $('#deleteSelected').on('click', function() {
            if (selectedIds.length === 0) return;

            showConfirmModal(`Вы уверены, что хотите удалить выбранные записи (${selectedIds.length})?`, function() {
                $.ajax({
                    url: "{{ route('users.deleteSelected') }}",
                    method: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        ids: selectedIds
                    },
                    success: function(response) {
                        if (response.success) {
                            selectedIds = [];
                            table.draw();
                            showToast('Успех', 'Выбранные записи успешно удалены', 'success');
                        }
                    },
                    error: function(xhr) {
                        showToast('Ошибка', xhr.responseJSON.error || 'Произошла ошибка при удалении', 'error');
                    }
                });
            });
        });
    });
</script>
@endpush
@endsection
