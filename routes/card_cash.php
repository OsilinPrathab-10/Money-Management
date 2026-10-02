<?php

use App\Http\Controllers\CardCash\CardCashBillPaymentController;
use App\Http\Controllers\CardCash\CardCashDashboardController;
use App\Http\Controllers\CardCash\CardCashLeadController;
use App\Http\Controllers\CardCash\CardCashProcessingController;
use App\Http\Controllers\CardCash\CardCashReportController;
use App\Http\Controllers\CardCash\CardCashReturnController;
use App\Http\Controllers\CardCash\CardCashSettingsController;
use App\Http\Controllers\CardCash\CardCashSwipeController;
use App\Http\Controllers\CardCash\CreditCardCustomerController;
use App\Http\Controllers\CardCash\CreditCardWalletController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'adminOrStaff'])->prefix('card-to-cash')->name('card-cash.')->group(function () {

    // 1. Dashboard
    Route::get('/', [CardCashDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [CardCashDashboardController::class, 'index']);

    // 2. Leads
    Route::prefix('leads')->name('leads.')->group(function () {
        Route::get('/', [CardCashLeadController::class, 'index'])->name('index');
        Route::get('/create', [CardCashLeadController::class, 'create'])->name('create');
        Route::post('/', [CardCashLeadController::class, 'store'])->name('store');
        Route::get('/customer-cards/{customerId}', [CardCashLeadController::class, 'customerCards'])->name('customer-cards');
        Route::get('/{id}', [CardCashLeadController::class, 'show'])->name('show');
        Route::put('/{id}', [CardCashLeadController::class, 'update'])->name('update');
        Route::patch('/{id}', [CardCashLeadController::class, 'update']);
        Route::post('/{id}/assign', [CardCashLeadController::class, 'assign'])->name('assign');
        Route::delete('/{id}', [CardCashLeadController::class, 'destroy'])->name('destroy');
    });

    // 3. Processing Queue & Action Hub
    Route::prefix('processing')->name('processing.')->group(function () {
        Route::get('/', [CardCashProcessingController::class, 'index'])->name('index');
        Route::get('/{id}', [CardCashProcessingController::class, 'process'])->name('process');
        Route::post('/{id}/status', [CardCashProcessingController::class, 'changeStatus'])->name('status');
    });

    // 4. Bill Payments
    Route::prefix('bill-payments')->name('bill-payments.')->group(function () {
        Route::get('/', [CardCashBillPaymentController::class, 'index'])->name('index');
        Route::post('/{leadId}/process', [CardCashBillPaymentController::class, 'processPayment'])->name('process');
        Route::post('/{id}/proof', [CardCashBillPaymentController::class, 'uploadProof'])->name('proof');
        Route::get('/{leadId}/whatsapp', [CardCashBillPaymentController::class, 'sendWhatsApp'])->name('whatsapp');
    });

    // 5. Swipe Transactions
    Route::prefix('swipes')->name('swipes.')->group(function () {
        Route::get('/', [CardCashSwipeController::class, 'index'])->name('index');
        Route::post('/{leadId}/process', [CardCashSwipeController::class, 'processSwipe'])->name('process');
        Route::post('/{id}/proof', [CardCashSwipeController::class, 'uploadProof'])->name('proof');
    });

    // 6. Returns / Settlements
    Route::prefix('returns')->name('returns.')->group(function () {
        Route::get('/', [CardCashReturnController::class, 'index'])->name('index');
        Route::get('/preview-calc', [CardCashReturnController::class, 'calculatePreview'])->name('preview');
        Route::post('/{leadId}/process', [CardCashReturnController::class, 'processReturn'])->name('process');
    });

    // 7. Credit Card Customers
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CreditCardCustomerController::class, 'index'])->name('index');
        Route::get('/create', [CreditCardCustomerController::class, 'create'])->name('create');
        Route::post('/', [CreditCardCustomerController::class, 'store'])->name('store');
        Route::get('/search-ajax', [CreditCardCustomerController::class, 'searchAjax'])->name('search-ajax');
        Route::get('/{id}', [CreditCardCustomerController::class, 'show'])->name('show');
        Route::post('/{id}/cards', [CreditCardCustomerController::class, 'storeCard'])->name('cards.store');
        Route::get('/{id}/edit', [CreditCardCustomerController::class, 'edit'])->name('edit');
        Route::put('/{id}', [CreditCardCustomerController::class, 'update'])->name('update');
        Route::delete('/{id}', [CreditCardCustomerController::class, 'destroy'])->name('destroy');
    });

    // 8. Wallets
    Route::prefix('wallets')->name('wallets.')->group(function () {
        Route::get('/', [CreditCardWalletController::class, 'index'])->name('index');
        Route::post('/', [CreditCardWalletController::class, 'store'])->name('store');
        Route::put('/{id}', [CreditCardWalletController::class, 'update'])->name('update');
        Route::get('/{id}', [CreditCardWalletController::class, 'show'])->name('show');
        Route::post('/{id}/add-balance', [CreditCardWalletController::class, 'addBalance'])->name('add-balance');
        Route::post('/{id}/adjust-balance', [CreditCardWalletController::class, 'adjustBalance'])->name('adjust-balance');
    });

    // 9. Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [CardCashSettingsController::class, 'index'])->name('index');
        Route::put('/', [CardCashSettingsController::class, 'update'])->name('update');
        // Companies Master CRUD
        Route::post('/companies', [CardCashSettingsController::class, 'storeCompany'])->name('companies.store');
        Route::put('/companies/{id}', [CardCashSettingsController::class, 'updateCompany'])->name('companies.update');
        Route::delete('/companies/{id}', [CardCashSettingsController::class, 'destroyCompany'])->name('companies.destroy');

        // Bank Names Master CRUD
        Route::post('/banks', [CardCashSettingsController::class, 'storeBank'])->name('banks.store');
        Route::put('/banks/{id}', [CardCashSettingsController::class, 'updateBank'])->name('banks.update');
        Route::delete('/banks/{id}', [CardCashSettingsController::class, 'destroyBank'])->name('banks.destroy');

        Route::post('/payment-sources', [CardCashSettingsController::class, 'storePaymentSource'])->name('payment-sources.store');
        Route::put('/payment-sources/{id}', [CardCashSettingsController::class, 'updatePaymentSource'])->name('payment-sources.update');
        Route::delete('/payment-sources/{id}', [CardCashSettingsController::class, 'destroyPaymentSource'])->name('payment-sources.destroy');
        Route::post('/withdrawal-gateways', [CardCashSettingsController::class, 'storeWithdrawalGateway'])->name('withdrawal-gateways.store');
        Route::put('/withdrawal-gateways/{id}', [CardCashSettingsController::class, 'updateWithdrawalGateway'])->name('withdrawal-gateways.update');
        Route::delete('/withdrawal-gateways/{id}', [CardCashSettingsController::class, 'destroyWithdrawalGateway'])->name('withdrawal-gateways.destroy');
    });

    // 10. Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [CardCashReportController::class, 'index'])->name('index');
        Route::get('/export-excel', [CardCashReportController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [CardCashReportController::class, 'exportCsv'])->name('export-csv');
    });
});
