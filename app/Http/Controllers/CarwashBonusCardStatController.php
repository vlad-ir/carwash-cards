<?php

namespace App\Http\Controllers;

use App\Http\Requests\CarwashBonusCardStatRequest;
use App\Models\CarwashBonusCard;
use App\Models\CarwashBonusCardStat;
use App\Services\CarwashCsvImportService;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CarwashBonusCardStatController extends Controller
{
    public function index()
    {
        return view('carwash_bonus_card_stats.index');
    }

    public function create()
    {
        $cards = CarwashBonusCard::all();
        return view('carwash_bonus_card_stats.create', compact('cards'));
    }

    public function store(CarwashBonusCardStatRequest $request)
    {
        CarwashBonusCardStat::create($request->validated());

        return redirect()->route('carwash_bonus_card_stats.index')->with('success', 'Запись статистики успешно добавлена.');
    }

    public function show($id)
    {
        $stat = CarwashBonusCardStat::with('card')->findOrFail($id);
        return view('carwash_bonus_card_stats.show', compact('stat'));
    }

    public function edit($id)
    {
        $stat = CarwashBonusCardStat::findOrFail($id);
        $cards = CarwashBonusCard::all();
        return view('carwash_bonus_card_stats.edit', compact('stat', 'cards'));
    }

    public function update(CarwashBonusCardStatRequest $request, $id)
    {
        $stat = CarwashBonusCardStat::findOrFail($id);
        $stat->update($request->validated());

        return redirect()->route('carwash_bonus_card_stats.index')->with('success', 'Запись статистики успешно обновлена.');
    }

    public function destroy($id)
    {
        $stat = CarwashBonusCardStat::findOrFail($id);
        $stat->delete();

        return redirect()->route('carwash_bonus_card_stats.index')->with('success', 'Запись статистики успешно удалена.');
    }

    public function getStatData(Request $request)
    {
        $query = CarwashBonusCardStat::with('card');

        // Фильтр по дате начала (точная дата или диапазон)
        if ($request->has('start_time') && $request->start_time) {
            $query->whereDate('start_time', $request->start_time);
        }

        return DataTables::eloquent($query)
            ->addColumn('checkbox', fn($stat) => '<input type="checkbox" class="select-row" value="' . $stat->id . '">')
            ->addColumn('card_number', fn($stat) => $stat->card?->card_number ?? 'N/A')
            ->editColumn('start_time', fn($stat) => $stat->start_time->format('d.m.Y H:i:s'))
            ->editColumn('duration_seconds', fn($stat) => gmdate("H:i:s", $stat->duration_seconds))
            ->editColumn('remaining_balance_seconds', fn($stat) => gmdate("H:i:s", $stat->remaining_balance_seconds))
            ->editColumn('import_date', fn($stat) => optional($stat->import_date)->format('d.m.Y'))
            ->addColumn('action', function ($stat) {
                return '
                <div class="action-buttons">
                    <a href="' . route('carwash_bonus_card_stats.edit', $stat->id) . '" class="btn btn-sm btn-outline-warning" title="Редактировать"><i class="fas fa-edit"></i></a>
                    <form action="' . route('carwash_bonus_card_stats.destroy', $stat->id) . '" method="POST" style="display:inline;">
                        ' . csrf_field() . '
                        ' . method_field('DELETE') . '
                        <button type="submit" class="btn btn-sm btn-outline-danger delete-single"
                                title="Удалить" data-card-name="' . htmlspecialchars($stat->card?->name ?? 'Неизвестная карта') . '"
                                data-card-number="' . htmlspecialchars($stat->card?->card_number ?? '—') . '">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>';
            })
            ->rawColumns(['checkbox', 'action'])
            ->toJson();
    }

    public function deleteSelected(Request $request)
    {
        $ids = $request->input('ids');
        CarwashBonusCardStat::whereIn('id', $ids)->delete();
        return response()->json(['success' => 'Выбранные записи удалены.']);
    }

    public function showUploadForm()
    {
        return view('carwash_bonus_card_stats.upload');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('csv_file');
        $fileName = $file->getClientOriginalName();
        $file->storeAs('import_stat', $fileName, 'public');

        $service = new CarwashCsvImportService();
        $service->importCsv('import_stat/' . $fileName, preg_replace('/\.csv$/', '', $fileName));

        return redirect()->route('carwash_bonus_card_stats.index')->with('success', 'Файл успешно загружен и обработан.');
    }
}
