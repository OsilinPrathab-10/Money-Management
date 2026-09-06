<?php

use App\Http\Controllers\Account\AccountTypeController;
use App\Http\Controllers\Account\BankAccountController;
use App\Http\Controllers\Account\BankTransactionController;
use App\Http\Controllers\Account\BankTransferController;
use App\Http\Controllers\Account\ChartOfAccountController;
use App\Http\Controllers\Account\CreditNoteController;
use App\Http\Controllers\Account\CustomerController;
use App\Http\Controllers\Account\CustomerPaymentController;
use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\LoanPortfolioController;
use App\Http\Controllers\Account\DebitNoteController;
use App\Http\Controllers\Account\ExpenseCategoriesController;
use App\Http\Controllers\Account\ExpenseController;
use App\Http\Controllers\Account\ReportsController;
use App\Http\Controllers\Account\DayBookController;
use App\Http\Controllers\Account\LedgerController;
use App\Http\Controllers\Account\ProfitLossController;
use App\Http\Controllers\Account\RevenueCategoriesController;
use App\Http\Controllers\Account\RevenueController;
use App\Http\Controllers\Account\VendorController;
use App\Http\Controllers\Account\VendorPaymentController;
use Illuminate\Support\Facades\Route;

/*
| Integrated from ERPSoftware (WorkDo Account package) — Blade UI, Admin-only.
*/
Route::middleware(['web', 'auth', 'admin'])->prefix('account')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('account.index');

    Route::get('loan-accounts', [LoanPortfolioController::class, 'loanAccounts'])->name('account.loan-accounts.index');
    Route::get('loan-accounts/export', [LoanPortfolioController::class, 'loanAccountsExport'])->name('account.loan-accounts.export');
    Route::get('emis', [LoanPortfolioController::class, 'emis'])->name('account.emis.index');
    Route::get('emis/export', [LoanPortfolioController::class, 'emisExport'])->name('account.emis.export');

    Route::resource('customers', CustomerController::class, ['as' => 'account']);
    Route::get('customers/export', [CustomerController::class, 'export'])->name('account.customers.export');

    Route::prefix('bank-accounts')->name('account.bank-accounts.')->group(function () {
        Route::get('/', [BankAccountController::class, 'index'])->name('index');
        Route::get('/export', [BankAccountController::class, 'export'])->name('export');
        Route::post('/', [BankAccountController::class, 'store'])->name('store');
        Route::get('/{bankaccount}/edit', [BankAccountController::class, 'edit'])->name('edit');
        Route::put('/{bankaccount}', [BankAccountController::class, 'update'])->name('update');
        Route::delete('/{bankaccount}', [BankAccountController::class, 'destroy'])->name('destroy');
        Route::get('/api/list', [BankAccountController::class, 'bankAccounts'])->name('api.list');
    });

    Route::prefix('account-types')->name('account.account-types.')->group(function () {
        Route::get('/', [AccountTypeController::class, 'index'])->name('index');
        Route::get('/export', [AccountTypeController::class, 'export'])->name('export');
        Route::post('/', [AccountTypeController::class, 'store'])->name('store');
        Route::put('/{accounttype}', [AccountTypeController::class, 'update'])->name('update');
        Route::delete('/{accounttype}', [AccountTypeController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('chart-of-accounts')->name('account.chart-of-accounts.')->group(function () {
        Route::get('/', [ChartOfAccountController::class, 'index'])->name('index');
        Route::get('/export', [ChartOfAccountController::class, 'export'])->name('export');
        Route::post('/', [ChartOfAccountController::class, 'store'])->name('store');
        Route::get('/{chartofaccount}', [ChartOfAccountController::class, 'show'])->name('show');
        Route::get('/{chartofaccount}/edit', [ChartOfAccountController::class, 'edit'])->name('edit');
        Route::put('/{chartofaccount}', [ChartOfAccountController::class, 'update'])->name('update');
        Route::delete('/{chartofaccount}', [ChartOfAccountController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('bank-transactions')->name('account.bank-transactions.')->group(function () {
        Route::get('/', [BankTransactionController::class, 'index'])->name('index');
        Route::get('/export', [BankTransactionController::class, 'export'])->name('export');
        Route::post('/{id}/mark-reconciled', [BankTransactionController::class, 'markReconciled'])->name('mark-reconciled');
    });

    Route::prefix('bank-transfers')->name('account.bank-transfers.')->group(function () {
        Route::get('/', [BankTransferController::class, 'index'])->name('index');
        Route::get('/export', [BankTransferController::class, 'export'])->name('export');
        Route::post('/', [BankTransferController::class, 'store'])->name('store');
        Route::put('/{banktransfer}', [BankTransferController::class, 'update'])->name('update');
        Route::delete('/{banktransfer}', [BankTransferController::class, 'destroy'])->name('destroy');
        Route::post('/{banktransfer}/process', [BankTransferController::class, 'process'])->name('process');
    });

    Route::prefix('debit-notes')->name('account.debit-notes.')->group(function () {
        Route::get('/', [DebitNoteController::class, 'index'])->name('index');
        Route::get('/export', [DebitNoteController::class, 'export'])->name('export');
        Route::post('/{debitNote}/approve', [DebitNoteController::class, 'approve'])->name('approve');
        Route::delete('/{debitNote}', [DebitNoteController::class, 'destroy'])->name('destroy');
        Route::get('/{debitNote}', [DebitNoteController::class, 'show'])->name('show');
    });

    Route::prefix('credit-notes')->name('account.credit-notes.')->group(function () {
        Route::get('/', [CreditNoteController::class, 'index'])->name('index');
        Route::get('/export', [CreditNoteController::class, 'export'])->name('export');
        Route::post('/{creditNote}/approve', [CreditNoteController::class, 'approve'])->name('approve');
        Route::delete('/{creditNote}', [CreditNoteController::class, 'destroy'])->name('destroy');
        Route::get('/{creditNote}', [CreditNoteController::class, 'show'])->name('show');
    });

    Route::prefix('customer-payments')->name('account.customer-payments.')->group(function () {
        Route::get('/', [CustomerPaymentController::class, 'index'])->name('index');
        Route::get('/export', [CustomerPaymentController::class, 'export'])->name('export');
        Route::post('/', [CustomerPaymentController::class, 'store'])->name('store');
        Route::delete('/{customerPayment}', [CustomerPaymentController::class, 'destroy'])->name('destroy');
        Route::get('/customers/{customerId}/outstanding', [CustomerPaymentController::class, 'getOutstandingInvoices'])->name('outstanding-invoices');
        Route::patch('/{customerPayment}/update-status', [CustomerPaymentController::class, 'updateStatus'])->name('update-status');
    });

    Route::prefix('revenue-categories')->name('account.revenue-categories.')->group(function () {
        Route::get('/', [RevenueCategoriesController::class, 'index'])->name('index');
        Route::get('/export', [RevenueCategoriesController::class, 'export'])->name('export');
        Route::post('/', [RevenueCategoriesController::class, 'store'])->name('store');
        Route::get('/{revenuecategories}/edit', [RevenueCategoriesController::class, 'edit'])->name('edit');
        Route::put('/{revenuecategories}', [RevenueCategoriesController::class, 'update'])->name('update');
        Route::delete('/{revenuecategories}', [RevenueCategoriesController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('expense-categories')->name('account.expense-categories.')->group(function () {
        Route::get('/', [ExpenseCategoriesController::class, 'index'])->name('index');
        Route::get('/export', [ExpenseCategoriesController::class, 'export'])->name('export');
        Route::post('/', [ExpenseCategoriesController::class, 'store'])->name('store');
        Route::get('/{expensecategories}/edit', [ExpenseCategoriesController::class, 'edit'])->name('edit');
        Route::put('/{expensecategories}', [ExpenseCategoriesController::class, 'update'])->name('update');
        Route::delete('/{expensecategories}', [ExpenseCategoriesController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('revenues')->name('account.revenues.')->group(function () {
        Route::get('/', [RevenueController::class, 'index'])->name('index');
        Route::get('/export', [RevenueController::class, 'export'])->name('export');
        Route::post('/', [RevenueController::class, 'store'])->name('store');
        Route::get('/{revenue}', [RevenueController::class, 'show'])->name('show');
        Route::put('/{revenue}', [RevenueController::class, 'update'])->name('update');
        Route::delete('/{revenue}', [RevenueController::class, 'destroy'])->name('destroy');
        Route::post('/{revenue}/approve', [RevenueController::class, 'approve'])->name('approve');
        Route::post('/{revenue}/post', [RevenueController::class, 'post'])->name('post');
    });

    Route::prefix('expenses')->name('account.expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->name('index');
        Route::get('/export', [ExpenseController::class, 'export'])->name('export');
        Route::post('/', [ExpenseController::class, 'store'])->name('store');
        Route::get('/{expense}', [ExpenseController::class, 'show'])->name('show');
        Route::put('/{expense}', [ExpenseController::class, 'update'])->name('update');
        Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->name('destroy');
        Route::post('/{expense}/approve', [ExpenseController::class, 'approve'])->name('approve');
        Route::post('/{expense}/post', [ExpenseController::class, 'post'])->name('post');
    });

    Route::prefix('reports')->name('account.reports.')->group(function () {
        Route::get('/', [ReportsController::class, 'index'])->name('index');
        Route::get('/invoice-aging', [ReportsController::class, 'invoiceAging'])->name('invoice-aging');
        Route::get('/invoice-aging/print', [ReportsController::class, 'printInvoiceAging'])->name('invoice-aging.print');
        Route::get('/bill-aging', [ReportsController::class, 'billAging'])->name('bill-aging');
        Route::get('/bill-aging/print', [ReportsController::class, 'printBillAging'])->name('bill-aging.print');
        Route::get('/tax-summary', [ReportsController::class, 'taxSummary'])->name('tax-summary');
        Route::get('/tax-summary/print', [ReportsController::class, 'printTaxSummary'])->name('tax-summary.print');
        Route::get('/customer-balance', [ReportsController::class, 'customerBalance'])->name('customer-balance');
        Route::get('/customer-balance/print', [ReportsController::class, 'printCustomerBalance'])->name('customer-balance.print');
        Route::get('/vendor-balance', [ReportsController::class, 'vendorBalance'])->name('vendor-balance');
        Route::get('/vendor-balance/print', [ReportsController::class, 'printVendorBalance'])->name('vendor-balance.print');
        Route::get('/invoice-aging/export', [ReportsController::class, 'exportInvoiceAging'])->name('invoice-aging.export');
        Route::get('/bill-aging/export', [ReportsController::class, 'exportBillAging'])->name('bill-aging.export');
        Route::get('/tax-summary/export', [ReportsController::class, 'exportTaxSummary'])->name('tax-summary.export');
        Route::get('/customer-balance/export', [ReportsController::class, 'exportCustomerBalance'])->name('customer-balance.export');
        Route::get('/vendor-balance/export', [ReportsController::class, 'exportVendorBalance'])->name('vendor-balance.export');
        Route::get('/customer/{customer}', [ReportsController::class, 'customerDetail'])->name('customer-detail');
        Route::get('/customer/{customer}/print', [ReportsController::class, 'printCustomerDetail'])->name('customer-detail.print');
        Route::get('/vendor/{vendor}', [ReportsController::class, 'vendorDetail'])->name('vendor-detail');
        Route::get('/vendor/{vendor}/print', [ReportsController::class, 'printVendorDetail'])->name('vendor-detail.print');
        
        // 15 New Report Placeholders
        Route::get('/trial-balance', [ReportsController::class, 'trialBalance'])->name('trial-balance');
        Route::get('/balance-sheet', [ReportsController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/cash-flow', [ReportsController::class, 'cashFlow'])->name('cash-flow');
        Route::get('/profit-loss', [ReportsController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/account-summary', [ReportsController::class, 'accountSummary'])->name('account-summary');
        
        Route::get('/general-ledger', [ReportsController::class, 'generalLedger'])->name('general-ledger');
        Route::get('/day-book', [ReportsController::class, 'dayBook'])->name('day-book');
        Route::get('/bank-book', [ReportsController::class, 'bankBook'])->name('bank-book');
        Route::get('/cash-book', [ReportsController::class, 'cashBook'])->name('cash-book');
        
        Route::get('/revenue-report', [ReportsController::class, 'revenueReport'])->name('revenue-report');
        Route::get('/expense-report', [ReportsController::class, 'expenseReport'])->name('expense-report');
        Route::get('/revenue-category', [ReportsController::class, 'revenueCategory'])->name('revenue-category');
        Route::get('/expense-category', [ReportsController::class, 'expenseCategory'])->name('expense-category');
        
        Route::get('/outstanding-loans', [ReportsController::class, 'outstandingLoans'])->name('outstanding-loans');
        Route::get('/loan-disbursement', [ReportsController::class, 'loanDisbursement'])->name('loan-disbursement');
    
        });

    // ERP-style accounting additions: Day Book, Ledger, Profit & Loss
    Route::prefix('day-book')->name('account.day-book.')->group(function () {
        Route::get('/', [DayBookController::class, 'index'])->name('index');
        Route::get('/export', [DayBookController::class, 'export'])->name('export');
    });

    Route::prefix('ledger')->name('account.ledger.')->group(function () {
        Route::get('/', [LedgerController::class, 'index'])->name('index');
        Route::get('/export', [LedgerController::class, 'export'])->name('export');
    });

    Route::prefix('profit-loss')->name('account.profit-loss.')->group(function () {
        Route::get('/', [ProfitLossController::class, 'index'])->name('index');
        Route::get('/export', [ProfitLossController::class, 'export'])->name('export');
    });
});
