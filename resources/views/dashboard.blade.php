@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Панель управления</h1>

        <!-- Фильтры -->
        <div class="card mb-4">
            <div class="card-header">Фильтры</div>
            <div class="card-body">
                <form id="filter-form" method="GET">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="period">Период</label>
                                <select name="period" id="period" class="form-control">
                                    <option value="month" {{ $period == 'month' ? 'selected' : '' }}>Месяц</option>
                                    <option value="year" {{ $period == 'year' ? 'selected' : '' }}>Год</option>
                                    <option value="custom" {{ $period == 'custom' ? 'selected' : '' }}>Произвольный</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 custom-period" style="display: {{ $period == 'custom' ? 'block' : 'none' }}">
                            <div class="form-group">
                                <label for="start_date">Начало</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $startDate }}">
                            </div>
                        </div>
                        <div class="col-md-3 custom-period" style="display: {{ $period == 'custom' ? 'block' : 'none' }}">
                            <div class="form-group">
                                <label for="end_date">Конец</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $endDate }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="granularity">Детализация</label>
                                <select name="granularity" id="granularity" class="form-control">
                                    @if ($period == 'month')
                                        <option value="day" {{ $granularity == 'day' ? 'selected' : '' }}>По дням</option>
                                        <option value="week" {{ $granularity == 'week' ? 'selected' : '' }}>По неделям</option>
                                    @else
                                        <option value="month" {{ $granularity == 'month' ? 'selected' : '' }}>По месяцам</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">Применить</button>
                </form>
            </div>
        </div>

        <!-- Виджеты -->
        <div class="row">
            <!-- Количество бонусных карт -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Бонусные карты по месяцам</div>
                    <div class="card-body">
                        <canvas id="bonusCardsChart"></canvas>
                    </div>
                </div>
            </div>
            <!-- Количество клиентов -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Клиенты по месяцам</div>
                    <div class="card-body">
                        <canvas id="clientsChart"></canvas>
                    </div>
                </div>
            </div>
            <!-- Количество счетов -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Счета по месяцам</div>
                    <div class="card-body">
                        <canvas id="invoicesChart"></canvas>
                    </div>
                </div>
            </div>
            <!-- Общая сумма счетов -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Общая сумма счетов</div>
                    <div class="card-body">
                        <h3>{{ number_format($totalInvoicesAmount, 2) }} руб.</h3>
                    </div>
                </div>
            </div>
            <!-- Длительность использования карт -->
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header">Длительность использования карт по месяцам</div>
                    <div class="card-body">
                        <canvas id="usageDurationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            // Бонусные карты
            const bonusCardsCtx = document.getElementById('bonusCardsChart').getContext('2d');
            new Chart(bonusCardsCtx, {
                type: 'line',
                data: {
                    labels: @json($bonusCardsData['labels']),
                    datasets: [
                        {
                            label: 'Всего',
                            data: @json($bonusCardsData['data']['total']),
                            borderColor: 'blue',
                            fill: false
                        },
                        {
                            label: 'Активные',
                            data: @json($bonusCardsData['data']['active']),
                            borderColor: 'green',
                            fill: false
                        },
                        {
                            label: 'Заблокированные',
                            data: @json($bonusCardsData['data']['blocked']),
                            borderColor: 'red',
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });

            // Клиенты
            const clientsCtx = document.getElementById('clientsChart').getContext('2d');
            new Chart(clientsCtx, {
                type: 'line',
                data: {
                    labels: @json($clientsData['labels']),
                    datasets: [
                        {
                            label: 'Всего',
                            data: @json($clientsData['data']['total']),
                            borderColor: 'blue',
                            fill: false
                        },
                        {
                            label: 'Активные',
                            data: @json($clientsData['data']['active']),
                            borderColor: 'green',
                            fill: false
                        },
                        {
                            label: 'Заблокированные',
                            data: @json($clientsData['data']['blocked']),
                            borderColor: 'red',
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });

            // Счета
            const invoicesCtx = document.getElementById('invoicesChart').getContext('2d');
            new Chart(invoicesCtx, {
                type: 'line',
                data: {
                    labels: @json($invoicesData['labels']),
                    datasets: [
                        {
                            label: 'Счета',
                            data: @json($invoicesData['data']['total']),
                            borderColor: 'purple',
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });

            // Длительность использования
            const usageDurationCtx = document.getElementById('usageDurationChart').getContext('2d');
            new Chart(usageDurationCtx, {
                type: 'line',
                data: {
                    labels: @json($usageDurationData['labels']),
                    datasets: [
                        {
                            label: 'Длительность (сек)',
                            data: @json($usageDurationData['data']['total_duration']),
                            borderColor: 'orange',
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });

            // Логика фильтров
            document.getElementById('period').addEventListener('change', function() {
                const customPeriod = document.querySelectorAll('.custom-period');
                if (this.value === 'custom') {
                    customPeriod.forEach(el => el.style.display = 'block');
                } else {
                    customPeriod.forEach(el => el.style.display = 'none');
                }

                // Обновление детализации
                const granularity = document.getElementById('granularity');
                granularity.innerHTML = '';
                if (this.value === 'month') {
                    granularity.innerHTML = `
                        <option value="day">По дням</option>
                        <option value="week">По неделям</option>
                    `;
                } else {
                    granularity.innerHTML = `<option value="month">По месяцам</option>`;
                }
            });
        </script>
    @endpush
@endsection
