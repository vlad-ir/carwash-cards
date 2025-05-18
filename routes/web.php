<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CarwashClientController;
use App\Http\Controllers\CarwashBonusCardController;
use App\Http\Controllers\CarwashDashboardController;
use App\Http\Controllers\CarwashBonusCardStatController;
use App\Http\Controllers\CarwashInvoiceController;


// Дашборд
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('carwash_dashboard');
    }
    return view('auth.login');
});

Route::middleware(['auth'])->group(function () {
    // Дашборд
    Route::get('/carwash_dashboard', [CarwashDashboardController::class, 'index'])->name('carwash_dashboard');

    // Клиенты
    Route::post('/carwash_clients/deleteSelected', [CarwashClientController::class, 'deleteSelected'])->name('carwash_clients.deleteSelected');
    Route::get('/carwash_clients/data', [CarwashClientController::class, 'getClientData'])->name('carwash_clients.data');
    Route::resource('carwash_clients', CarwashClientController::class);

    // Бонусные карты
    Route::post('/carwash_bonus_cards/delete-selected', [CarwashBonusCardController::class, 'deleteSelected'])->name('carwash_bonus_cards.deleteSelected');
    Route::get('/carwash_bonus_cards/data', [CarwashBonusCardController::class, 'getBonusCardData'])->name('carwash_bonus_cards.data');
    Route::resource('carwash_bonus_cards', CarwashBonusCardController::class);

    // Статистика бонусных карт
    Route::post('/carwash_bonus_card_stats/delete-selected', [CarwashBonusCardStatController::class, 'deleteSelected'])->name('carwash_bonus_card_stats.deleteSelected');
    Route::get('/carwash_bonus_card_stats/data', [CarwashBonusCardStatController::class, 'getStatData'])->name('carwash_bonus_card_stats.data');
    Route::get('/carwash_bonus_card_stats/upload', [CarwashBonusCardStatController::class, 'showUploadForm'])->name('carwash_bonus_card_stats.upload.form');
    Route::post('/carwash_bonus_card_stats/upload', [CarwashBonusCardStatController::class, 'upload'])->name('carwash_bonus_card_stats.upload');
    Route::resource('carwash_bonus_card_stats', CarwashBonusCardStatController::class);

    // Счета
    Route::post('/carwash_invoices/deleteSelected', [CarwashInvoiceController::class, 'deleteSelected'])->name('carwash_invoices.deleteSelected');
    Route::get('/carwash_invoices/data', [CarwashInvoiceController::class, 'getInvoiceData'])->name('carwash_invoices.data');
    Route::resource('carwash_invoices', CarwashInvoiceController::class)->except(['edit', 'update']);
});

Auth::routes(['register' => false, 'reset' => false]);
