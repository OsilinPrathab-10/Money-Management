<?php

/**
 * Customer mobile app API routes.
 * Base path: /api/...
 *
 * Controllers live under App\Http\Controllers\Customer (app-settings)
 * and App\Http\Controllers\Api (existing customer/loan flows).
 */

use Illuminate\Support\Facades\Route;

// Customer app settings (public)
Route::prefix('customer')->group(function () {
    Route::get('/app-settings', [App\Http\Controllers\Customer\AppSettingControllerApi::class, 'index']);
    Route::get('/app-settings/policies/{type?}', [App\Http\Controllers\Customer\AppSettingControllerApi::class, 'policies']);
    Route::get('/onboarding', [App\Http\Controllers\Customer\AppSettingControllerApi::class, 'onboarding']);
    Route::get('/privacy_policy', [App\Http\Controllers\Customer\AppSettingControllerApi::class, 'privacyPolicy']);
    Route::get('/terms_conditions', [App\Http\Controllers\Customer\AppSettingControllerApi::class, 'termsConditions']);
    Route::get('/about-us', [App\Http\Controllers\Customer\AppSettingControllerApi::class, 'aboutUs']);
    Route::get('/locations', [App\Http\Controllers\Customer\AuthControllerApi::class, 'locations']);
    Route::get('/register-dropdowns', [App\Http\Controllers\Customer\AuthControllerApi::class, 'registerDropdowns']);

    Route::get('/banners/{module?}', [App\Http\Controllers\Customer\CatalogControllerApi::class, 'banners']);
    Route::get('/loan-types', [App\Http\Controllers\Customer\CatalogControllerApi::class, 'loanTypes']);
    Route::get('/loan-types/{id}', [App\Http\Controllers\Customer\CatalogControllerApi::class, 'loanTypeShow']);
    Route::get('/loans/dropdowns', [App\Http\Controllers\Customer\LoanControllerApi::class, 'loanDropdowns']);
    Route::get('/chit-schemes', [App\Http\Controllers\Customer\CatalogControllerApi::class, 'chitSchemes']);
    Route::get('/chit-schemes/{id}', [App\Http\Controllers\Customer\CatalogControllerApi::class, 'chitSchemeShow']);
    Route::get('/chit-schemes/{schemeId}/groups', [App\Http\Controllers\Customer\CatalogControllerApi::class, 'schemeGroups']);
    Route::get('/chit-groups', [App\Http\Controllers\Customer\CatalogControllerApi::class, 'chitGroups']);
    Route::get('/fd-schemes', [App\Http\Controllers\Customer\CatalogControllerApi::class, 'fdSchemes']);
    Route::get('/fd-schemes/{id}', [App\Http\Controllers\Customer\CatalogControllerApi::class, 'fdSchemeShow']);
});

// Legacy / shared public app helpers used by customer app
Route::prefix('app')->group(function () {
    Route::get('/slide-imgs', [App\Http\Controllers\Api\SlideController::class, 'slideImgs']);
    Route::get('/color', [App\Http\Controllers\Api\AppControllerApi::class, 'getAppColor']);
    Route::get('/locations', [App\Http\Controllers\Api\AppControllerApi::class, 'getLocations']);
    // Alias to new settings endpoint
    Route::get('/settings', [App\Http\Controllers\Customer\AppSettingControllerApi::class, 'index']);
});

// Customer auth — password login + OTP (first/forgot) + MPIN
Route::prefix('customer/auth')->group(function () {
    Route::controller(App\Http\Controllers\Customer\AuthControllerApi::class)->group(function () {
        Route::get('/locations', 'locations');
        Route::get('/register-dropdowns', 'registerDropdowns');

        // Customer registration (same fields as Admin Add Client)
        Route::post('/register', 'register');

        // Password creation / management
        Route::post('/create-password', 'createPassword');
        Route::post('/set-password', 'createPassword');

        // Password login (username = email|mobile, default password = mobile)
        Route::post('/login', 'login');

        // OTP: first_login | forgot_password | forgot_mpin (purpose param)
        Route::post('/send-otp', 'sendOtp');
        Route::post('/resend-otp', 'resendOtp');
        Route::post('/verify-otp', 'verifyOtp');
        Route::post('/reset-password', 'resetPassword');

        // MPIN after login/OTP
        Route::post('/set-mpin', 'setMpin');
        Route::post('/verify-mpin', 'verifyMpin');

        // Forgot MPIN (OTP → reset)
        Route::post('/forgot-mpin/send-otp', 'forgotMpinSendOtp');
        Route::post('/forgot-mpin/verify-otp', 'forgotMpinVerifyOtp');
        Route::post('/forgot-mpin/reset', 'forgotMpinReset');

        // Forgot password aliases
        Route::post('/forgot-password/send-otp', 'forgotPasswordSendOtp');
        Route::post('/forgot-password/verify-otp', 'forgotPasswordVerifyOtp');
        Route::post('/forgot-password/reset', 'resetPassword');
    });
});

// Legacy customer auth paths (same controller)
Route::prefix('auth')->group(function () {
    Route::controller(App\Http\Controllers\Customer\AuthControllerApi::class)->group(function () {
        Route::post('/register', 'register');
        Route::post('/create-password', 'createPassword');
        Route::post('/set-password', 'createPassword');
        Route::post('/login', 'login');
        Route::post('/send-otp', 'sendOtp');
        Route::post('/resend-otp', 'resendOtp');
        Route::post('/verify-otp', 'verifyOtp');
        Route::post('/reset-password', 'resetPassword');
        Route::post('/set-mpin', 'setMpin');
        Route::post('/verify-mpin', 'verifyMpin');
        Route::post('/forgot-mpin/send-otp', 'forgotMpinSendOtp');
        Route::post('/forgot-mpin/verify-otp', 'forgotMpinVerifyOtp');
        Route::post('/forgot-mpin/reset', 'forgotMpinReset');
    });
});

Route::middleware(['auth:sanctum', 'user.valid'])->group(function () {
    Route::prefix('customer/auth')->group(function () {
        Route::controller(App\Http\Controllers\Customer\AuthControllerApi::class)->group(function () {
            Route::post('/change-mpin', 'changeMpin');
            Route::post('/logout', 'logout');
            Route::get('/me', 'me');
            Route::post('/fcm-token', 'registerFcmToken');
            Route::post('/fcm-token/update', 'registerFcmToken');
        });
    });

    Route::prefix('users')->group(function () {
        Route::controller(App\Http\Controllers\Api\UserDataSyncControllerApi::class)->group(function () {
            Route::post('/user/sms', 'storeSms');
            Route::post('/user/calls', 'storeCallLogs');
            Route::post('/user/contacts', 'storeContacts');
            Route::post('/location/update', 'updateLocation');
            Route::post('/emi/calculate', 'calculateEmi');
            Route::post('/user/update', [App\Http\Controllers\Api\VerificationControllerApi::class, 'userUpdate']);
        });

        Route::post('/user/device', [App\Http\Controllers\Api\LoginControllerApi::class, 'registerDeviceToken']);
        Route::post('/user/fcm-token', [App\Http\Controllers\Api\LoginControllerApi::class, 'registerDeviceToken']);
        Route::post('/fcm-token/update', [App\Http\Controllers\Api\LoginControllerApi::class, 'registerDeviceToken']);
        Route::post('/fcm-token', [App\Http\Controllers\Api\LoginControllerApi::class, 'registerDeviceToken']);
        Route::get('/dashboard', [App\Http\Controllers\Api\DashboardControllerApi::class, 'dashboard']);
        Route::post('/nominee/add', [App\Http\Controllers\Api\KycControllerApi::class, 'addNominee']);
        Route::post('/employee-information/add', [App\Http\Controllers\Api\KycControllerApi::class, 'addEmployeeInformation']);
        Route::get('/all-bank-details', [App\Http\Controllers\Api\DashboardControllerApi::class, 'getAllBankDetails']);
        Route::post('/logout', [App\Http\Controllers\Customer\AuthControllerApi::class, 'logout']);
        Route::post('/contact', [App\Http\Controllers\Api\ContactControllerApi::class, 'send']);
    });

    Route::prefix('kyc')->group(function () {
        Route::controller(App\Http\Controllers\Api\VerificationControllerApi::class)->group(function () {
            Route::post('/aadhaar/verify', 'verifyAadhaar');
            Route::post('/aadhaar/resend-otp', 'resendAadhaarOtp');
            Route::post('/aadhaar/otp-verify', 'verifyAadhaarOtp');
            Route::post('/pan/verify', 'verifyPan');
            Route::post('/bank/verify', 'verifyBank');
            Route::post('/email/send-otp', 'sendEmailOtp');
            Route::post('/email/resend-otp', 'resendEmailOtp');
            Route::post('/email/verify-otp', 'verifyEmailOtp');
            Route::get('/locations', [App\Http\Controllers\Api\LocationControllerApi::class, 'index']);
        });
        Route::post('/selfie/upload', [App\Http\Controllers\Api\KycControllerApi::class, 'addImage']);
    });

    Route::prefix('customer/profile')->controller(App\Http\Controllers\Api\ClientControllerApi::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/update', 'updateProfile');
    });

    Route::prefix('userDetails')->group(function () {
        Route::controller(App\Http\Controllers\Api\ClientControllerApi::class)->group(function () {
            Route::get('/profile', 'index');
            Route::get('/profile/check-details', 'checkClientDetails');
            Route::post('/profile-update', 'updateProfile');
            Route::get('/kyc', 'kycDetails')->middleware('check.kyc');
        });

        Route::controller(App\Http\Controllers\Api\PolicyControllerApi::class)->group(function () {
            Route::get('/policies/{slug?}', 'getPolicies');
            Route::post('/policies/accept', 'acceptPolicies');
            Route::get('/faq', 'faq');
        });

        Route::get('/loan-products', [App\Http\Controllers\Api\LoanProductControllerApi::class, 'index']);
    });

    Route::prefix('customer/notifications')->group(function () {
        Route::controller(App\Http\Controllers\Api\NotificationControllerApi::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/latest', 'getLatest');
            Route::get('/unread-count', 'getUnreadCount');
            Route::get('/stats', 'getStats');

            // Static POST routes must be registered before /{id}
            Route::post('/read', 'markAsRead');
            Route::post('/read/{id}', 'markAsRead');
            Route::post('/mark-read', 'markAsRead');
            Route::post('/{id}/mark-read', 'markAsRead');
            Route::post('/read-all', 'markAllAsRead');
            Route::post('/readall', 'markAllAsRead');
            Route::post('/mark-all-read', 'markAllAsRead');
            Route::post('/clear-read', 'clearRead');

            Route::get('/{id}', 'show');
            Route::delete('/{id}', 'destroy');
        });
    });
});

Route::middleware(['auth:sanctum', 'user.valid'])->group(function () {
    Route::prefix('chits')->group(function () {
        Route::controller(App\Http\Controllers\Api\ChitControllerApi::class)->group(function () {
            Route::get('/my-chits', 'getMyChits');
            Route::post('/request-settlement', 'requestSettlement')->middleware('check.kyc');
        });

        Route::controller(App\Http\Controllers\Customer\ChitControllerApi::class)->group(function () {
            Route::get('/settlements/metadata', 'settlementMetadata');
            Route::get('/settlements/dropdowns', 'settlementMetadata');
            Route::match(['get', 'post'], '/settlements/preview', 'settlementPreview');
            Route::get('/settlements/history', 'settlementApplications');
            Route::get('/settlement-history', 'settlementApplications');
            Route::post('/settlements', 'applySettlement')->middleware('check.kyc');
            Route::post('/settlements/apply', 'applySettlement')->middleware('check.kyc');
            Route::post('/settlement-applications', 'applySettlement')->middleware('check.kyc');
            Route::post('/settlements/{id}', 'applySettlement')->middleware('check.kyc');
            Route::post('/settlement-applications/{id}', 'applySettlement')->middleware('check.kyc');
            Route::get('/settlements/{id?}', 'settlementApplications');
            Route::get('/settlement-applications/{id?}', 'settlementApplications');
        });
    });

    Route::prefix('customer')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Api\DashboardControllerApi::class, 'dashboard']);
        Route::get('/loans/dropdowns', [App\Http\Controllers\Customer\LoanControllerApi::class, 'loanDropdowns']);
        Route::post('/loans/apply', [App\Http\Controllers\Customer\LoanControllerApi::class, 'applyLoan'])->middleware('check.kyc');
        Route::get('/loans/applications-with-accounts', [App\Http\Controllers\Customer\LoanControllerApi::class, 'applicationsWithAccounts']);
        Route::get('/loans/applications/{id?}', [App\Http\Controllers\Customer\LoanControllerApi::class, 'loanApplications']);
        Route::get('/loans/accounts/{id?}', [App\Http\Controllers\Customer\LoanControllerApi::class, 'loanAccounts']);

        Route::get('/chits/dropdowns', [App\Http\Controllers\Customer\ChitControllerApi::class, 'chitDropdowns']);
        Route::post('/chits/apply', [App\Http\Controllers\Customer\ChitControllerApi::class, 'applyChit'])->middleware('check.kyc');
        Route::get('/chits/applications-with-accounts', [App\Http\Controllers\Customer\ChitControllerApi::class, 'applicationsWithAccounts']);
        Route::get('/chits/applications/{id?}', [App\Http\Controllers\Customer\ChitControllerApi::class, 'chitApplications']);
        Route::get('/chits/accounts/{id?}', [App\Http\Controllers\Customer\ChitControllerApi::class, 'chitAccounts']);
        Route::get('/chits/settlements/metadata', [App\Http\Controllers\Customer\ChitControllerApi::class, 'settlementMetadata']);
        Route::get('/chits/settlements/dropdowns', [App\Http\Controllers\Customer\ChitControllerApi::class, 'settlementMetadata']);
        Route::match(['get', 'post'], '/chits/settlements/preview', [App\Http\Controllers\Customer\ChitControllerApi::class, 'settlementPreview']);
        Route::get('/chits/settlements/history', [App\Http\Controllers\Customer\ChitControllerApi::class, 'settlementApplications']);
        Route::get('/chits/settlement-history', [App\Http\Controllers\Customer\ChitControllerApi::class, 'settlementApplications']);
        Route::post('/chits/settlements', [App\Http\Controllers\Customer\ChitControllerApi::class, 'applySettlement'])->middleware('check.kyc');
        Route::post('/chits/settlements/apply', [App\Http\Controllers\Customer\ChitControllerApi::class, 'applySettlement'])->middleware('check.kyc');
        Route::post('/chits/settlement-applications', [App\Http\Controllers\Customer\ChitControllerApi::class, 'applySettlement'])->middleware('check.kyc');
        Route::post('/chits/request-settlement', [App\Http\Controllers\Customer\ChitControllerApi::class, 'applySettlement'])->middleware('check.kyc');
        Route::post('/chits/settlements/{id}', [App\Http\Controllers\Customer\ChitControllerApi::class, 'applySettlement'])->middleware('check.kyc');
        Route::post('/chits/settlement-applications/{id}', [App\Http\Controllers\Customer\ChitControllerApi::class, 'applySettlement'])->middleware('check.kyc');
        Route::get('/chits/settlements/{id?}', [App\Http\Controllers\Customer\ChitControllerApi::class, 'settlementApplications']);
        Route::get('/chits/settlement-applications/{id?}', [App\Http\Controllers\Customer\ChitControllerApi::class, 'settlementApplications']);
        Route::get('/chits/family-members', [App\Http\Controllers\Customer\ChitControllerApi::class, 'familyMembers']);
        Route::get('/chits/payment-history', [App\Http\Controllers\Customer\ChitControllerApi::class, 'paymentHistory']);
        Route::get('/chits/accounts/{id}/payment-history', [App\Http\Controllers\Customer\ChitControllerApi::class, 'paymentHistory']);

        Route::get('/fd/dropdowns', [App\Http\Controllers\Customer\FdControllerApi::class, 'fdDropdowns']);
        Route::post('/fd/apply', [App\Http\Controllers\Customer\FdControllerApi::class, 'applyFd'])->middleware('check.kyc');
        Route::get('/fd/applications-with-accounts', [App\Http\Controllers\Customer\FdControllerApi::class, 'applicationsWithAccounts']);
        Route::get('/fd/applications/{id?}', [App\Http\Controllers\Customer\FdControllerApi::class, 'fdApplications']);
        Route::get('/fd/settlement-history', [App\Http\Controllers\Customer\FdControllerApi::class, 'settlementHistory']);
        Route::get('/fd/accounts/{id}/settlement-history', [App\Http\Controllers\Customer\FdControllerApi::class, 'settlementHistory']);
        Route::get('/fd/accounts/{id?}', [App\Http\Controllers\Customer\FdControllerApi::class, 'fdAccounts']);

        Route::controller(App\Http\Controllers\Customer\CustomerWalletControllerApi::class)->group(function () {
            Route::get('/wallets/balance', 'balance');
            Route::get('/wallets/transactions', 'transactions');
        });

        Route::prefix('support-tickets')->group(function () {
            Route::controller(App\Http\Controllers\Api\SupportTicketControllerApi::class)->group(function () {
                Route::get('/', 'index');
                Route::post('/send', 'send')->middleware('check.active.supportTicket');
                Route::get('/{id}', 'show');
                Route::post('/{id}/reply', 'reply');
            });
        });
    });

    Route::prefix('loans')->group(function () {
        Route::get('/loan-products/{id}', [App\Http\Controllers\Api\LoanProductControllerApi::class, 'show']);
        Route::controller(App\Http\Controllers\Api\LoanApplicationControllerApi::class)->group(function () {
        Route::post('/apply', 'applyForLoan')->middleware(['check.kyc', 'check.active.loan', 'check.active.application']);
        Route::get('/applications/{id?}', 'listApplications');
        Route::post('/proceed-application', 'proceedApplication')->middleware('check.kyc');
        Route::get('/loan-history', 'loanHistory');
        Route::get('/loan-account/{id}', 'loanDetail');
        });

        Route::controller(App\Http\Controllers\LoanAccountsController::class)->group(function () {
            Route::get('/{id}/foreclose-loan', 'foreclosureInfo');
            Route::get('/{id}/prepayment-eligible', 'prepaymentInfo');
            Route::post('/{id}/prepayment-loan', 'prepaymentInfo');
            Route::get('/emi/{emi_id}/partial-min', 'emiPartialMinAmount');
            Route::get('/loan-statements/{loan_account_id}', 'loanStatements');
        });

        Route::get('/emi/{id}/receipt', [App\Http\Controllers\Api\EmiControllerApi::class, 'generateEmiReceipt']);
        Route::get('/emi-history', [App\Http\Controllers\Api\EmiControllerApi::class, 'emiHistory']);
    });

    Route::post('/account-deletion/request', [\App\Http\Controllers\PublicAccountDeletionController::class, 'storeForClient']);

    Route::prefix('razor-pay')
        ->controller(App\Http\Controllers\Api\RazorpayPaymentControllerApi::class)
        ->group(function () {
            Route::post('/create-order', 'createEmiOrder');
        });

    Route::get('/payment-methods/enabled', [
        App\Http\Controllers\Api\PaymentControllerApi::class,
        'enabledMethods',
    ]);

    Route::prefix('cashfree')
        ->controller(App\Http\Controllers\Api\PaymentControllerApi::class)
        ->group(function () {
            Route::post('/create-order', 'cashFreeEmiOrder');
            Route::post('/verify-payment', 'verifyCashFreeEmiPayment');
        });

    Route::controller(App\Http\Controllers\Api\ClientDocumentControllerApi::class)->group(function () {
        Route::get('/loan-document/{loan_account_id}', 'getDocuments');
        Route::post('/loan/{id}/statement/download', 'downloadStatement');
        Route::post('/loan/{id}/statement/email', 'emailStatement');
    });
});
