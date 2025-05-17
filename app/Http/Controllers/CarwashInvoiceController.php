<?php

namespace App\Http\Controllers;

use App\Models\CarwashClient;
use App\Models\CarwashInvoice;
use App\Services\CarwashInvoiceService;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CarwashInvoiceController extends Controller
{
    protected $invoiceService;

    public function __construct(CarwashInvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    public function index()
    {
        return view('carwash_invoices.index');
    }

    public function create()
    {
        $clients = CarwashClient::where('status', 'active')
            ->where('invoice_email_required', true)
            ->get();
        return view('carwash_invoices.create', compact('clients'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:carwash_clients,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        $invoice = $this->invoiceService->createInvoice(
            $request->client_id,
            $request->period_start,
            $request->period_end
        );

        return redirect()->route('carwash_invoices.index')
            ->with('success', 'Счет успешно создан.');
    }

    public function show($id)
    {
        $invoice = CarwashInvoice::with('client')->findOrFail($id);
        return view('carwash_invoices.show', compact('invoice'));
    }

    public function destroy($id)
    {
        $invoice = CarwashInvoice::findOrFail($id);
        if ($invoice->pdf_path && file_exists(storage_path('app/' . $invoice->pdf_path))) {
            unlink(storage_path('app/' . $invoice->pdf_path));
        }
        $invoice->delete();

        return redirect()->route('carwash_invoices.index')
            ->with('success', 'Счет успешно удален.');
    }

    public function deleteSelected(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:carwash_invoices,id',
        ]);

        foreach ($request->ids as $id) {
            $invoice = CarwashInvoice::find($id);
            if ($invoice->pdf_path && file_exists(storage_path('app/' . $invoice->pdf_path))) {
                unlink(storage_path('app/' . $invoice->pdf_path));
            }
            $invoice->delete();
        }

        return response()->json(['success' => 'Выбранные счета удалены.']);
    }

    public function getInvoiceData(Request $request)
    {
        $query = CarwashInvoice::with('client');

        // Фильтр по имени клиента
        if ($request->has('client_name') && !empty($request->client_name)) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->client_name . '%');
            });
        }

        // Фильтр по началу периода
        if ($request->has('period_start') && !empty($request->period_start)) {
            $query->whereDate('period_start', '>=', $request->period_start);
        }

        // Фильтр по концу периода
        if ($request->has('period_end') && !empty($request->period_end)) {
            $query->whereDate('period_end', '<=', $request->period_end);
        }

        // Фильтр по статусу отправки
        if ($request->has('sent_status') && !empty($request->sent_status)) {
            if ($request->sent_status === 'sent') {
                $query->whereNotNull('sent_at');
            } elseif ($request->sent_status === 'not_sent') {
                $query->whereNull('sent_at');
            }
        }

        return DataTables::of($query)
            ->addColumn('checkbox', fn($invoice) => '<input type="checkbox" class="select-row" value="' . $invoice->id . '">')
            ->addColumn('client_name', fn($invoice) => $invoice->client->name)
            ->addColumn('action', function ($invoice) {
                return '
                    <a href="' . route('carwash_invoices.show', $invoice->id) . '" class="btn btn-primary btn-sm">Просмотр</a>
                    <form action="' . route('carwash_invoices.destroy', $invoice->id) . '" method="POST" style="display:inline;">
                        ' . csrf_field() . '
                        ' . method_field('DELETE') . '
                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(\'Вы уверены?\')">Удалить</button>
                    </form>';
            })
            ->rawColumns(['checkbox', 'action'])
            ->make(true);
    }
}
