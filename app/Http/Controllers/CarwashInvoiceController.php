<?php

namespace App\Http\Controllers;

use App\Models\CarwashClient;
use App\Models\CarwashInvoice;
use App\Services\CarwashInvoiceService;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

/**
 * Class CarwashInvoiceController
 * Handles HTTP requests related to carwash invoices.
 * @package App\Http\Controllers
 */
class CarwashInvoiceController extends Controller
{
    protected CarwashInvoiceService $invoiceService;

    /**
     * CarwashInvoiceController constructor.
     * @param CarwashInvoiceService $invoiceService
     */
    public function __construct(CarwashInvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Display a listing of the invoices with filtering options.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request): View
    {
        $query = CarwashInvoice::with('client')->orderBy('sent_at', 'desc');

        // Apply filters
        if ($request->filled('client_name')) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('short_name', 'like', '%' . $request->input('client_name') . '%');
            });
        }

        if ($request->filled('period_start')) {
            try {
                $periodStartDate = Carbon::parse($request->input('period_start'))->startOfMonth();
                $query->where('period_start', '>=', $periodStartDate);
            } catch (\Exception $e) {
                // Silently ignore invalid date format for filters, or add error handling
            }
        }

        if ($request->filled('period_end')) {
            try {
                $periodEndDate = Carbon::parse($request->input('period_end'))->endOfMonth();
                $query->where('period_end', '<=', $periodEndDate);
            } catch (\Exception $e) {
                // Silently ignore invalid date format
            }
        }

        if ($request->filled('invoice_date')) {
            try {
                $invoiceDate = Carbon::parse($request->input('invoice_date'))->toDateString();
                $query->whereDate('sent_at', $invoiceDate);
            } catch (\Exception $e) {
                // Silently ignore invalid date format
            }
        }

        $invoices = $query->paginate(15);

        return view('carwash_invoices.index', [
            'invoices' => $invoices,
            'filters' => $request->only(['client_name', 'period_start', 'period_end', 'invoice_date']),
        ]);
    }

    /**
     * Show the form for creating a new invoice (manual creation).
     *
     * @return \Illuminate\View\View
     */
    public function create(): View
    {
        $clients = CarwashClient::where('status', 'active')
            ->where('invoice_email_required', true)
            ->get();
        return view('carwash_invoices.create', compact('clients'));
    }

    /**
     * Store a newly created invoice in storage (manual creation).
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'client_id' => 'required|exists:carwash_clients,id',
            'period_date' => 'required|date',
        ]);

        $client = CarwashClient::findOrFail($request->client_id);
        $periodDate = Carbon::parse($request->period_date);

        $success = $this->invoiceService->createAndSendInvoiceForClient($client, $periodDate);

        if ($success) {
            return redirect()->route('carwash_invoices.index')
                ->with('success', 'Счет успешно создан и отправлен.');
        } else {
            return redirect()->back()
                ->with('error', 'Не удалось создать счет. Проверьте логи.');
        }
    }

    /**
     * Display the specified invoice.
     *
     * @param string $id
     * @return \Illuminate\View\View
     */
    public function show(string $id): View
    {
        $invoice = CarwashInvoice::with('client')->findOrFail($id);

        $invoice->download_url = null;
        if ($invoice->file_path) {
            $relativePath = 'invoices/' . basename($invoice->file_path);
            if (Storage::disk('public')->exists($relativePath)) {
                $invoice->download_url = Storage::disk('public')->url($relativePath);
            }
        }
        return view('carwash_invoices.show', compact('invoice'));
    }

    /**
     * Remove the specified invoice from storage.
     *
     * @param string $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(string $id): RedirectResponse
    {
        $invoice = CarwashInvoice::findOrFail($id);
        if ($invoice->file_path) {
            $publicPathBase = storage_path('app/public/');
            // Check if file_path starts with the public base path
            if (strpos($invoice->file_path, $publicPathBase) === 0) {
                $relativePathInsidePublic = str_replace($publicPathBase, '', $invoice->file_path);
                if (Storage::disk('public')->exists($relativePathInsidePublic)) {
                    Storage::disk('public')->delete($relativePathInsidePublic);
                }
            }
        }
        $invoice->delete();

        return redirect()->route('carwash_invoices.index')
            ->with('success', 'Счет успешно удален.');
    }

    /**
     * Remove selected invoices from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteSelected(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:carwash_invoices,id',
        ]);

        $publicPathBase = storage_path('app/public/');
        foreach ($request->ids as $id) {
            $invoice = CarwashInvoice::find($id);
            if ($invoice) {
                if ($invoice->file_path && strpos($invoice->file_path, $publicPathBase) === 0) {
                    $relativePathInsidePublic = str_replace($publicPathBase, '', $invoice->file_path);
                    if (Storage::disk('public')->exists($relativePathInsidePublic)) {
                        Storage::disk('public')->delete($relativePathInsidePublic);
                    }
                }
                $invoice->delete();
            }
        }
        return response()->json(['success' => 'Выбранные счета удалены.']);
    }

    /**
     * Provide data for DataTables AJAX calls.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function getInvoiceData(Request $request): JsonResponse
    {
        $query = CarwashInvoice::with('client');

        if ($request->filled('client_name')) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('short_name', 'like', '%' . $request->input('client_name') . '%');
            });
        }
        if ($request->filled('period_start')) {
            $query->whereDate('period_start', '>=', Carbon::parse($request->input('period_start'))->startOfMonth());
        }
        if ($request->filled('period_end')) {
            $query->whereDate('period_end', '<=', Carbon::parse($request->input('period_end'))->endOfMonth());
        }
        if ($request->filled('invoice_date')) {
            $query->whereDate('sent_at', '=', Carbon::parse($request->input('invoice_date')));
        }
        if ($request->has('sent_status') && !empty($request->sent_status)) {
            if ($request->sent_status === 'sent') {
                $query->whereNotNull('sent_at');
            } elseif ($request->sent_status === 'not_sent') {
                $query->whereNull('sent_at');
            }
        }

        return DataTables::of($query)
            ->addColumn('checkbox', fn($invoice) => '<input type="checkbox" class="select-row" value="' . $invoice->id . '">')
            ->editColumn('client.short_name', fn($invoice) => $invoice->client->short_name ?? 'N/A')
            ->editColumn('period_start', fn($invoice) => Carbon::parse($invoice->period_start)->isoFormat('MMMM YYYY'))
            ->editColumn('period_end', fn($invoice) => Carbon::parse($invoice->period_end)->isoFormat('MMMM YYYY'))
            ->editColumn('sent_at', fn($invoice) => $invoice->sent_at ? Carbon::parse($invoice->sent_at)->format('d.m.Y') : 'N/A')
            ->addColumn('file_link', function($invoice){
                if ($invoice->file_path) {
                    $relativePath = 'invoices/' . basename($invoice->file_path);
                    if (Storage::disk('public')->exists($relativePath)) {
                        return '<a href="'.Storage::disk('public')->url($relativePath).'" target="_blank">Скачать XLS</a>';
                    }
                }
                return 'Нет файла';
            })
            ->addColumn('action', function ($invoice) {
                return '
                    <a href="' . route('carwash_invoices.show', $invoice->id) . '" class="btn btn-primary btn-sm">Просмотр</a>
                    <form action="' . route('carwash_invoices.destroy', $invoice->id) . '" method="POST" style="display:inline;">
                        ' . csrf_field() . '
                        ' . method_field('DELETE') . '
                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(\'Вы уверены?\')">Удалить</button>
                    </form>';
            })
            ->rawColumns(['checkbox', 'action', 'file_link'])
            ->make(true);
    }
}
