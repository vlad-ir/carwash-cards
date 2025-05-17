<?php

namespace App\Http\Controllers;

use App\Models\CarwashBonusCard;
use App\Models\CarwashClient;
use App\Http\Requests\StoreCarwashBonusCardRequest;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CarwashBonusCardController extends Controller
{
    public function index()
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
        $validated = $request->validated();

        CarwashBonusCard::create([
            'name' => $validated['name'],
            'card_number' => $validated['card_number'],
            'discount_percentage' => $validated['discount_percentage'],
            'balance' => $validated['balance'],
            'status' => $validated['status'],
            'car_license_plate' => $validated['car_license_plate'] ?? null,
            'rate_per_minute' => $validated['rate_per_minute'],
            'invoice_required' => $validated['invoice_required'],
            'client_id' => $validated['client_id'],
        ]);

        return redirect()->route('carwash_bonus_cards.index')->with('success', 'Бонусная карта успешно добавлена.');
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

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'card_number' => 'required|string|max:20|unique:carwash_bonus_cards,card_number,' . $id,
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'balance' => 'required|date_format:H:i:s',
            'status' => 'required|in:active,inactive,blocked',
            'car_license_plate' => 'nullable|string|max:20',
            'rate_per_minute' => 'required|numeric|min:0',
            'invoice_required' => 'boolean',
            'client_id' => 'required|exists:carwash_clients,id',
        ]);

        $bonusCard = CarwashBonusCard::findOrFail($id);
        $bonusCard->update($request->all());

        return redirect()->route('carwash_bonus_cards.index')->with('success', 'Бонусная карта успешно обновлена.');
    }

    public function destroy($id)
    {
        $bonusCard = CarwashBonusCard::findOrFail($id);
        $bonusCard->delete();

        return redirect()->route('carwash_bonus_cards.index')->with('success', 'Бонусная карта успешно удалена.');
    }

    public function getBonusCardData(Request $request)
    {
        $query = CarwashBonusCard::with('client');

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->has('card_number')) {
            $query->where('card_number', 'like', '%' . $request->card_number . '%');
        }
        if ($request->has('car_license_plate')) {
            $query->where('car_license_plate', 'like', '%' . $request->car_license_plate . '%');
        }
        if ($request->has('client_short_name')) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('short_name', 'like', '%' . $request->client_short_name . '%');
            });
        }

        return DataTables::of($query)
            ->addColumn('checkbox', fn($card) => '<input type="checkbox" class="select-row" value="' . $card->id . '">')
            ->addColumn('client_short_name', fn($card) => $card->client->short_name)
            ->addColumn('action', function ($card) {
                return '
                    <a href="' . route('carwash_bonus_cards.show', $card->id) . '" class="btn btn-primary btn-sm">Просмотр</a>
                    <a href="' . route('carwash_bonus_cards.edit', $card->id) . '" class="btn btn-warning btn-sm">Редактировать</a>
                    <form action="' . route('carwash_bonus_cards.destroy', $card->id) . '" method="POST" style="display:inline;">
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
        CarwashBonusCard::whereIn('id', $ids)->delete();
        return response()->json(['status' => 'success']);
    }
}
