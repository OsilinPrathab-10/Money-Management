<?php

/**
 * Agent mobile app API routes.
 * Base path: /api/agent/...
 *
 * Controllers: App\Http\Controllers\Agent\*
 * Auth: same email/password as web Agent login (Sanctum agent guard).
 */

use Illuminate\Support\Facades\Route;

Route::prefix('agent')->name('agent.')->group(function () {

    // Public: app settings for agent mobile app (no auth)
    Route::get('/app-settings', [App\Http\Controllers\Agent\AppSettingControllerApi::class, 'index'])->name('app-settings');
    Route::get('/help-support', [App\Http\Controllers\Agent\AppSettingControllerApi::class, 'helpSupport'])->name('help-support');
    Route::get('/privacy_policy', [App\Http\Controllers\Agent\AppSettingControllerApi::class, 'privacyPolicy'])->name('privacy-policy');
    Route::get('/terms_conditions', [App\Http\Controllers\Agent\AppSettingControllerApi::class, 'termsConditions'])->name('terms-conditions');
    // Route::get('/app/settings', [App\Http\Controllers\Agent\AppSettingControllerApi::class, 'index']); // alias
    // Route::get('/app-settings/policies/{type?}', [App\Http\Controllers\Agent\AppSettingControllerApi::class, 'policies'])->name('app-settings.policies');
    // Route::get('/app/policies/{type?}', [App\Http\Controllers\Agent\AppSettingControllerApi::class, 'policies']); // alias

    // Auth — same web login credentials (email + password)
    Route::controller(App\Http\Controllers\Agent\AuthControllerApi::class)->group(function () {
        Route::post('/login', 'login')->name('login');
        Route::post('/password/forgot', 'sendForgetPasswordOtp');
        Route::post('/forget-password/verify-otp', 'verifyForgetPasswordOtp');
        Route::post('/forget-password/change-password', 'changePasswordAfterOtp');
    });

    // Authenticated agent routes
    Route::middleware(['auth:agent', 'agent'])->group(function () {
        Route::controller(App\Http\Controllers\Agent\AuthControllerApi::class)->group(function () {
            Route::post('/logout', 'logout')->name('logout');
            Route::post('/refresh', 'refresh')->name('refresh');
            Route::get('/me', 'me')->name('me');
            Route::post('/location/update', 'updateLocation');
            Route::post('/fcm-token', 'registerFcmToken');
            Route::post('/fcm-token/update', 'registerFcmToken');
        });

        Route::get('/dashboard', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'index']);
        Route::get('/profile', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'profile']);
        Route::get('/profile/bank-info', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'bankInfo']);

        // Client Management
        Route::get('/clients', [App\Http\Controllers\Agent\ClientManagementControllerApi::class, 'index']);
        Route::post('/clients', [App\Http\Controllers\Agent\ClientManagementControllerApi::class, 'store']);
        Route::get('/clients/{id}', [App\Http\Controllers\Agent\ClientManagementControllerApi::class, 'show']);
        Route::post('/clients/{id}', [App\Http\Controllers\Agent\ClientManagementControllerApi::class, 'update']);
        Route::delete('/clients/{id}', [App\Http\Controllers\Agent\ClientManagementControllerApi::class, 'destroy']);
        Route::get('/zones', [App\Http\Controllers\Agent\ClientManagementControllerApi::class, 'zones']);

        // Loan Applications
        Route::get('/loan-applications/metadata', [App\Http\Controllers\Agent\LoanManagementControllerApi::class, 'metadata']);
        Route::get('/loan-applications', [App\Http\Controllers\Agent\LoanManagementControllerApi::class, 'index']);
        Route::post('/loan-applications/quick-apply', [App\Http\Controllers\Agent\LoanManagementControllerApi::class, 'storeQuickApplication']);
        Route::post('/loan-applications/check-eligibility', [App\Http\Controllers\Agent\LoanManagementControllerApi::class, 'checkLoanEligibility']);
        Route::post('/loan-applications/preview-emi', [App\Http\Controllers\Agent\LoanManagementControllerApi::class, 'previewEmi']);
        Route::get('/loan-applications/{application}', [App\Http\Controllers\Agent\LoanManagementControllerApi::class, 'show']);

        // Loan Accounts
        Route::get('/loan-accounts', [App\Http\Controllers\Agent\LoanManagementControllerApi::class, 'loanAccounts']);
        Route::get('/loan-account/{id?}', [App\Http\Controllers\Agent\LoanManagementControllerApi::class, 'viewLoanAccount']);
        Route::get('/loan-account', [App\Http\Controllers\Agent\LoanManagementControllerApi::class, 'viewLoanAccount']);
        Route::post('/loan-account/{id}/regenerate-documents', [App\Http\Controllers\Agent\LoanManagementControllerApi::class, 'regenerateDocuments']);

        // Chit Applications
        Route::get('/chit-applications/metadata', [App\Http\Controllers\Agent\ChitManagementControllerApi::class, 'metadata']);
        Route::get('/chit-applications', [App\Http\Controllers\Agent\ChitManagementControllerApi::class, 'index']);
        Route::post('/chit-applications/apply', [App\Http\Controllers\Agent\ChitManagementControllerApi::class, 'apply']);
        Route::get('/chit-applications/{member}', [App\Http\Controllers\Agent\ChitManagementControllerApi::class, 'show']);

        // Chit Accounts
        Route::get('/chit-accounts', [App\Http\Controllers\Agent\ChitManagementControllerApi::class, 'chitAccounts']);
        Route::get('/chit-account/{id?}', [App\Http\Controllers\Agent\ChitManagementControllerApi::class, 'viewChitAccount']);
        Route::get('/chit-account', [App\Http\Controllers\Agent\ChitManagementControllerApi::class, 'viewChitAccount']);

        // Fixed Deposits
        Route::get('/fd-schemes/metadata', [App\Http\Controllers\Agent\FDManagementControllerApi::class, 'schemes']);
        Route::post('/fd-applications/apply', [App\Http\Controllers\Agent\FDManagementControllerApi::class, 'apply']);
        Route::get('/fd-applications', [App\Http\Controllers\Agent\FDManagementControllerApi::class, 'applications']);
        Route::get('/fd-applications/{id}', [App\Http\Controllers\Agent\FDManagementControllerApi::class, 'showApplication']);
        Route::get('/fixed-deposits', [App\Http\Controllers\Agent\FDManagementControllerApi::class, 'fixedDeposits']);
        Route::get('/fixed-deposits/{id}', [App\Http\Controllers\Agent\FDManagementControllerApi::class, 'showFixedDeposit']);

        // Agent Collections (Unified Loan & Chit)
        Route::get('/agent-collections/search-dues', [App\Http\Controllers\Agent\AgentCollectionControllerApi::class, 'assignedDues']);
        Route::get('/agent-collections/overdue', [App\Http\Controllers\Agent\AgentCollectionControllerApi::class, 'overdueClients']);
        Route::get('/overdue-clients', [App\Http\Controllers\Agent\AgentCollectionControllerApi::class, 'overdueClients']);
        Route::get('/agent-collections', [App\Http\Controllers\Agent\AgentCollectionControllerApi::class, 'allCollections']);
        Route::get('/agent-collections/pending-follow-ups', [App\Http\Controllers\Agent\AgentCollectionControllerApi::class, 'pendingFollowUps']);
        Route::get('/agent-collections/bank-accounts', [App\Http\Controllers\Agent\AgentCollectionControllerApi::class, 'collectionBankAccounts']);
        Route::post('/agent-collections/store', [App\Http\Controllers\Agent\AgentCollectionControllerApi::class, 'store']);
        Route::post('/agent-collections/bulk-store', [App\Http\Controllers\Agent\AgentCollectionControllerApi::class, 'bulkStore']);
        Route::get('/agent-collections/list', [App\Http\Controllers\Agent\AgentCollectionControllerApi::class, 'list']);
        Route::get('/agent-collections/chit-list', [App\Http\Controllers\Agent\AgentCollectionControllerApi::class, 'chitList']);
        // Unified Receipt Endpoint (supports single/bulk loans and chits, and legacy loan/chit receipt routes)
        Route::get('/agent-collections/receipt/{id?}', [App\Http\Controllers\Agent\AgentCollectionControllerApi::class, 'unifiedReceipt']);

        Route::get('/notifications', [App\Http\Controllers\Agent\EmiPaymentControllerApi::class, 'notifications']);
        Route::post('/notifications/mark-read', [App\Http\Controllers\Agent\EmiPaymentControllerApi::class, 'markAsRead']);
        Route::post('/notifications/mark-all-read', [App\Http\Controllers\Agent\EmiPaymentControllerApi::class, 'markAllAsRead']);
        Route::post('/notifications/clear-all', [App\Http\Controllers\Agent\EmiPaymentControllerApi::class, 'clearAllNotifications']);

        Route::post('/profile-update', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'updateProfile']);

        Route::post('/check-in', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'checkIn']);
        Route::post('/check-out', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'checkOut']);
        // Backward-compatible aliases
        Route::post('/agent/check-in', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'checkIn']);
        Route::post('/agent/check-out', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'checkOut']);

        Route::get('/daily-summary', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'checkoutSummary']);
        Route::get('/daily-logs', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'dailyLogs']);
        Route::get('/daily-logs/{id}', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'showDailyLog']);

        Route::get('/today-cases', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'todayCases']);
        Route::get('/cases', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'index']);
        Route::post('/cases/update-call-status', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'updateCallStatus']);
        Route::get('/cases/risk', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'riskCases']);
        Route::get('/cases/recovered', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'recoveredCases']);
        Route::get('/cases/collections', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'agentCollections']);
        Route::get('/cases/pending', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'pendingCollections']);
        Route::get('/cases/collected-today', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'todayCollectedList']);
        Route::get('/cases/recovered/{emiId}', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'recoveredCaseDetail']);
        // Legacy nested paths
        Route::get('/agent/cases/risk', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'riskCases']);
        Route::get('/agent/cases/recovered', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'recoveredCases']);
        Route::get('/agent/cases/collections', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'agentCollections']);
        Route::get('/agent/cases/pending', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'pendingCollections']);
        Route::get('/agent/cases/collected-today', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'todayCollectedList']);
        Route::get('/agent/cases/recovered/{emiId}', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'recoveredCaseDetail']);

        Route::get('/outstanding-overview', [App\Http\Controllers\Agent\AgentOverviewControllerApi::class, 'outstandingOverview']);

        Route::get('/loan-accounts/{loanAccountId}/collect', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'show']);
        Route::get('/emis/{emiId}/collect', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'showEmi']);
        Route::get('/emi/{emi}/collections', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'index']);
        Route::post('/collections', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'store']);
        Route::post('/collections/resend-otp', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'resendOtp']);
        Route::get('/collections/details', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'getCollectionDetails']);
        Route::post('/collections/action', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'collectionAction']);
        Route::get('/collections/dashboard', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'collectionDashboard']);
        Route::get('/collections/today', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'todayCollections']);
        Route::get('/collections/in-progress', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'inProgressCollections']);
        Route::get('/collections/upcoming', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'upcomingCollections']);
        Route::get('/loan-accounts/{loanAccountId}/customer-details', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'customerDetails']);
        Route::get('/loan-accounts/{loanAccountId}/overdue-breakdown', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'getOverdueBreakdown']);
        Route::get('/loan-accounts/{loanAccountId}/actions-history', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'viewActionsHistory']);
        Route::get('/payment-status', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'paymentStatus']);

        Route::get('/visits/today', [App\Http\Controllers\Agent\AgentVisitControllerApi::class, 'todayVisits']);
        Route::get('/visits/recovered', [App\Http\Controllers\Agent\AgentVisitControllerApi::class, 'recoveredVisits']);
        Route::post('/visits/start', [App\Http\Controllers\Agent\AgentVisitControllerApi::class, 'startVisit']);
        Route::post('/visits/stop', [App\Http\Controllers\Agent\AgentVisitControllerApi::class, 'stopVisit']);
        Route::get('/visits', [App\Http\Controllers\Agent\AgentVisitControllerApi::class, 'index']);

        Route::get('/payment-history', [App\Http\Controllers\Agent\EmiPaymentControllerApi::class, 'paymentHistory']);
        Route::get('/high-risk-clients', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'highRiskClients']);
        Route::post('/emis/{emiId}/update-status', [App\Http\Controllers\Agent\EmiPaymentControllerApi::class, 'updateStatus']);
        Route::get('/followups', [App\Http\Controllers\Agent\EmiPaymentControllerApi::class, 'followups']);
        Route::put('/followups/{followupId}/reschedule', [App\Http\Controllers\Agent\EmiPaymentControllerApi::class, 'rescheduleFollowup']);
        Route::get('/status-options', [App\Http\Controllers\Agent\EmiPaymentControllerApi::class, 'getFollowupOptions']);

        Route::get('/support', [App\Http\Controllers\Agent\SupportControllerApi::class, 'index']);
        Route::get('/policy-pages', [App\Http\Controllers\Agent\PolicyPageControllerApi::class, 'index']);
        Route::get('/policy-pages/{slug}', [App\Http\Controllers\Agent\PolicyPageControllerApi::class, 'show']);
    });

    // Payment gateway callback (public)
    Route::post('/payments/callback', [App\Http\Controllers\Agent\EmiPaymentControllerApi::class, 'handle'])
        ->name('payment.callback');
});
