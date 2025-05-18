@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Управление пользователями</h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        Добавить пользователя
                    </button>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Имя</th>
                                    <th>Email</th>
                                    <th>Роли</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($users as $user)
                                <tr>
                                    <td>{{ $user->id }}</td>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        @foreach($user->roles as $role)
                                            <span class="badge bg-primary">{{ $role->description }}</span>
                                        @endforeach
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editUserModal{{ $user->id }}">
                                            Редактировать
                                        </button>
                                        @if($user->id !== auth()->id())
                                            <form action="{{ route('users.destroy', $user) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Вы уверены?')">
                                                    Удалить
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>

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
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
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
@endsection
