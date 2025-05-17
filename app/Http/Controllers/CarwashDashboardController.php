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

        // Данные для графиков
        $bonusCardsData = $this->getBonusCardsData($start, $end, $granularity);
        $clientsData = $this->getClientsData($start, $end, $granularity);
        $invoicesData = $this->getInvoicesData($start, $end, $granularity);
        $totalInvoicesAmount = $this->getTotalInvoicesAmount($start, $end);
        $usageDurationData = $this->getUsageDurationData($start, $end, $granularity);

        return view('dashboard', compact(
            'bonusCardsData',
            'clientsData',
            'invoicesData',
            'totalInvoicesAmount',
            'usageDurationData',
            'period',
            'startDate',
            'endDate',
            'granularity'
        ));
    }

    protected function getBonusCardsData($start, $end, $granularity)
    {
        $query = CarwashBonusCard::selectRaw('COUNT(*) as total, SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active, SUM(CASE WHEN status = "blocked" THEN 1 ELSE 0 END) as blocked');

        return $this->aggregateByPeriod($query, $start, $end, $granularity, 'created_at');
    }

    protected function getClientsData($start, $end, $granularity)
    {
        $query = CarwashClient::selectRaw('COUNT(*) as total, SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active, SUM(CASE WHEN status = "blocked" THEN 1 ELSE 0 END) as blocked');

        return $this->aggregateByPeriod($query, $start, $end, $granularity, 'created_at');
    }

    protected function getInvoicesData($start, $end, $granularity)
    {
        $query = CarwashInvoice::selectRaw('COUNT(*) as total');

        return $this->aggregateByPeriod($query, $start, $end, $granularity, 'created_at');
    }

    protected function getTotalInvoicesAmount($start, $end)
    {
        return CarwashInvoice::whereBetween('created_at', [$start, $end])->sum('amount');
    }

    protected function getUsageDurationData($start, $end, $granularity)
    {
        $query = CarwashBonusCardStat::selectRaw('SUM(duration_seconds) as total_duration');

        return $this->aggregateByPeriod($query, $start, $end, $granularity, 'start_time');
    }

    protected function aggregateByPeriod($query, $start, $end, $granularity, $dateColumn)
    {
        $labels = [];
        $data = ['total' => [], 'active' => [], 'blocked' => [], 'total_duration' => []];

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
                ->whereBetween($dateColumn, [$current, $next->subSecond()])
                ->first();

            $data['total'][] = $result->total ?? 0;
            if (isset($result->active)) {
                $data['active'][] = $result->active ?? 0;
                $data['blocked'][] = $result->blocked ?? 0;
            }
            if (isset($result->total_duration)) {
                $data['total_duration'][] = $result->total_duration ?? 0;
            }

            $current = $next;
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }
}
