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

        if ($request->has('start_time')) {
            $query->whereDate('start_time', $request->start_time);
        }

        return DataTables::of($query)
            ->addColumn('checkbox', fn($stat) => '<input type="checkbox" class="select-row" value="' . $stat->id . '">')
            ->addColumn('card_number', fn($stat) => $stat->card->card_number ?? 'N/A')
            ->addColumn('action', function ($stat) {
                return '
                    <a href="' . route('carwash_bonus_card_stats.show', $stat->id) . '" class="btn btn-primary btn-sm">Просмотр</a>
                    <a href="' . route('carwash_bonus_card_stats.edit', $stat->id) . '" class="btn btn-warning btn-sm">Редактировать</a>
                    <form action="' . route('carwash_bonus_card_stats.destroy', $stat->id) . '" method="POST" style="display:inline;">
                        ' . csrf_field() . '
                        ' . method_field('DELETE') . '
                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(\'Вы уверены?\')">Удалить</button>
                    </form>';
            })
            ->rawColumns(['checkbox', 'action'])
            ->make(true);
    }

    public function deleteSelected(Request $request)
    {
        $ids = $request->input('ids');
        CarwashBonusCardStat::whereIn('id', $ids)->delete();
        return response()->json(['status' => 'success']);
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
