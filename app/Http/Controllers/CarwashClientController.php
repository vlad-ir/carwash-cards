<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCarwashClientRequest;
use App\Models\CarwashClient;
use App\Services\CarwashInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class CarwashClientController extends Controller
{
    public function index(Request $request)
    {
        return view('carwash_clients.index');
    }

    public function create()
    {
        return view('carwash_clients.create');
    }

    public function store(StoreCarwashClientRequest $request)
    {
        CarwashClient::create($request->validated());

        return redirect()->route('carwash_clients.index')
            ->with('success', 'Клиент успешно создан.');
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

    public function update(StoreCarwashClientRequest $request, $id)
    {
        $client = CarwashClient::findOrFail($id);
        $client->update($request->validated());

        return redirect()->route('carwash_clients.index')
            ->with('success', 'Клиент успешно обновлен.');
    }

    public function destroy($id)
    {
        $client = CarwashClient::findOrFail($id);
        $client->delete();

        return redirect()->route('carwash_clients.index')
            ->with('success', 'Клиент успешно удален.');
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('name')) {
            $query->where('short_name', 'like', '%' . $request->input('name') . '%');
        }

        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->input('email') . '%');
        }

        if ($request->filled('unp')) {
            $query->where('unp', 'like', '%' . $request->input('unp') . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('invoice_email_required')) {
            $query->where('invoice_email_required', $request->input('invoice_email_required'));
        }
        return $query;
    }

    public function getClientData(Request $request)
    {
        $query = CarwashClient::withCount('bonusCards');
        $query = $this->applyFilters($query, $request);
        $dataTable = DataTables::of($query);

        $dataTable->orderColumn('bonus_cards_count', function ($query, $order) {
            $query->orderBy('bonus_cards_count', $order);
        });

        return $dataTable
            ->addColumn('checkbox', fn($client) => '<input type="checkbox" class="select-row" value="' . $client->id . '">')
            ->addColumn('bonus_cards_count', fn($client) => $client->bonus_cards_count)
            ->addColumn('action', function ($client) {
                return '
                    <div class="action-buttons">
                        <a href="' . route('carwash_clients.show', $client->id) . '" class="btn btn-sm btn-outline-primary" title="Просмотр"><i class="fas fa-eye"></i></a>
                        <a href="' . route('carwash_clients.edit', $client->id) . '" class="btn btn-sm btn-outline-warning" title="Редактировать"><i class="fas fa-edit"></i></a>
                        <form action="' . route('carwash_clients.destroy', $client->id) . '" method="POST" style="display:inline;">
                            ' . csrf_field() . '
                            ' . method_field('DELETE') . '
                            <button type="submit" class="btn btn-sm btn-outline-danger delete-single"
                                    title="Удалить" data-short-name="' . htmlspecialchars($client->short_name) . '">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>';
            })
            ->rawColumns(['checkbox', 'action'])
            ->make(true);
    }

    public function getAllClientIds(Request $request)
    {
        $query = CarwashClient::query();
        $query = $this->applyFilters($query, $request);
        $ids = $query->pluck('id');
        return response()->json(['ids' => $ids]);
    }

    public function getClientBonusCardsData(Request $request, $clientId)
    {
        $client = CarwashClient::findOrFail($clientId);
        $bonusCards = $client->bonusCards(); // Получаем Builder, а не коллекцию

        return DataTables::of($bonusCards)
            ->addColumn('name', fn($card) => $card->name)
            ->addColumn('card_number', fn($card) => $card->card_number)
            ->addColumn('status', function ($card) {
                return $card->status === 'active'
                    ? '<i class="fas fa-check text-success"></i>'
                    : '<i class="fas fa-ban text-danger"></i>';
            })
            ->addColumn('rate_per_minute', fn($card) => $card->rate_per_minute ? number_format($card->rate_per_minute, 2, ',', ' ') : '-')
            ->rawColumns(['status'])
            ->make(true);
    }

    public function deleteSelected(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:carwash_clients,id',
        ]);

        CarwashClient::whereIn('id', $request->ids)->delete();
        return response()->json(['success' => 'Выбранные клиенты удалены.']);
    }

    public function createInvoicesForSelected(Request $request, CarwashInvoiceService $invoiceService)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:carwash_clients,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:' . (date('Y') - 10) . '|max:' . (date('Y') + 1),
            'send_email' => 'required|boolean',
        ]);

        $clientIds = $request->ids;
        $month = $request->month;
        $year = $request->year;
        $sendEmail = $request->send_email;
        $successCount = 0;
        $errorCount = 0;

        foreach ($clientIds as $clientId) {
            $client = CarwashClient::find($clientId);
            if ($client) {
                try {
                    // Передаем месяц, год и флаг отправки email в сервис
                    $result = $invoiceService->createAndSendInvoiceForClient($client, $month, $year, $sendEmail);
                    if ($result) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                } catch (\Exception $e) {
                    Log::error("Error creating invoice for client {$clientId}: " . $e->getMessage());
                    $errorCount++;
                }
            } else {
                $errorCount++;
            }
        }

        if ($successCount > 0 && $errorCount === 0) {
            return response()->json(['success' => "Счета успешно созданы для {$successCount} клиентов."]);
        } elseif ($successCount > 0 && $errorCount > 0) {
            return response()->json(['success' => "Счета созданы для {$successCount} клиентов, но для {$errorCount} клиентов произошла ошибка."]);
        } elseif ($successCount === 0 && $errorCount > 0) {
            return response()->json(['error' => "Не удалось создать счета ни для одного из {$errorCount} выбранных клиентов."], 500);
        } else {
            return response()->json(['error' => "Не выбрано клиентов для создания счетов."], 400);
        }
    }
}
