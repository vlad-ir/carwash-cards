<?php

namespace App\Http\Controllers;

use App\Models\CarwashClient;
use Illuminate\Http\Request;
use App\Http\Requests\StoreCarwashClientRequest;
use Yajra\DataTables\Facades\DataTables;


class CarwashClientController extends Controller
{
    public function index()
    {
        return view('carwash_clients.index');
    }

    public function create()
    {
        return view('carwash_clients.create');
    }

    public function store(StoreCarwashClientRequest $request)
    {
        $validated = $request->validated();

        $client = CarwashClient::create([
            'short_name' => $validated['short_name'],
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'unp' => $validated['unp'],
            'bank_account_number' => $validated['bank_account_number'],
            'bank_bic' => $validated['bank_bic'],
            'status' => $validated['status'],
            'invoice_email_required' => $validated['invoice_email_required'],
            'invoice_email_date' => $validated['invoice_email_date'],
            'postal_address' => $validated['postal_address'],
            'bank_postal_address' => $validated['bank_postal_address'],
        ]);

        if (isset($validated['bonus_cards'])) {
            foreach ($validated['bonus_cards'] as $card) {
                $client->bonusCards()->create([
                    'card_number' => $card['card_number'],
                    'name' => $card['name'],
                    'discount_percentage' => $card['discount_percentage'],
                    'balance' => $card['balance'],
                    'status' => $card['status'],
                    'car_license_plate' => $card['car_license_plate'] ?? null,
                    'rate_per_minute' => $card['rate_per_minute'],
                    'invoice_required' => $card['invoice_required'],
                ]);
            }
        }

        return redirect()->route('carwash_clients.index')->with('success', 'Клиент успешно добавлен.');
    }

    public function show($id)
    {
        $client = CarwashClient::findOrFail($id);
        return view('carwash_clients.show', compact('client'));
    }

    public function edit($id)
    {
        $client = CarwashClient::findOrFail($id);
        return view('carwash_clients.edit', compact('client'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'short_name' => 'required|string|max:100',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:100',
            'phone' => 'required|string|max:20',
            'unp' => 'required|string|max:9',
            'bank_account_number' => 'required|string|max:50',
            'bank_bic' => 'required|string|max:20',
            'postal_address' => 'required|string|max:255',
            'bank_postal_address' => 'required|string|max:255',
        ]);

        $client = CarwashClient::findOrFail($id);
        $client->update($request->all());

        return redirect()->route('carwash_clients.index')->with('success', 'Клиент успешно обновлен.');
    }

    public function destroy($id)
    {
        $client = CarwashClient::findOrFail($id);
        $client->delete();

        return redirect()->route('carwash_clients.index')->with('success', 'Клиент успешно удален.');
    }

    public function getClientData(Request $request)
    {
        $query = CarwashClient::query();

        if ($request->has('short_name')) {
            $query->where('short_name', 'like', '%' . $request->short_name . '%');
        }

        if ($request->has('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }

        if ($request->has('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }

        if ($request->has('unp')) {
            $query->where('unp', 'like', '%' . $request->unp . '%');
        }

        return DataTables::of($query)
            ->addColumn('bonus_cards_count', function ($client) {
                return $client->bonusCards()->count();
            })
            ->addColumn('action', function ($client) {
                return '
                <a href="' . route('carwash_clients.show', $client->id) . '" class="btn btn-primary btn-sm">Просмотр</a>
                <a href="' . route('carwash_clients.edit', $client->id) . '" class="btn btn-warning btn-sm">Редактировать</a>
                <form action="' . route('carwash_clients.destroy', $client->id) . '" method="POST" style="display:inline;">
                    ' . csrf_field() . '
                    ' . method_field('DELETE') . '
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(\'Вы уверены?\')">Удалить</button>
                </form>
            ';
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function deleteSelected(Request $request)
    {
        $ids = $request->input('ids');
        CarwashClient::whereIn('id', $ids)->delete();
        return response()->json(['status' => 'success']);
    }
}
