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
            ->editColumn('sent_at', fn($invoice) => $invoice->sent_at ? Carbon::parse($invoice->sent_at)->format('d.m.Y H:i') : 'N/A') // Добавлено время
            ->addColumn('sent_to_email_at', function ($invoice) {
                if ($invoice->sent_to_email_at) {
                    return Carbon::parse($invoice->sent_to_email_at)->format('d.m.Y H:i');
                }
                return $invoice->client && $invoice->client->email ? 'Не отправлен' : 'Нет email у клиента';
            })
            ->addColumn('file_link', function($invoice) {
                if ($invoice->file_path) {
                    // file_path теперь 'public/invoices/...'
                    // Storage::url преобразует 'public/path/to/file.xls' в '/storage/path/to/file.xls'
                    $publicRelativePath = str_replace('public/', '', $invoice->file_path);
                    if (Storage::disk('public')->exists($publicRelativePath)) {
                        return '<a href="'.Storage::url($publicRelativePath).'" target="_blank" class="btn btn-sm btn-outline-success" title="Скачать счет"><i class="fas fa-file-excel"></i> XLS</a>';
                    }
                }
                return '<span class="text-muted">Нет файла</span>';
            })
            ->addColumn('action', function (CarwashInvoice $invoice) {
                $buttons = '<div class="action-buttons">';
                $buttons .= '<a href="' . route('carwash_invoices.show', $invoice->id) . '" class="btn btn-sm btn-outline-primary" title="Просмотр"><i class="fas fa-eye"></i></a>';

                // Кнопка "Перевыставить счет"
                $buttons .= '<button type="button" class="btn btn-sm btn-outline-info reissue-invoice-btn ms-1" data-invoice-id="' . $invoice->id . '" title="Перевыставить счет"><i class="fas fa-redo"></i></button>';

                // Кнопка "Отправить на email"
                $canSendEmail = false;
                if ($invoice->file_path) {
                    $publicRelativePathForCheck = str_replace('public/', '', $invoice->file_path);
                    if (Storage::disk('public')->exists($publicRelativePathForCheck)) {
                        $canSendEmail = true;
                    }
                }

                if (!$invoice->sent_to_email_at && $invoice->client && $invoice->client->email && $canSendEmail) {
                    $buttons .= '<button type="button" class="btn btn-sm btn-outline-success send-email-btn ms-1" data-invoice-id="' . $invoice->id . '" title="Отправить на email"><i class="fas fa-envelope"></i></button>';
                } elseif (!($invoice->client && $invoice->client->email)) {
                    $buttons .= '<button type="button" class="btn btn-sm btn-outline-secondary ms-1" title="У клиента не указан email для отправки" disabled><i class="fas fa-envelope"></i></button>';
                } elseif (!$canSendEmail) {
                    $buttons .= '<button type="button" class="btn btn-sm btn-outline-secondary ms-1" title="Файл счета отсутствует или недоступен" disabled><i class="fas fa-envelope"></i></button>';
                }

                $buttons .= '<form action="' . route('carwash_invoices.destroy', $invoice->id) . '" method="POST" style="display:inline;" class="delete-form ms-1">
                            ' . csrf_field() . '
                            ' . method_field('DELETE') . '
                            <button type="submit" class="btn btn-sm btn-outline-danger delete-single"
                                    title="Удалить" data-invoice-id="' . $invoice->id . '">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>';
                $buttons .= '</div>';
                return $buttons;
            })
            ->rawColumns(['checkbox', 'action', 'file_link'])
            ->make(true);
    }

    // Метод downloadInvoice больше не нужен, так как файлы доступны по публичной ссылке

    public function reissue(Request $request, CarwashInvoice $invoice): JsonResponse
    {
        $client = $invoice->client;
        if (!$client) {
            return response()->json(['error' => 'Клиент для данного счета не найден.'], 404);
        }

        try {
            $periodStart = Carbon::parse($invoice->period_start);
            $month = $periodStart->month;
            $year = $periodStart->year;

            // Передаем true для sendEmail по умолчанию при перевыставлении
            $success = $this->invoiceService->createAndSendInvoiceForClient($client, $month, $year, true);

            if ($success) {
                return response()->json(['success' => 'Счет #' . $invoice->id . ' успешно перевыставлен и отправлен.']);
            } else {
                return response()->json(['error' => 'Не удалось перевыставить счет. Проверьте логи.'], 500);
            }
        } catch (\Exception $e) {
            Log::error("Error reissuing invoice #{$invoice->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Внутренняя ошибка сервера при перевыставлении счета.'], 500);
        }
    }

    public function sendEmailManually(Request $request, CarwashInvoice $invoice): JsonResponse
    {
        $client = $invoice->client;

        if (!$client) {
            return response()->json(['error' => 'Клиент для данного счета не найден.'], 404);
        }

        if (empty($client->email)) {
            return response()->json(['error' => 'У клиента не указан email адрес.'], 400);
        }

        if (empty($invoice->file_path)) {
            return response()->json(['error' => 'Путь к файлу счета не указан.'], 400);
        }

        $publicRelativePath = str_replace('public/', '', $invoice->file_path);
        if (!Storage::disk('public')->exists($publicRelativePath)) {
            return response()->json(['error' => 'Файл счета не найден на диске public. Возможно, его нужно сначала сформировать/перевыставить.'], 400);
        }

        try {
            $absolutePathToAttach = Storage::disk('public')->path($publicRelativePath);
            Mail::to($client->email)->send(new \App\Mail\CarwashInvoiceMail($client, $invoice, $absolutePathToAttach));

            $invoice->sent_to_email_at = now();
            $invoice->save();

            return response()->json(['success' => 'Счет #' . $invoice->id . ' успешно отправлен на email: ' . $client->email]);
        } catch (\Exception $e) {
            Log::error("Error sending invoice #{$invoice->id} manually: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Ошибка при отправке email.'], 500);
        }
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
    public function show(CarwashInvoice $carwashInvoice): View
    {
        $carwashInvoice->loadMissing('client:id,short_name');

        $carwashInvoice->download_url = null;
        if ($carwashInvoice->file_path) {
            $relativePath = 'invoices/' . basename($carwashInvoice->file_path);
            if (Storage::disk('public')->exists($relativePath)) {
                $carwashInvoice->download_url = Storage::disk('public')->url($relativePath);
            }
        }
        return view('carwash_invoices.show', ['invoice' => $carwashInvoice]);
    }

    /**
     * Remove the specified invoice from storage.
     *
     * @param \App\Models\CarwashInvoice $carwashInvoice
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(CarwashInvoice $carwashInvoice): RedirectResponse
    {
        if ($carwashInvoice->file_path) {
            $publicRelativePath = str_replace('public/', '', $carwashInvoice->file_path);
            if (Storage::disk('public')->exists($publicRelativePath)) {
                Storage::disk('public')->delete($publicRelativePath);
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

        $deletedCount = 0;
        foreach ($request->ids as $id) {
            $invoice = CarwashInvoice::find($id);
            if ($invoice) {
                if ($invoice->file_path) {
                    $publicRelativePath = str_replace('public/', '', $invoice->file_path);
                    if (Storage::disk('public')->exists($publicRelativePath)) {
                        Storage::disk('public')->delete($publicRelativePath);
                    }
                }
                $invoice->delete();
                $deletedCount++;
            }
        }
        return response()->json(['success' => "Выбрано счетов для удаления: {$deletedCount}. Успешно удалено."]);
    }

}
