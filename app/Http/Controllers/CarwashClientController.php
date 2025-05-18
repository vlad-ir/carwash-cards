<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCarwashClientRequest;
use App\Models\CarwashClient;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CarwashClientController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = CarwashClient::query();

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

            return DataTables::of($query)
                ->addColumn('checkbox', fn($client) => '<input type="checkbox" class="select-row" value="' . $client->id . '">')
                ->addColumn('action', function ($client) {
                    return '
                        <div class="action-buttons">
                            <a href="' . route('carwash_clients.show', $client->id) . '" class="btn btn-sm btn-outline-primary" title="Просмотр"><i class="fas fa-eye"></i></a>
                            <a href="' . route('carwash_clients.edit', $client->id) . '" class="btn btn-sm btn-outline-warning" title="Редактировать"><i class="fas fa-edit"></i></a>
                            <form action="' . route('carwash_clients.destroy', $client->id) . '" method="POST" style="display:inline;">
                                ' . csrf_field() . '
                                ' . method_field('DELETE') . '
                                <button type="submit" class="btn btn-sm btn-outline-danger delete-single" title="Удалить" data-short-name="' . htmlspecialchars($client->short_name) . '"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>';
                })
                ->rawColumns(['checkbox', 'action'])
                ->make(true);
        }

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

    public function deleteSelected(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:carwash_clients,id',
        ]);

        CarwashClient::whereIn('id', $request->ids)->delete();

        return response()->json(['success' => 'Выбранные клиенты удалены.']);
    }
}
