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
        $clients = CarwashClient::where('status', 'active')->get();
        $bonus_cards = CarwashBonusCard::with('client')->get();
        return view('carwash_bonus_cards.index', compact('clients', 'bonus_cards'));
    }

    public function store(StoreCarwashBonusCardRequest $request)
    {
        CarwashBonusCard::create($request->validated());
        return redirect()->route('carwash_bonus_cards.index')
            ->with('success', 'Бонусная карта успешно добавлена.');
    }

    public function show($id)
    {
        return redirect()->route('carwash_bonus_cards.index')
            ->with('error', 'Такой страницы не существует.');
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

        if ($request->filled('client_short_name')) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('short_name', 'like', '%' . $request->input('client_short_name') . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Обработка сортировки, включая связанное поле client_short_name
        if ($request->has('order') && count($request->input('order'))) {
            $orderColumnIndex = $request->input('order.0.column');
            $orderColumnName = $request->input('columns.' . $orderColumnIndex . '.data');
            $orderDir = $request->input('order.0.dir');

            if ($orderColumnName == 'client_short_name') {
                // Используем leftJoin, чтобы не потерять бонусные карты без клиентов (если такие возможны)
                // или если client_id может быть NULL.
                $query->leftJoin('carwash_clients', 'carwash_bonus_cards.client_id', '=', 'carwash_clients.id')
                    ->orderBy('carwash_clients.short_name', $orderDir)
                    ->select('carwash_bonus_cards.*'); // Важно выбрать все поля из основной таблицы
            } else {
                // Стандартная сортировка по полям основной таблицы
                // Убедимся, что имя колонки безопасно для использования в orderBy
                $allowedSortableColumns = ['name', 'card_number', 'status', 'rate_per_minute']; // Добавьте другие разрешенные колонки
                if (in_array($orderColumnName, $allowedSortableColumns)) {
                    $query->orderBy($orderColumnName, $orderDir);
                }
            }
        } else {
            // Сортировка по умолчанию, если не указана в запросе
            $query->orderBy('carwash_bonus_cards.name', 'asc');
        }


        return DataTables::of($query)
            ->addColumn('checkbox', fn($card) => '<input type="checkbox" class="select-row" value="' . $card->id . '">')
            ->addColumn('client_short_name', function(CarwashBonusCard $card) { // Явное указание типа для автодополнения
                return $card->client->short_name ?? '-';
            })
            ->addColumn('client_id', fn($card) => $card->client_id)
            ->addColumn('action', function (CarwashBonusCard $card) {
                $editBtn = '<button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editBonusCardModal' . $card->id . '" title="Редактировать"><i class="fas fa-edit"></i></button>';
                $deleteForm = '
                    <form action="' . route('carwash_bonus_cards.destroy', $card->id) . '" method="POST" style="display:inline;" class="delete-form">
                        ' . csrf_field() . '
                        ' . method_field('DELETE') . '
                        <button type="submit" class="btn btn-sm btn-outline-danger delete-single" title="Удалить"
                                data-card-name="' . htmlspecialchars($card->name) . '"
                                data-card-number="' . htmlspecialchars($card->card_number) . '">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>';
                return '<div class="action-buttons">' . $editBtn . ' ' . $deleteForm . '</div>';
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
