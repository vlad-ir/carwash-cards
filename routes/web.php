<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CarwashClientController;
use App\Http\Controllers\CarwashBonusCardController;
use App\Http\Controllers\CarwashDashboardController;
use App\Http\Controllers\CarwashBonusCardStatController;
use App\Http\Controllers\CarwashInvoiceController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\CheckRole;

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

    // Маршруты для администраторов
    Route::middleware(['role:admin'])->group(function () {
        // Управление пользователями
        Route::post('/users/deleteSelected', [UserController::class, 'deleteSelected'])->name('users.deleteSelected');
        Route::get('/users/get_all_ids', [UserController::class, 'getAllUserIds'])->name('users.get_all_ids');
        Route::resource('users', UserController::class)->except(['create', 'edit']);

        // Клиенты
        Route::post('/carwash_clients/deleteSelected', [CarwashClientController::class, 'deleteSelected'])->name('carwash_clients.deleteSelected');
        Route::post('/carwash_clients/create_invoices_for_selected', [CarwashClientController::class, 'createInvoicesForSelected'])->name('carwash_clients.create_invoices_for_selected');
        Route::get('/carwash_clients/data', [CarwashClientController::class, 'getClientData'])->name('carwash_clients.data');
        Route::get('/carwash_clients/get_all_ids', [CarwashClientController::class, 'getAllClientIds'])->name('carwash_clients.get_all_ids');
        Route::get('/carwash-clients/{clientId}/bonus-cards-data', [CarwashClientController::class, 'getClientBonusCardsData'])->name('carwash_clients.bonus_cards_data');
        Route::resource('carwash_clients', CarwashClientController::class);

        // Бонусные карты
        Route::post('/carwash_bonus_cards/delete-selected', [CarwashBonusCardController::class, 'deleteSelected'])->name('carwash_bonus_cards.deleteSelected');
        Route::get('/carwash_bonus_cards/data', [CarwashBonusCardController::class, 'getBonusCardData'])->name('carwash_bonus_cards.data');
        Route::get('/carwash_bonus_cards/get_all_ids', [CarwashBonusCardController::class, 'getAllBonusCardIds'])->name('carwash_bonus_cards.get_all_ids');
        Route::resource('carwash_bonus_cards', CarwashBonusCardController::class)->except(['create', 'edit']);

        // Статистика бонусных карт
        Route::post('/carwash_bonus_card_stats/delete-selected', [CarwashBonusCardStatController::class, 'deleteSelected'])->name('carwash_bonus_card_stats.deleteSelected');
        Route::get('/carwash_bonus_card_stats/data', [CarwashBonusCardStatController::class, 'getStatData'])->name('carwash_bonus_card_stats.data');
        Route::get('/carwash_bonus_card_stats/get_all_ids', [CarwashBonusCardStatController::class, 'getAllStatIds'])->name('carwash_bonus_card_stats.get_all_ids');
        Route::get('/carwash_bonus_card_stats/upload', [CarwashBonusCardStatController::class, 'showUploadForm'])->name('carwash_bonus_card_stats.upload.form');
        Route::post('/carwash_bonus_card_stats/upload', [CarwashBonusCardStatController::class, 'upload'])->name('carwash_bonus_card_stats.upload');
        Route::resource('carwash_bonus_card_stats', CarwashBonusCardStatController::class);

        // Счета
        Route::post('/carwash_invoices/deleteSelected', [CarwashInvoiceController::class, 'deleteSelected'])->name('carwash_invoices.deleteSelected');
        Route::get('/carwash_invoices/data', [CarwashInvoiceController::class, 'getInvoicesData'])->name('carwash_invoices.data');
        Route::get('/carwash_invoices/get_all_ids', [CarwashInvoiceController::class, 'getAllInvoiceIds'])->name('carwash_invoices.get_all_ids');
        Route::post('/carwash_invoices/{invoice}/reissue', [CarwashInvoiceController::class, 'reissue'])->name('carwash_invoices.reissue');
        Route::post('/carwash_invoices/{invoice}/send_email_manually', [CarwashInvoiceController::class, 'sendEmailManually'])->name('carwash_invoices.send_email_manually');
        Route::resource('carwash_invoices', CarwashInvoiceController::class)->except(['edit', 'update']);
    });

    // Маршруты для бухгалтера
    Route::middleware(['role:accountant'])->group(function () {
        // Здесь можно добавить маршруты, доступные только бухгалтеру
    });

    // Маршруты для клиента автомойки
    Route::middleware(['role:client'])->group(function () {
        // Здесь можно добавить маршруты, доступные только клиенту автомойки
    });

    // Маршруты для менеджеров
    Route::middleware(['role:manager'])->group(function () {
        // Здесь можно добавить маршруты, доступные только менеджерам
    });

});

Auth::routes(['register' => false, 'reset' => false]);
