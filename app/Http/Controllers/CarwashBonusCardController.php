<?php

namespace App\Http\Controllers;

use App\Models\CarwashBonusCard;
use App\Models\CarwashClient;
use App\Http\Requests\StoreCarwashBonusCardRequest;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CarwashBonusCardController extends Controller
{
    public function index(Request $request)
    {
        return view('carwash_bonus_cards.index');
    }

    public function create()
    {
        $clients = CarwashClient::where('status', 'active')->get();
        return view('carwash_bonus_cards.create', compact('clients'));
    }

    public function store(StoreCarwashBonusCardRequest $request)
    {
        CarwashBonusCard::create($request->validated());

        return redirect()->route('carwash_bonus_cards.index')
            ->with('success', 'Бонусная карта успешно добавлена.');
    }

    public function show($id)
    {
        $bonusCard = CarwashBonusCard::with('client')->findOrFail($id);
        return view('carwash_bonus_cards.show', compact('bonusCard'));
    }

    public function edit($id)
    {
        $bonusCard = CarwashBonusCard::findOrFail($id);
        $clients = CarwashClient::where('status', 'active')->get();

        return view('carwash_bonus_cards.edit', compact('bonusCard', 'clients'));
    }

    public function update(StoreCarwashBonusCardRequest $request, $id)
    {
        $bonusCard = CarwashBonusCard::findOrFail($id);
        $bonusCard->update($request->validated());

        return redirect()->route('carwash_bonus_cards.index')
            ->with('success', 'Бонусная карта успешно обновлена.');
    }

    public function destroy($id)
    {
        $bonusCard = CarwashBonusCard::findOrFail($id);
        $bonusCard->delete();

        return redirect()->route('carwash_bonus_cards.index')
            ->with('success', 'Бонусная карта успешно удалена.');
    }

    public function getBonusCardData(Request $request)
    {
        $query = CarwashBonusCard::with('client');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        if ($request->filled('card_number')) {
            $query->where('card_number', 'like', '%' . $request->input('card_number') . '%');
        }

        if ($request->filled('car_license_plate')) {
            $query->where('car_license_plate', 'like', '%' . $request->input('car_license_plate') . '%');
        }

        if ($request->filled('client_short_name')) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('short_name', 'like', '%' . $request->input('client_short_name') . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return DataTables::of($query)
            ->addColumn('checkbox', fn($card) => '<input type="checkbox" class="select-row" value="' . $card->id . '">')
            ->addColumn('client_short_name', fn($card) => $card->client->short_name ?? '-')
            ->addColumn('action', function ($card) {
                return '
                    <div class="action-buttons">
                        <a href="' . route('carwash_bonus_cards.show', $card->id) . '" class="btn btn-sm btn-outline-primary" title="Просмотр"><i class="fas fa-eye"></i></a>
                        <a href="' . route('carwash_bonus_cards.edit', $card->id) . '" class="btn btn-sm btn-outline-warning" title="Редактировать"><i class="fas fa-edit"></i></a>
                        <form action="' . route('carwash_bonus_cards.destroy', $card->id) . '" method="POST" style="display:inline;">
                            ' . csrf_field() . '
                            ' . method_field('DELETE') . '
                            <button type="submit" class="btn btn-sm btn-outline-danger delete-single" title="Удалить" data-card-name="' . htmlspecialchars($card->name) . '" data-card-number="' . htmlspecialchars($card->card_number) . '"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>';
            })
            ->rawColumns(['checkbox', 'action'])
            ->make(true);
    }

    public function deleteSelected(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:carwash_bonus_cards,id',
        ]);

        CarwashBonusCard::whereIn('id', $request->ids)->delete();

        return response()->json(['success' => 'Выбранные бонусные карты удалены.']);
    }
}
