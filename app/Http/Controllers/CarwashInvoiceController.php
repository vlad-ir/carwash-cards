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
use Illuminate\Database\Eloquent\Builder;

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
     * Display a listing of the invoices.
     * Data will be fetched via AJAX by DataTables.
     *
     * @return \Illuminate\View\View
     */
    public function index(): View
    {
        // Filters will be passed to the view to pre-fill the filter form if needed
        // but the actual data loading and filtering is done by getInvoicesData
        return view('carwash_invoices.index');
    }

    /**
     * Apply filters to the invoice query.
     *
     * @param Builder $query
     * @param Request $request
     * @return Builder
     */
    private function applyInvoiceFilters(Builder $query, Request $request): Builder
    {
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
                // Silently ignore for now
            }
        }

        if ($request->filled('period_end')) {
            try {
                $periodEndDate = Carbon::parse($request->input('period_end'))->endOfMonth();
                $query->where('period_end', '<=', $periodEndDate);
            } catch (\Exception $e) {
                // Silently ignore
            }
        }

        if ($request->filled('invoice_date')) {
            try {
                $invoiceDate = Carbon::parse($request->input('invoice_date'))->toDateString();
                $query->whereDate('sent_at', $invoiceDate);
            } catch (\Exception $e) {
                // Silently ignore
            }
        }
        return $query;
    }

    /**
     * Provide data for DataTables AJAX calls for invoices.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function getInvoicesData(Request $request): JsonResponse
    {
        $query = CarwashInvoice::with('client:id,short_name')->select('carwash_invoices.*'); // Select all from invoices table

        $query = $this->applyInvoiceFilters($query, $request);

        return DataTables::of($query)
            ->addColumn('checkbox', fn($invoice) => '<input type="checkbox" class="select-row" value="' . $invoice->id . '">')
            ->addColumn('client_id', fn($invoice) => $invoice->client_id)
            ->addColumn('client_short_name', fn($invoice) => $invoice->client->short_name ?? 'N/A')
            ->editColumn('period_start', fn($invoice) => Carbon::parse($invoice->period_start)->isoFormat('MMMM YYYY'))
            ->editColumn('period_end', fn($invoice) => Carbon::parse($invoice->period_end)->isoFormat('MMMM YYYY'))
            ->editColumn('sent_at', fn($invoice) => $invoice->sent_at ? Carbon::parse($invoice->sent_at)->format('d.m.Y') : 'N/A')
            ->addColumn('file_link', function($invoice) {
                if ($invoice->file_path) {
                    $fileName = basename($invoice->file_path);
                    $relativePath = 'invoices/' . $fileName;
                    if (Storage::disk('public')->exists($relativePath)) {
                        return '<a href="'.Storage::disk('public')->url($relativePath).'" target="_blank" class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel"></i> XLS</a>';
                    }
                }
                return '<span class="text-muted">Нет файла</span>';
            })
            ->addColumn('action', function (CarwashInvoice $invoice) { // Route model binding not directly usable here, but type hint is good
                return '
                    <div class="action-buttons">
                        <a href="' . route('carwash_invoices.show', $invoice->id) . '" class="btn btn-sm btn-outline-primary" title="Просмотр"><i class="fas fa-eye"></i></a>
                        <form action="' . route('carwash_invoices.destroy', $invoice->id) . '" method="POST" style="display:inline;" class="delete-form">
                            ' . csrf_field() . '
                            ' . method_field('DELETE') . '
                            <button type="submit" class="btn btn-sm btn-outline-danger delete-single"
                                    title="Удалить" data-invoice-id="' . $invoice->id . '">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>';
            })
            ->rawColumns(['checkbox', 'action', 'file_link'])
            ->make(true);
    }

    /**
     * Get all invoice IDs matching the current filters.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllInvoiceIds(Request $request): JsonResponse
    {
        $query = CarwashInvoice::query();
        $query = $this->applyInvoiceFilters($query, $request);
        $ids = $query->pluck('id')->toArray();
        return response()->json(['ids' => $ids]);
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
     * @param \App\Models\CarwashInvoice $carwashInvoice
     * @return \Illuminate\View\View
     */
    public function show(CarwashInvoice $carwashInvoice): View // Route model binding
    {
        // $carwashInvoice is already loaded via Route Model Binding
        // Ensure client is loaded if accessed in view, though getInvoicesData loads it.
        $carwashInvoice->loadMissing('client:id,short_name');

        $carwashInvoice->download_url = null;
        if ($carwashInvoice->file_path) {
            $relativePath = 'invoices/' . basename($carwashInvoice->file_path);
            if (Storage::disk('public')->exists($relativePath)) {
                $carwashInvoice->download_url = Storage::disk('public')->url($relativePath);
            }
        }
        return view('carwash_invoices.show', ['invoice' => $carwashInvoice]); // Pass as 'invoice' for consistency if view expects that
    }

    /**
     * Remove the specified invoice from storage.
     *
     * @param \App\Models\CarwashInvoice $carwashInvoice
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(CarwashInvoice $carwashInvoice): RedirectResponse // Route model binding
    {
        if ($carwashInvoice->file_path) {
            $publicPathBase = storage_path('app/public/');
            if (strpos($carwashInvoice->file_path, $publicPathBase) === 0) {
                $relativePathInsidePublic = str_replace($publicPathBase, '', $carwashInvoice->file_path);
                if (Storage::disk('public')->exists($relativePathInsidePublic)) {
                    Storage::disk('public')->delete($relativePathInsidePublic);
                }
            }
        }
        $carwashInvoice->delete();

        return redirect()->route('carwash_invoices.index')
            ->with('success', 'Счет #' . $carwashInvoice->id . ' успешно удален.');
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
        $deletedCount = 0;
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
                $deletedCount++;
            }
        }
        return response()->json(['success' => "Выбрано счетов для удаления: {$deletedCount}. Успешно удалено."]);
    }

    // The old getInvoiceData is replaced by getInvoicesData for DataTables
    // If the old one was used by something else, it might need to be kept or that other part updated.
    // For this task, I am replacing it with the new DataTables source method.
}
