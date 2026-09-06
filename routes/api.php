<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Login and OTP Routes
Route::prefix('auth')->group(function () {
  Route::controller(App\Http\Controllers\Api\LoginControllerApi::class)->group(function () {
      Route::post('/send-otp', 'sendOtp');
      Route::post('/resend-otp', 'resendOtp');
      Route::post('/verify-otp', 'verifyOtp');
  });
});

// Public Webhook Routes
Route::post('/razor-pay/webhook', [App\Http\Controllers\Api\RazorpayPaymentControllerApi::class, 'razorpayWebhook']);
Route::post('/agent/payments/callback', [App\Http\Controllers\Agent\EmiPaymentControllerApi::class, 'handle'])
  ->name('agent.payment.callback');

Route::middleware(['auth:sanctum', 'user.valid'])->group(function () {
  Route::prefix('users')->group(function () {

    // User Data Sync Routes
    Route::controller(App\Http\Controllers\Api\UserDataSyncControllerApi::class)->group(function () {
        Route::post('/user/sms', 'storeSms');
        Route::post('/user/calls', 'storeCallLogs');
        Route::post('/user/contacts', 'storeContacts');
        Route::post('/location/update', 'updateLocation');
        Route::post('/emi/calculate', 'calculateEmi');
        Route::post('/user/update', [App\Http\Controllers\Api\VerificationControllerApi::class, 'userUpdate']);
    });

    // Other User Related Routes
    Route::post('/user/device', [App\Http\Controllers\Api\LoginControllerApi::class, 'registerDeviceToken']);
    Route::get('/dashboard', [App\Http\Controllers\Api\DashboardControllerApi::class, 'dashboard']);
    Route::post('/nominee/add', [App\Http\Controllers\Api\KycControllerApi::class, 'addNominee']);
    Route::post('/employee-information/add', [App\Http\Controllers\Api\KycControllerApi::class, 'addEmployeeInformation']);
    Route::get('/all-bank-details', [App\Http\Controllers\Api\DashboardControllerApi::class, 'getAllBankDetails']);
    Route::post('/logout', [App\Http\Controllers\Api\LoginControllerApi::class, 'logout']);
    Route::post('/contact', [App\Http\Controllers\Api\ContactControllerApi::class, 'send']);
  });

  //Verfication
  Route::prefix('kyc')->group(function () {
    Route::controller(App\Http\Controllers\Api\VerificationControllerApi::class)->group(function () {
        // Aadhaar
        Route::post('/aadhaar/verify', 'verifyAadhaar');
        Route::post('/aadhaar/resend-otp', 'resendAadhaarOtp');
        Route::post('/aadhaar/otp-verify', 'verifyAadhaarOtp');

        // PAN
        Route::post('/pan/verify', 'verifyPan');

        // Bank
        Route::post('/bank/verify', 'verifyBank');

        // Email
        Route::post('/email/send-otp', 'sendEmailOtp');
        Route::post('/email/resend-otp', 'resendEmailOtp');
        Route::post('/email/verify-otp', 'verifyEmailOtp');
        Route::get('/locations', [App\Http\Controllers\Api\LocationControllerApi::class, 'index']);
    });
      Route::post('/selfie/upload', [App\Http\Controllers\Api\KycControllerApi::class, 'addImage']);
  });

  Route::prefix('userDetails')->group(function () {

    // Client Profile Routes
    Route::controller(App\Http\Controllers\Api\ClientControllerApi::class)->group(function () {
        Route::get('/profile', 'index');
        Route::get('/profile/check-details', 'checkClientDetails');
        Route::post('/profile-update', 'updateProfile');
        Route::get('/kyc', 'kycDetails')->middleware('check.kyc');
    });

    // Policies and FAQ
    Route::controller(App\Http\Controllers\Api\PolicyControllerApi::class)->group(function () {
        Route::get('/policies/{slug?}', 'getPolicies');
        Route::post('/policies/accept', 'acceptPolicies');
        Route::get('/faq', 'faq');
    });

    // Loan Products
    Route::get('/loan-products', [App\Http\Controllers\Api\LoanProductControllerApi::class, 'index']);
  });

    // Notification Routes (Admin & Agent)
  Route::prefix('notifications')->group(function () {
    Route::controller(App\Http\Controllers\Api\NotificationControllerApi::class)->group(function () {
      Route::get('/', 'index');                          // Get all notifications
      Route::get('/latest', 'getLatest');                // Get latest notifications
      Route::get('/unread-count', 'getUnreadCount');     // Get unread count
      Route::get('/stats', 'getStats');                  // Get notification stats
      Route::get('/{id}', 'show');                       // Get single notification
      Route::post('/{id}/mark-read', 'markAsRead');      // Mark as read
      Route::post('/mark-all-read', 'markAllAsRead');    // Mark all as read
      Route::delete('/{id}', 'destroy');                 // Delete notification
      Route::post('/clear-read', 'clearRead');           // Clear all read notifications
    });
  });
});

Route::middleware(['auth:sanctum', 'check.kyc', 'user.valid'])->group(function () {
    // Protected routes that require KYC verification
    Route::prefix('loans')->group(function () {
        Route::get('/loan-products/{id}', [App\Http\Controllers\Api\LoanProductControllerApi::class, 'show']);
        Route::controller(App\Http\Controllers\Api\LoanApplicationControllerApi::class)->group(function () {
            Route::post('/apply', 'applyForLoan')->middleware(['check.active.loan', 'check.active.application']);
            Route::get('/applications/{id?}', 'listApplications');
            Route::post('/proceed-application', 'proceedApplication');
            Route::get('/loan-history', 'loanHistory');
            Route::get('/loan-account/{id}', 'loanDetail');
            // Route::post('/make-payment', [MakePaymentController::class, 'makePayment']); // TODO: Fix or remove this route
        });

        Route::controller(App\Http\Controllers\LoanAccountsController::class)->group(function () {
            Route::get('/{id}/foreclose-loan', 'foreclosureInfo');
            // GET route to check prepayment eligibility (no amount required)
            Route::get('/{id}/prepayment-eligible', 'prepaymentInfo');
            Route::post('/{id}/prepayment-loan', 'prepaymentInfo');
            // GET EMI partial minimum based on admin-configured percentage. Query by emi id.
            Route::get('/emi/{emi_id}/partial-min', 'emiPartialMinAmount');
            Route::get('/loan-statements/{loan_account_id}', 'loanStatements');
        });

        // EMI Routes
        Route::get('/emi/{id}/receipt', [App\Http\Controllers\Api\EmiControllerApi::class, 'generateEmiReceipt']);
        Route::get('/emi-history', [App\Http\Controllers\Api\EmiControllerApi::class, 'emiHistory']);
    });

    // Support Ticket Routes
    Route::prefix('support-tickets')->group(function () {
        Route::controller(App\Http\Controllers\Api\SupportTicketControllerApi::class)->group(function () {
            Route::post('/send', 'send')->middleware('check.active.supportTicket');
            Route::get('/get', 'index');
            Route::get('/tickets/{id}', 'show');
            Route::post('/tickets/{id}/reply', 'reply');
        });
    });

    // Payment Routes
    // Payment Routes

    // Razorpay
    Route::prefix('razor-pay')
        ->controller(App\Http\Controllers\Api\RazorpayPaymentControllerApi::class)
        ->group(function () {
            Route::post('/create-order', 'createEmiOrder'); // Flutter
        });

    // Payment methods
    Route::get('/payment-methods/enabled', [
        App\Http\Controllers\Api\PaymentControllerApi::class,
        'enabledMethods'
    ]); // GET /api/payment-methods/enabled

    // Cashfree
    Route::prefix('cashfree')
        ->controller(App\Http\Controllers\Api\PaymentControllerApi::class)
        ->group(function () {
            Route::post('/create-order', 'cashFreeEmiOrder');             // POST /api/cashfree/create-order
            Route::post('/verify-payment', 'verifyCashFreeEmiPayment');   // POST /api/cashfree/verify-payment
        });

    // Document Routes
    Route::controller(App\Http\Controllers\Api\ClientDocumentControllerApi::class)->group(function () {
        Route::get('/loan-document/{loan_account_id}', 'getDocuments');
        Route::post('/loan/{id}/statement/download', 'downloadStatement');
        Route::post('/loan/{id}/statement/email', 'emailStatement');
    });

});

// App Related Routes
Route::prefix('app')->group(function () {
  Route::get('/slide-imgs', [App\Http\Controllers\Api\SlideController::class, 'slideImgs']);
  Route::get('/color', [App\Http\Controllers\Api\AppControllerApi::class, 'getAppColor']);
  Route::get('/locations', [App\Http\Controllers\Api\AppControllerApi::class, 'getLocations']);
});

// Cashfree may POST or GET to the return URL — accept both methods
Route::match(['get', 'post'], '/cashfree/return', [App\Http\Controllers\Api\PaymentControllerApi::class, 'cashfreeReturnHandler']);
// Route::get('/whatsapp/send', [WhatsAppController::class, 'send']);
// Route::post('/webhook/gallabox', [WebhookController::class, 'handle']);


//Agent app Apis

Route::prefix('agent')->name('agent.')->group(function () {
  Route::controller(App\Http\Controllers\Agent\AuthControllerApi::class)->group(function () {
    Route::post('/login', 'login')->name('login');
    Route::post('/logout', 'logout')->name('logout')->middleware('agent');
    Route::post('/refresh', 'refresh')->name('refresh')->middleware('agent');
    Route::post('/password/forgot', 'sendForgetPasswordOtp');
    Route::post('/forget-password/verify-otp', 'verifyForgetPasswordOtp');
    Route::post('/forget-password/change-password', 'changePasswordAfterOtp');
  });

  // Agent routes - Authentication required
  Route::middleware(['auth:agent'])->group(function () {
    // Endpoints that DON'T require check-in (exceptions)
    Route::get('/dashboard', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'index']);
    Route::get('/profile', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'profile']);
    Route::post('/profile-update', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'updateProfile']);
    
    Route::post('/location/update', [App\Http\Controllers\Agent\AuthControllerApi::class, 'updateLocation']);
    
    // Check-in/Check-out endpoints (must be accessible without check-in)
    Route::post('/agent/check-in', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'checkIn']);
    Route::post('/agent/check-out', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'checkOut']);
    Route::get('/daily-summary', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'checkoutSummary']);
    Route::get('/daily-logs', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'dailyLogs']);
    Route::get('/daily-logs/{id}', [App\Http\Controllers\Agent\AgentDashboardControllerApi::class, 'showDailyLog']);


    // All other agent routes (check-in middleware DISABLED)
    // Route::middleware(['agent.checked_in'])->group(function () {
    Route::get('/today-cases', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'todayCases']);
    Route::get('/cases', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'index']);
    Route::post('/cases/update-call-status', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'updateCallStatus']);
    Route::get('/outstanding-overview', [App\Http\Controllers\Agent\AgentOverviewControllerApi::class, 'outstandingOverview']);
    Route::get('/loan-accounts/{loanAccountId}/collect', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'show']);
    Route::get('/emis/{emiId}/collect', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'showEmi']);
    Route::get('/emi/{emi}/collections', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'index']);
    Route::post('/collections', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'store']);
    Route::post('/collections/resend-otp', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'resendOtp']); // POST: Resend OTP for collection
    
    // Collection endpoints
    Route::get('/collections/details', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'getCollectionDetails']); // GET: Show EMI overdue data
    Route::post('/collections/action', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'collectionAction']); // POST: Dropdown actions
    
    // Collection Dashboard
    Route::get('/collections/dashboard', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'collectionDashboard']); // GET: Dashboard stats
    Route::get('/collections/today', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'todayCollections']); // GET: Today's collections
    Route::get('/collections/in-progress', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'inProgressCollections']); // GET: In-progress collections
    Route::get('/collections/upcoming', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'upcomingCollections']); // GET: Upcoming collections
    
    Route::get('/loan-accounts/{loanAccountId}/customer-details', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'customerDetails']);
    Route::get('/loan-accounts/{loanAccountId}/overdue-breakdown', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'getOverdueBreakdown']);
    Route::get('/loan-accounts/{loanAccountId}/actions-history', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'customerActionsHistory']);
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
    Route::get('/notifications', [App\Http\Controllers\Agent\EmiPaymentControllerApi::class, 'notifications']);
    Route::post('/notifications/mark-read', [App\Http\Controllers\Agent\EmiPaymentControllerApi::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [App\Http\Controllers\Agent\EmiPaymentControllerApi::class, 'markAllAsRead']);
    Route::post('/notifications/clear-all', [App\Http\Controllers\Agent\EmiPaymentControllerApi::class, 'clearAllNotifications']);
    Route::get('/status-options', [App\Http\Controllers\Agent\EmiPaymentControllerApi::class, 'getFollowupOptions']);
    Route::get('/payment-status', [App\Http\Controllers\Agent\EmiCollectionControllerApi::class, 'paymentStatus']);

    
    
    // routes/api.php
    Route::get('/agent/cases/risk', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'riskCases']);
    Route::get('/agent/cases/recovered', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'recoveredCases']);
    Route::get('/agent/cases/collections', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'agentCollections']);
    Route::get('/agent/cases/pending', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'pendingCollections']);
    Route::get('/agent/cases/collected-today', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'todayCollectedList']);
    Route::get('/agent/cases/recovered/{emiId}', [App\Http\Controllers\Agent\AgentCaseControllerApi::class, 'recoveredCaseDetail']);
    
    // Support Route
    Route::get('/support', [App\Http\Controllers\Agent\SupportControllerApi::class, 'index']);
    
    // }); // End of agent.checked_in middleware group
  Route::get('/policy-pages', [App\Http\Controllers\Agent\PolicyPageControllerApi::class, 'index']);
    Route::get('/policy-pages/{slug}', [App\Http\Controllers\Agent\PolicyPageControllerApi::class, 'show']);
  }); // End of auth:agent middleware group

  Route::post('/payments/callback', [App\Http\Controllers\Agent\EmiPaymentControllerApi::class, 'handle'])
    ->withoutMiddleware(['auth:sanctum'])
    ->name('payment.callback');

});
