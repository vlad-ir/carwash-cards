<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Система бонусных карт для автомойки')</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/carwash_styles.css') }}">
    <!-- Custom Styles -->
    @stack('styles')
</head>
<body>
<div id="app">
    <!-- Добавляем компоненты toast и confirm-modal -->
    @include('components.toast')
    @include('components.confirm-modal')
    <!-- Навигационная панель -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/') }}">АВТОМОЙКА</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    @auth
                        <li class="nav-item">
                            <a class="nav-link {{ Request::is('carwash_dashboard') ? 'active' : '' }}" href="{{ route('carwash_dashboard') }}">Главная</a>
                        </li>
                        @if(auth()->user()->roles()->where('name', 'admin')->exists())
                            <li class="nav-item">
                                <a class="nav-link {{ Request::is('users*') ? 'active' : '' }}" href="{{ route('users.index') }}">Пользователи</a>
                            </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link {{ Request::is('carwash_clients*') ? 'active' : '' }}" href="{{ route('carwash_clients.index') }}">Клиенты</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Request::is('carwash_bonus_cards*') ? 'active' : '' }}" href="{{ route('carwash_bonus_cards.index') }}">Бонусные карты</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Request::is('carwash_bonus_card_stats*') ? 'active' : '' }}" href="{{ route('carwash_bonus_card_stats.index') }}">Статистика</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Request::is('carwash_invoices*') ? 'active' : '' }}" href="{{ route('carwash_invoices.index') }}">Счета</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-1"></i> {{ Auth::user()->name }}
                            </a>
                            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end" aria-labelledby="navbarUserDropdown">
                                <li>
                                    <a class="dropdown-item disabled" href="#" tabindex="-1" aria-disabled="true">
                                        <strong>Роли:</strong> {{ Auth::user()->roles->pluck('description')->implode(', ') }}
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        Выйти <i class="fas fa-right-from-bracket"></i>
                                    </a>
                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </li>
                            </ul>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">Вход</a>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <!-- Основной контент -->
    <div class="container">
        @yield('content')
    </div>
</div>
<!-- Bootstrap JS и зависимости -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery (требуется для DataTables) -->
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.bootstrap5.min.js"></script>

<script src="{{ asset('js/datatables.js') }}"></script>
<script src="{{ asset('js/common.js') }}"></script>
@stack('scripts')

@if (session('success'))
<script>
    $(document).ready(function() {
        showToast('Успех', '{{ session('success') }}', 'success');
    });
</script>
@elseif (session('error'))
<script>
    $(document).ready(function() {
        showToast('Ошибка', '{{ session('error') }}', 'error');
    });
</script>
@endif
</body>
</html>
