<?php

namespace App\Http\Controllers;

use App\Models\CarwashBonusCard;
use App\Models\CarwashBonusCardStat;
use App\Models\CarwashClient;
use App\Models\CarwashInvoice;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CarwashDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Параметры фильтра
        $period = $request->input('period', 'month'); // month, year, custom
        $startDate = $request->input('start_date', now()->subMonth()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $granularity = $request->input('granularity', 'day'); // day, week, month

        // Определение периода
        if ($period === 'month') {
            $startDate = now()->subMonth()->startOfMonth()->toDateString();
            $endDate = now()->endOfMonth()->toDateString();
            $granularity = in_array($granularity, ['day', 'week']) ? $granularity : 'day';
        } elseif ($period === 'year') {
            $startDate = now()->subYear()->startOfYear()->toDateString();
            $endDate = now()->endOfYear()->toDateString();
            $granularity = 'month';
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        // Данные для виджетов
        $clientStats = $this->getClientStats($start, $end);
        $bonusCardStats = $this->getBonusCardStats($start, $end);
        $invoiceStats = $this->getInvoiceStats($start, $end);

        // Данные для графика
        $usageDurationData = $this->getUsageDurationData($start, $end, $granularity);
        $invoicesAmountByMonthData = $this->getInvoicesAmountByMonthData($start, $end, $granularity);


        return view('dashboard', compact(
            'clientStats',
            'bonusCardStats',
            'invoiceStats',
            'usageDurationData',
            'invoicesAmountByMonthData',
            'period',
            'startDate',
            'endDate',
            'granularity'
        ));
    }

    protected function getClientStats($start, $end)
    {
        $query = CarwashClient::whereBetween('created_at', [$start, $end]);

        $total = (clone $query)->count();
        $active = (clone $query)->where('status', 'active')->count();
        $blocked = (clone $query)->where('status', 'blocked')->count();
        $without_card = (clone $query)->doesntHave('bonusCards')->count();

        return (object) [
            'total' => $total,
            'active' => $active,
            'blocked' => $blocked,
            'without_card' => $without_card,
        ];
    }

    protected function getBonusCardStats($start, $end)
    {
        return CarwashBonusCard::whereBetween('created_at', [$start, $end])
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active')
            ->selectRaw('SUM(CASE WHEN status = "blocked" THEN 1 ELSE 0 END) as blocked')
            ->first();
    }

    protected function getInvoiceStats($start, $end)
    {
        $invoiceData = CarwashInvoice::whereBetween('created_at', [$start, $end])
            ->selectRaw('COUNT(*) as total_count, SUM(amount) as total_amount')
            ->first();

        $totalUsageDurationSeconds = CarwashBonusCardStat::whereBetween('start_time', [$start, $end])
            ->sum('duration_seconds');

        return (object) [
            'total_count' => $invoiceData->total_count ?? 0,
            'total_amount' => $invoiceData->total_amount ?? 0,
            'total_usage_duration_seconds' => $totalUsageDurationSeconds ?? 0,
        ];
    }

    protected function getUsageDurationData($start, $end, $granularity)
    {
        $query = CarwashBonusCardStat::selectRaw('SUM(duration_seconds) as value_to_aggregate');
        return $this->aggregateByPeriod($query, $start, $end, $granularity, 'start_time', 'value_to_aggregate', true);
    }

    protected function getInvoicesAmountByMonthData($start, $end, $granularity)
    {
        $query = CarwashInvoice::selectRaw('SUM(amount) as value_to_aggregate');
        return $this->aggregateByPeriod($query, $start, $end, $granularity, 'created_at', 'value_to_aggregate', false);
    }

    protected function aggregateByPeriod($query, $start, $end, $granularity, $dateColumn, $valueColumn, $convertToMinutesAndCeil = false)
    {
        $labels = [];
        $data = [];

        if ($granularity === 'day') {
            $interval = '1 day';
            $format = 'Y-m-d';
        } elseif ($granularity === 'week') {
            $interval = '1 week';
            $format = 'Y-W';
        } else { // month
            $interval = '1 month';
            $format = 'Y-m';
        }

        $current = $start->copy();
        while ($current <= $end) {
            $labels[] = $current->format($format);
            $next = $current->copy()->add($interval);

            $result = (clone $query)
                ->whereBetween($dateColumn, [$current, $next->copy()->subSecond()])
                ->first();

            $value = $result->{$valueColumn} ?? 0;

            if ($convertToMinutesAndCeil) {
                $data[] = (int) ceil($value / 60);
            } else {
                $data[] = $value;
            }

            $current = $next;
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }
}
