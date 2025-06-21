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
            <!-- Статистика по клиентам -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">Клиенты</div>
                    <div class="card-body">
                        <p>Общее количество: {{ $clientStats->total ?? 0 }}</p>
                        <p>Активных: {{ $clientStats->active ?? 0 }}</p>
                        <p>Заблокированных: {{ $clientStats->blocked ?? 0 }}</p>
                        <p>Без бонусных карт: {{ $clientStats->without_card ?? 0 }}</p>
                    </div>
                </div>
            </div>
            <!-- Статистика по бонусным картам -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">Бонусные карты</div>
                    <div class="card-body">
                        <p>Общее количество: {{ $bonusCardStats->total ?? 0 }}</p>
                        <p>Активных: {{ $bonusCardStats->active ?? 0 }}</p>
                        <p>Заблокированных: {{ $bonusCardStats->blocked ?? 0 }}</p>
                    </div>
                </div>
            </div>
            <!-- Статистика по счетам -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">Счета</div>
                    <div class="card-body">
                        <p>Количество счетов: {{ $invoiceStats->total_count ?? 0 }}</p>
                        <p>Общая сумма: {{ number_format($invoiceStats->total_amount ?? 0, 2) }} руб.</p>
                        <p>Общая длительность использования: {{ (int) ceil(($invoiceStats->total_usage_duration_seconds ?? 0) / 60) }} мин.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- График "Длительность использования карт и суммы счетов по месяцам" -->
        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header">Длительность использования карт и суммы счетов по месяцам</div>
                    <div class="card-body">
                        <canvas id="usageDurationAndInvoicesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            // Длительность использования и суммы счетов
            const usageDurationAndInvoicesCtx = document.getElementById('usageDurationAndInvoicesChart').getContext('2d');
            new Chart(usageDurationAndInvoicesCtx, {
                type: 'line',
                data: {
                    labels: @json($usageDurationData['labels']),
                    datasets: [
                        {
                            label: 'Длительность (мин)',
                            data: @json($usageDurationData['data']),
                            borderColor: 'orange',
                            backgroundColor: 'rgba(255, 165, 0, 0.2)',
                            yAxisID: 'y-duration',
                            fill: false
                        },
                        {
                            label: 'Сумма счетов (руб)',
                            data: @json($invoicesAmountByMonthData['data']),
                            borderColor: 'blue',
                            backgroundColor: 'rgba(0, 0, 255, 0.2)',
                            yAxisID: 'y-amount',
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        'y-duration': {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Длительность (мин)'
                            }
                        },
                        'y-amount': {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Сумма (руб)'
                            },
                            grid: {
                                drawOnChartArea: false, // only want the grid lines for one axis to show up
                            },
                        }
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
