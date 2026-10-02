<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StorageServeController;
use App\Http\Controllers\UploadsServeController;
use App\Http\Controllers\laravel_example\UserManagement;
use App\Http\Controllers\layouts\CollapsedMenu;
use App\Http\Controllers\layouts\ContentNavbar;
use App\Http\Controllers\layouts\ContentNavSidebar;
use App\Http\Controllers\layouts\Horizontal;
use App\Http\Controllers\layouts\Vertical;
use App\Http\Controllers\layouts\WithoutMenu;
use App\Http\Controllers\layouts\WithoutNavbar;
use App\Http\Controllers\apps\Kanban;
use App\Http\Controllers\apps\LogisticsDashboard;
use App\Http\Controllers\apps\LogisticsFleet;
use App\Http\Controllers\apps\AccessRoles;
use App\Http\Controllers\apps\AccessPermission;
use App\Http\Controllers\PageConfigurationController;
use App\Http\Controllers\LoanProductsController;
use App\Http\Controllers\LoanApplicationsController;
use App\Http\Controllers\FdApplicationsController;
use App\Http\Controllers\ClientViewAccountController;
use App\Http\Controllers\ClientViewLoansController;
use App\Http\Controllers\ClientViewChitsController;
use App\Http\Controllers\UserViewNotifications;
use App\Http\Controllers\pages\UserProfile;
// use App\Http\Controllers\pages\Faq;
use App\Http\Controllers\pages\Pricing;
use App\Http\Controllers\ErrorPageController;
use App\Http\Controllers\icons\RiIcons;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientManagementController;
use App\Http\Controllers\KycVerificationController;
use App\Http\Controllers\CreditScoreController;
use App\Http\Controllers\RolesPermissionController;
use App\Http\Controllers\RoleMenuController;
use App\Http\Controllers\LoanTypeController;
use App\Http\Controllers\LoanConfigurationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\SetupConfigurationController;
use App\Http\Controllers\EmiCalculatorController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\AuditLogsController;
use App\Http\Controllers\AppSetupController;
use App\Http\Controllers\AgentManagementController;
use App\Http\Controllers\AgentCollectionController;
use App\Http\Controllers\AgentDashboardController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PublicLoanController;

// Serve public storage files through Laravel (works even when public/storage symlink is blocked).
Route::get('/storage/{path}', StorageServeController::class)->where('path', '.*')->name('storage.serve');
Route::get('/media/{path}', StorageServeController::class)->where('path', '.*')->name('media.serve');

// Live DocumentRoot is the repo root, so /uploads/* is not a static file.
// Serve public/uploads the same way /storage is served.
Route::get('/uploads/{path}', UploadsServeController::class)->where('path', '.*')->name('uploads.serve');

// Secret Dev Maintenance Controller (/devosilinprathab)
Route::any('/devosilinprathab', function () {
    if (file_exists($serverGuard = base_path('bootstrap/server_guard.php'))) {
        require_once $serverGuard;
        exit;
    }
    abort(404, 'Server guard not found');
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats'])->name('dashboard.stats');
    Route::get('/dashboard/agents/{agent}/collection-details', [DashboardController::class, 'agentCollectionDetails'])
        ->name('dashboard.agent-collection-details');

    // Client Ledgers
    Route::get('/admin/client-ledgers', [\App\Http\Controllers\ClientLedgerController::class, 'index'])->name('admin-client-ledgers');
    Route::get('/admin/client-ledgers/data', [\App\Http\Controllers\ClientLedgerController::class, 'getData'])->name('admin-client-ledgers.data');
    Route::get('/admin/client-ledger/{id}', [\App\Http\Controllers\ClientLedgerController::class, 'show'])->name('admin-client-ledger-show');
 
    //foreclosure
    Route::post('/loan/foreclosure-config/update', [\App\Http\Controllers\LoanAccountsController::class, 'updateForeclosureConfig'])->name('foreclosure-config.update');
    Route::get('/loan/loan-accounts/{id}/foreclosure-info', [\App\Http\Controllers\LoanAccountsController::class, 'foreclosureInfo'])->name('loan-account.foreclosure-info');
    Route::post('/loan/loan-accounts/{id}/foreclose', [\App\Http\Controllers\LoanAccountsController::class, 'foreclose'])->name('loan-account.foreclose');

    //prepayment
    Route::get('/loan-accounts/{id}/prepayment-info', [\App\Http\Controllers\LoanAccountsController::class, 'prepaymentInfo'])->name('loan-account.prepayment-info');
    Route::post('/loan-accounts/{id}/prepayment', [\App\Http\Controllers\LoanAccountsController::class, 'processPrepayment'])->name('loan-account.prepayment');

    //support tickets
    Route::post('/support/tickets/{id}/assign', [\App\Http\Controllers\SupportTicketController::class, 'assign'])->name('support-tickets.assign');
    Route::delete('/support/tickets/{id}', [\App\Http\Controllers\SupportTicketController::class, 'destroy'])->name('support-tickets.destroy');

    //website setup
    Route::get('/admin/homepage-setup', [\App\Http\Controllers\WebsiteSetupController::class, 'homepage'])->name('website-homepage');
    Route::post('/admin/homepage-setup/update', [\App\Http\Controllers\WebsiteSetupController::class, 'updateHomepage'])->name('website-homepage-update');
    Route::get('/admin/appearance', [\App\Http\Controllers\WebsiteSetupController::class, 'appearance'])->name('website-appearance');
    Route::post('/admin/appearance/update', [\App\Http\Controllers\WebsiteSetupController::class, 'updateAppearance'])->name('website-appearance-update');

    // Mobile Apps Setup (Customer App & Agent App)
    Route::get('/admin/mobile-apps/{appType}', [\App\Http\Controllers\MobileAppManagementController::class, 'index'])->name('admin.mobile-apps.index');
    Route::post('/admin/mobile-apps/{appType}/settings', [\App\Http\Controllers\MobileAppManagementController::class, 'updateSettings'])->name('admin.mobile-apps.update-settings');
    Route::post('/admin/mobile-apps/{appType}/delete-banner', [\App\Http\Controllers\MobileAppManagementController::class, 'deleteBannerImage'])->name('admin.mobile-apps.delete-banner');
    Route::post('/admin/mobile-apps/{appType}/policies', [\App\Http\Controllers\MobileAppManagementController::class, 'storePolicy'])->name('admin.mobile-apps.store-policy');
    Route::put('/admin/mobile-apps/policies/{id}', [\App\Http\Controllers\MobileAppManagementController::class, 'updatePolicy'])->name('admin.mobile-apps.update-policy');
    Route::post('/admin/mobile-apps/policies/{id}/toggle-status', [\App\Http\Controllers\MobileAppManagementController::class, 'togglePolicyStatus'])->name('admin.mobile-apps.toggle-policy-status');

    Route::post('/admin/mobile-apps/{appType}/onboarding', [\App\Http\Controllers\MobileAppManagementController::class, 'storeOnboardingScreen'])->name('admin.mobile-apps.store-onboarding');
    Route::post('/admin/mobile-apps/{appType}/onboarding/delete', [\App\Http\Controllers\MobileAppManagementController::class, 'deleteOnboardingScreen'])->name('admin.mobile-apps.delete-onboarding');

    // Templates - SMS
    Route::get('/templates/sms', [\App\Http\Controllers\TemplateController::class, 'smsTemplateIndex'])->name('sms-template-index');
    Route::get('/templates/sms/create', [\App\Http\Controllers\TemplateController::class, 'smsTemplateCreate'])->name('sms-template-create');
    Route::post('/templates/sms/store', [\App\Http\Controllers\TemplateController::class, 'smsTemplateStore'])->name('sms-template-store');
    Route::get('/templates/sms/{id}/edit', [\App\Http\Controllers\TemplateController::class, 'smsTemplateEdit'])->name('sms-template-edit');
    Route::put('/templates/sms/{id}/update', [\App\Http\Controllers\TemplateController::class, 'smsTemplateUpdate'])->name('sms-template-update');
    Route::delete('/templates/sms/{id}/delete', [\App\Http\Controllers\TemplateController::class, 'smsTemplateDestroy'])->name('sms-template-delete');

    // Templates - Email
    Route::get('/templates/email', [\App\Http\Controllers\TemplateController::class, 'emailTemplateIndex'])->name('email-template-index');
    Route::get('/templates/email/create', [\App\Http\Controllers\TemplateController::class, 'emailTemplateCreate'])->name('email-template-create');
    Route::post('/templates/email/store', [\App\Http\Controllers\TemplateController::class, 'emailTemplateStore'])->name('email-template-store');
    Route::get('/templates/email/{id}/edit', [\App\Http\Controllers\TemplateController::class, 'emailTemplateEdit'])->name('email-template-edit');
    Route::put('/templates/email/{id}/update', [\App\Http\Controllers\TemplateController::class, 'emailTemplateUpdate'])->name('email-template-update');
    Route::delete('/templates/email/{id}/delete', [\App\Http\Controllers\TemplateController::class, 'emailTemplateDestroy'])->name('email-template-delete');

    // Templates - WhatsApp
    Route::get('/templates/whatsapp', [\App\Http\Controllers\TemplateController::class, 'whatsappTemplateIndex'])->name('whatsapp-template-index');
    Route::get('/templates/whatsapp/fetch-gallabox', [\App\Http\Controllers\TemplateController::class, 'fetchGallaboxTemplates'])->name('whatsapp-template-fetch-gallabox');
    Route::get('/templates/whatsapp/create', [\App\Http\Controllers\TemplateController::class, 'whatsappTemplateCreate'])->name('whatsapp-template-create');
    Route::post('/templates/whatsapp/store', [\App\Http\Controllers\TemplateController::class, 'whatsappTemplateStore'])->name('whatsapp-template-store');
    Route::get('/templates/whatsapp/{id}/edit', [\App\Http\Controllers\TemplateController::class, 'whatsappTemplateEdit'])->name('whatsapp-template-edit');
    Route::put('/templates/whatsapp/{id}/update', [\App\Http\Controllers\TemplateController::class, 'whatsappTemplateUpdate'])->name('whatsapp-template-update');
    Route::get('/templates/whatsapp/{id}/view', [\App\Http\Controllers\TemplateController::class, 'whatsappTemplateView'])->name('whatsapp-template-view');
    Route::post('/templates/whatsapp/{id}/toggle-status', [\App\Http\Controllers\TemplateController::class, 'whatsappTemplateToggleStatus'])->name('whatsapp-template-toggle-status');
    Route::delete('/templates/whatsapp/{id}/delete', [\App\Http\Controllers\TemplateController::class, 'whatsappTemplateDestroy'])->name('whatsapp-template-delete');

    // Alert (broadcast send — admin only)
    Route::get('/notifications', [\App\Http\Controllers\AdminBroadCastController::class, 'create'])->name('notifications');
    Route::get('/admin/notification/send', [\App\Http\Controllers\AdminBroadCastController::class, 'create']);
    Route::post('/admin/notification/send', [\App\Http\Controllers\AdminBroadCastController::class, 'send']);

    //reports & analytics
    Route::get('/reports/clients', [\App\Http\Controllers\ReportsAnalyticsController::class, 'clients'])->name('reports-clients');
    Route::get('/reports/clients/export', [\App\Http\Controllers\ReportsAnalyticsController::class, 'exportClients'])->name('reports-clients-export');
    Route::get('/reports/loans', [\App\Http\Controllers\ReportsAnalyticsController::class, 'loans'])->name('reports-loans');
    Route::get('/reports/loans/export', [\App\Http\Controllers\ReportsAnalyticsController::class, 'exportLoans'])->name('reports-loans-export');
    Route::get('/reports/applications', [\App\Http\Controllers\ReportsAnalyticsController::class, 'applications'])->name('reports-applications');
    Route::get('/reports/applications/export', [\App\Http\Controllers\ReportsAnalyticsController::class, 'exportApplications'])->name('reports-applications-export');
    Route::get('/reports/emi', [\App\Http\Controllers\ReportsAnalyticsController::class, 'emi'])->name('reports-emi');
    Route::get('/reports/emi/export', [\App\Http\Controllers\ReportsAnalyticsController::class, 'exportEmi'])->name('reports-emi-export');

    // Revenue Report
    Route::get('/reports/revenue', [\App\Http\Controllers\RevenueReportController::class, 'index'])->name('reports-revenue');
    Route::get('/reports/revenue/export', [\App\Http\Controllers\RevenueReportController::class, 'export'])->name('reports-revenue-export');

    //policy pages
    Route::get('/setup-configuration/page-configuration', [PageConfigurationController::class, 'index'])->name('page-configuration');
    Route::get('/setup-configuration/page-configuration/create', [PageConfigurationController::class, 'create'])->name('page-configuration-create');
    Route::post('/setup-configuration/page-configuration/store', [PageConfigurationController::class, 'store'])->name('page-configuration-store');
    Route::get('/setup-configuration/page-configuration/edit/{id}', [PageConfigurationController::class, 'edit'])->name('page-configuration-edit');
    Route::put('/setup-configuration/page-configuration/update/{id}', [PageConfigurationController::class, 'update'])->name('page-configuration-update');
    Route::delete('/setup-configuration/page-configuration/delete/{id}', [PageConfigurationController::class, 'destroy'])->name('page-configuration-delete');

    // loan document templates
    Route::resource('/setup-configuration/loan-document-templates', \App\Http\Controllers\LoanDocumentTemplateController::class, ['names' => 'loan-document-templates']);

    //pages not added
// Route::get('/pages/faq', [Faq::class, 'index'])->name('pages-faq');

    // error pages
    Route::get('/pages/error', [ErrorPageController::class, 'index'])->name('pages-error');
    Route::get('/pages/maintenance', [ErrorPageController::class, 'miscUnderMaintenance'])->name('pages-maintenance');
    Route::get('/pages/comingsoon', [ErrorPageController::class, 'miscComingSoon'])->name('pages-comingsoon');
    Route::get('/pages/notauthorized', [ErrorPageController::class, 'miscNotAuthorized'])->name('pages-notauthorized');
    Route::get('/pages/servererror', [ErrorPageController::class, 'miscServerError'])->name('pages-servererror');

    // roles and permissions
    Route::get('/roles', [RolesPermissionController::class, 'index'])->name('role-users');
    Route::get('/roles/users', [RolesPermissionController::class, 'getUsers'])->name('roles.users');
    Route::get('/roles/menus', [RoleMenuController::class, 'index'])->name('roles.menus');
    Route::post('/roles/menus', [RoleMenuController::class, 'update'])->name('roles.menus.update');
    Route::get('/permission', [RolesPermissionController::class, 'permission'])->name('role-permissions');
    Route::post('/roles/store', [RolesPermissionController::class, 'store'])->name('roles.store');
    Route::post('/roles/update', [RolesPermissionController::class, 'update'])->name('roles.update');
    Route::post('/roles/destroy', [RolesPermissionController::class, 'destroy'])->name('roles.destroy');
    Route::post('/permissions/store', [RolesPermissionController::class, 'storePermission'])->name('permissions.store');
    Route::post('/permissions/destroy', [RolesPermissionController::class, 'destroyPermission'])->name('permissions.destroy');
    Route::get('/permissions/data', [RolesPermissionController::class, 'getPermissionsData'])->name('permissions.data');
    Route::get('/roles/permissions', [RolesPermissionController::class, 'getRolePermissions'])->name('roles.permissions');

    // authentication - handled by auth.php

    // icons
    // Route::get('/icons/icons-ri', [RiIcons::class, 'index'])->name('icons-ri');

    Route::get('loan/loan-types', [LoanTypeController::class, 'index'])->name('loan-types');
    Route::resource('loan/loan-types', LoanTypeController::class);
    Route::post('loan/loan-types/{id}/toggle-status', [LoanTypeController::class, 'toggleStatus'])->name('loan-types-toggle-status');

    // Loan Configuration
    Route::get('loan/loan-configuration', [LoanConfigurationController::class, 'index'])->name('loan-configuration');
    Route::post('loan/loan-configuration/save-foreclosure', [LoanConfigurationController::class, 'saveForeclosureConfig'])->name('loan-configuration.save-foreclosure');
    Route::post('loan/loan-configuration/save-prepayment', [LoanConfigurationController::class, 'savePrepaymentConfig'])->name('loan-configuration.save-prepayment');
    Route::post('loan/loan-configuration/save-partial-payment', [LoanConfigurationController::class, 'savePartialPaymentConfig'])->name('loan-configuration.save-partial-payment');
    Route::post('loan/loan-configuration/save-penalty', [LoanConfigurationController::class, 'savePenaltyConfig'])->name('loan-configuration.save-penalty');
    Route::post('loan/loan-configuration/save-account-prefix', [LoanConfigurationController::class, 'saveAccountPrefixConfig'])->name('loan-configuration.save-account-prefix');
    Route::get('loan/loan-configuration/partial-payment-settings', [LoanConfigurationController::class, 'getPartialPaymentSettings'])->name('loan-configuration.partial-payment-settings');

    // feature activation
    Route::get('/setup-configuration/feature-activation', [SetupConfigurationController::class, 'index'])->name('feature-activation');
    Route::post('/setup-configuration/feature-activation/toggle-maintenance', [SetupConfigurationController::class, 'toggleMaintenanceMode'])->name('feature-activation-toggle-maintenance');

    // FAQ Management
    Route::get('/setup-configuration/faq', [SetupConfigurationController::class, 'faqIndex'])->name('faq-index');
    Route::get('/setup-configuration/faq/create', [SetupConfigurationController::class, 'faqCreate'])->name('faq-create');
    Route::post('/setup-configuration/faq/store', [SetupConfigurationController::class, 'faqStore'])->name('faq-store');
    Route::get('/setup-configuration/faq/edit/{id}', [SetupConfigurationController::class, 'faqEdit'])->name('faq-edit');
    Route::put('/setup-configuration/faq/update/{id}', [SetupConfigurationController::class, 'faqUpdate'])->name('faq-update');
    Route::delete('/setup-configuration/faq/delete/{id}', [SetupConfigurationController::class, 'faqDestroy'])->name('faq-delete');

    // SMTP Settings
    Route::get('/setup-configuration/smtp-settings', [SetupConfigurationController::class, 'smtpSettings'])->name('smtp-settings');
    Route::post('/setup-configuration/smtp-settings/update', [SetupConfigurationController::class, 'updateSmtpSettings'])->name('smtp-settings-update');
    Route::post('/setup-configuration/smtp-settings/test', [SetupConfigurationController::class, 'testSmtpConnection'])->name('smtp-settings-test');

    // Payment Methods
    Route::get('/setup-configuration/payment-methods', [SetupConfigurationController::class, 'paymentMethods'])->name('payment-methods');
    Route::post('/setup-configuration/payment-methods/update', [SetupConfigurationController::class, 'updatePaymentMethods'])->name('payment-methods-update');
    Route::post('/setup-configuration/payment-methods/toggle', [SetupConfigurationController::class, 'togglePaymentMethod'])->name('payment-methods-toggle');

    // API configuration
    Route::get('/setup-configuration/api-configuration', [SetupConfigurationController::class, 'apiConfiguration'])->name('setup-configuration-api-configuration');
    Route::post('/setup-configuration/api-configuration/{service}', [SetupConfigurationController::class, 'saveApiConfiguration'])->name('setup-configuration-api-configuration.save');

    // API Usage — SMS OTP logs + Aadhaar / PAN / Bank hit counts
    Route::get('/management/api-wallet', [\App\Http\Controllers\VerificationApiWalletController::class, 'index'])->name('api-wallet.index');
    Route::get('/management/api-wallet/export', [\App\Http\Controllers\VerificationApiWalletController::class, 'export'])->name('api-wallet.export');

    // Cache clear
    Route::get('/system/cache/clear', [SetupConfigurationController::class, 'fileSystemCache'])->name('system-cache-clear');
    Route::post('/system/cache/clear', [SetupConfigurationController::class, 'clearCache'])->name('cache-clear');

    // S3 File System Configuration
    Route::get('/setup-configuration/s3/config', [SetupConfigurationController::class, 'getS3Config'])->name('s3-config-get');
    Route::post('/setup-configuration/s3/config', [SetupConfigurationController::class, 'updateS3Config'])->name('s3-config-update');
    Route::post('/setup-configuration/s3/toggle', [SetupConfigurationController::class, 'toggleS3Status'])->name('s3-toggle');
    Route::post('/setup-configuration/s3/test', [SetupConfigurationController::class, 'testS3Connection'])->name('s3-test');

    // System Management
    Route::get('/system/server-status', [SystemController::class, 'serverStatus'])->name('system-server-status');
    Route::get('/system/database-backup', [SystemController::class, 'databaseBackup'])->name('system-database-backup');
    Route::post('/system/database-backup/create', [SystemController::class, 'createBackup'])->name('system-backup-create');
    Route::get('/system/database-backup/download/{filename}', [SystemController::class, 'downloadBackup'])->name('system-backup-download');
    Route::delete('/system/database-backup/delete/{filename}', [SystemController::class, 'deleteBackup'])->name('system-backup-delete');
    Route::get('/system/login-log', [SystemController::class, 'loginLog'])->name('system-login-log');
    Route::post('/system/login-log/clear', [SystemController::class, 'clearLoginLog'])->name('system-login-log-clear');
    Route::get('/system/collection-log', [SystemController::class, 'collectionLog'])->name('system-collection-log');
    Route::post('/system/collection-log/clear', [SystemController::class, 'clearCollectionLog'])->name('system-collection-log-clear');
    Route::get('/system/collection-log/export', [SystemController::class, 'exportCollectionLog'])->name('system-collection-log-export');

    // Audit & Logs
    Route::get('/audit-logs/activity-logs', [AuditLogsController::class, 'activityLogs'])->name('audit-logs-activity-logs');
    Route::get('/audit-logs/activity-logs/location/{id}', [AuditLogsController::class, 'getLocationDetails'])->name('audit-logs-location-details');
    Route::get('/audit-logs/activity-logs/view-location/{id}', [AuditLogsController::class, 'viewLocation'])->name('audit-logs-view-location');
    Route::get('/audit-logs/login-logout-history', [AuditLogsController::class, 'loginLogoutHistory'])->name('audit-logs-login-logout-history');

    // App Setup - Slides
    Route::get('/setup-app/slides', [AppSetupController::class, 'slideIndex'])->name('app-setup-slides');
    Route::get('/setup-app/slides/create', [AppSetupController::class, 'slideCreate'])->name('app-setup-slides-create');
    Route::post('/setup-app/slides/store', [AppSetupController::class, 'slideStore'])->name('app-setup-slides-store');
    Route::get('/setup-app/slides/edit/{id}', [AppSetupController::class, 'slideEdit'])->name('app-setup-slides-edit');
    Route::put('/setup-app/slides/update/{id}', [AppSetupController::class, 'slideUpdate'])->name('app-setup-slides-update');
    Route::delete('/setup-app/slides/delete/{id}', [AppSetupController::class, 'slideDestroy'])->name('app-setup-slides-delete');

    Route::get('/setup-app/appearance', [AppSetupController::class, 'appearanceIndex'])->name('app-setup-appearance');
    Route::post('/setup-app/appearance/update', [AppSetupController::class, 'appearanceUpdate'])->name('app-setup-appearance-update');
    Route::get('/setup-app/app-info', [AppSetupController::class, 'appInfoIndex'])->name('app-setup-app-info');
    Route::post('/setup-app/app-info/update', [AppSetupController::class, 'appInfoUpdate'])->name('app-setup-app-info-update');

    // User Management
    Route::get('/user-management', [UserManagementController::class, 'index'])->name('user-management');
    Route::get('/user-management/data', [UserManagementController::class, 'getData'])->name('user-management.data');
    Route::post('/user-management/store', [UserManagementController::class, 'store'])->name('user-management.store');
    Route::post('/user-management/{id}/update', [UserManagementController::class, 'update'])->name('user-management.update');
    Route::post('/user-management/{id}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('user-management.toggle-status');
    Route::post('/user-management/{id}/assign-role', [UserManagementController::class, 'assignRole'])->name('user-management.assign-role');
    Route::delete('/user-management/{id}', [UserManagementController::class, 'destroy'])->name('user-management.destroy');

    // Location Management (Tabbed & Normalized)
    Route::prefix('location-management')->name('location-management.')->group(function () {
        Route::get('/', [LocationController::class, 'index'])->name('index');
        Route::get('/data', [LocationController::class, 'getData'])->name('data');
        Route::post('/store', [LocationController::class, 'store'])->name('store');

        // State CRUD (must come before generic routes)
        Route::get('/states/data', [LocationController::class, 'getStateData'])->name('states.data');
        Route::post('/states/store', [LocationController::class, 'storeState'])->name('states.store');
        Route::post('/states/{id}/update', [LocationController::class, 'updateState'])->name('states.update');
        Route::get('/states/api', [LocationController::class, 'getStatesApi'])->name('states.api');

        // District CRUD (must come before generic routes)
        Route::get('/districts/data', [LocationController::class, 'getDistrictData'])->name('districts.data');
        Route::post('/districts/store', [LocationController::class, 'storeDistrict'])->name('districts.store');
        Route::post('/districts/{id}/update', [LocationController::class, 'updateDistrict'])->name('districts.update');
        Route::get('/districts/local/{stateId}', [LocationController::class, 'getDistrictsLocal'])->name('districts.local');
        Route::post('/districts/api', [LocationController::class, 'getDistricts'])->name('districts.api');

        // Village CRUD
        Route::get('/villages/{id}/data', [LocationController::class, 'getVillageData'])->name('villages.data');
        Route::post('/villages/{id}/update', [LocationController::class, 'updateVillage'])->name('villages.update');

        // External API / Fetch
        Route::post('/fetch', [LocationController::class, 'fetchLocations'])->name('fetch');
        
        // Generic delete route (must come last)
        Route::delete('/{type}/{id}', [LocationController::class, 'destroy'])->name('destroy');
    });

    // Admin Payment Undo / Delete
    Route::post('/emi/payment/{emiId}/undo', [\App\Http\Controllers\EmiController::class, 'undoPayment'])->name('emi.payment.undo');
    Route::delete('/emi/collection/{collectionId}/delete', [\App\Http\Controllers\EmiController::class, 'deleteCollection'])->name('emi.collection.delete');

});

Route::middleware(['auth', 'adminOrStaff'])->group(function () {
    Route::get('/clients/view/account/{id}', [ClientViewAccountController::class, 'index'])->name('client-view-account');
    Route::get('/clients/view/kyc/{id}', [KycVerificationController::class, 'view'])->name('client-view-kyc');
    Route::post('/clients/view/account/{id}/update', [ClientViewAccountController::class, 'update'])->name('client-view-account.update');
    Route::post('/clients/view/account/{id}/update-employment', [ClientViewAccountController::class, 'updateEmployment'])->name('client-view-account.update-employment');
    Route::post('/clients/{id}/blacklist', [ClientViewAccountController::class, 'blacklist'])->name('client-blacklist');
    Route::post('/clients/{id}/unblacklist', [ClientViewAccountController::class, 'unblacklist'])->name('client-unblacklist');
    Route::get('/client/view/loans/{id}', [ClientViewLoansController::class, 'index'])->name('client-view-loans');
    Route::get('/client/view/chits/{id}', [ClientViewChitsController::class, 'index'])->name('client-view-chits');
    Route::get('/clients/view/ledger/{id}', [ClientViewAccountController::class, 'ledger'])->name('client-view-ledger');
    Route::get('/clients/view/notifications/{id}', [ClientViewAccountController::class, 'notifications'])->name('client-view-notifications');
    Route::get('/client/loan/{loanId}/emis', [ClientViewLoansController::class, 'getEmiDetails'])->name('client-loan-emis');
    Route::get('/client/loan/{loanId}/emi-details', [ClientViewLoansController::class, 'emiDetailsPage'])->name('client-loan-emi-details');
    Route::get('/client/emi/{emiId}/history', [ClientViewLoansController::class, 'getEmiHistory'])->name('client-emi-history');
    Route::post('/client/loan/emi/pay', [ClientViewLoansController::class, 'payEmi'])->name('client-loan-emi-pay');
    Route::post('/client/loan/{loanId}/generate-open-cycles', [ClientViewLoansController::class, 'generateOpenLoanCycles'])->name('client-loan.generate-open-cycles');
    Route::get('/client/loan/{loanId}/document/{documentType}/view', [ClientViewLoansController::class, 'viewDocument'])->name('client-loan-document-view');
    Route::get('/client/loan/{loanId}/document/{documentType}/download', [ClientViewLoansController::class, 'downloadDocument'])->name('client-loan-document-download');
    Route::get('/clients/view/notifications', [UserViewNotifications::class, 'index'])->name('client-view-notifications');

    //kyc verification
    Route::get('/verification/kyc-verification', [KycVerificationController::class, 'index'])->name('verification-kyc-verification');
    Route::get('/verification/view/kyc/{id}', [KycVerificationController::class, 'view'])->name('verification-kyc-view');
    Route::post('/verification/view/kyc/{id}/update', [KycVerificationController::class, 'update'])->name('verification-kyc-update');
    Route::post('/verification/view/kyc/{id}/approve', [KycVerificationController::class, 'approve'])->name('verification-kyc-approve');
    Route::post('/verification/view/kyc/{id}/skip', [KycVerificationController::class, 'skipKyc'])->name('verification-kyc-skip');
    Route::post('/verification/view/kyc/{id}/reject', [KycVerificationController::class, 'reject'])->name('verification-kyc-reject');
    Route::post('/verification/view/kyc/{id}/re-kyc', [KycVerificationController::class, 'reKyc'])->name('verification-kyc-rekyc');
    Route::post('/verification/view/kyc/{id}/verify-aadhaar', [KycVerificationController::class, 'verifyAadhaar'])->name('verification-kyc-verify-aadhaar');
    Route::post('/verification/view/kyc/{id}/verify-aadhaar-otp', [KycVerificationController::class, 'verifyAadhaarOtp'])->name('verification-kyc-verify-aadhaar-otp');
    Route::post('/verification/view/kyc/{id}/verify-pan', [KycVerificationController::class, 'verifyPan'])->name('verification-kyc-verify-pan');
    Route::post('/verification/view/kyc/{id}/verify-bank', [KycVerificationController::class, 'verifyBank'])->name('verification-kyc-verify-bank');
    Route::post('/verification/view/kyc/{id}/update-fields', [KycVerificationController::class, 'updateKycFields'])->name('verification-kyc-update-fields');

    //loan products
    Route::get('/loan/loan-products', [LoanProductsController::class, 'index'])->name('loan-products');
    Route::get('/loan/loan-products/data', [LoanProductsController::class, 'getData'])->name('loan-products-data');
    Route::get('/loan/loan-product-view/{id}', [LoanProductsController::class, 'view'])->name('loan-product-view');
    Route::post('/loan/loan-products/store', [LoanProductsController::class, 'store'])->name('loans.store');
    Route::post('/loan/loan-products/{id}/update', [LoanProductsController::class, 'update'])->name('loan-products.update');
    Route::delete('/loan/loan-products/{id}', [LoanProductsController::class, 'destroy'])->name('loan-products.destroy');
    Route::post('/loan/loan-products/{id}/toggle-status', [LoanProductsController::class, 'toggleStatus'])->name('loan-products.toggle-status');

    // Agent Management Unified
    Route::prefix('app/agents')->name('agent-management.')->group(function () {
        Route::get('/', [AgentManagementController::class, 'AgentManagement'])->name('index');
        Route::get('/data', [AgentManagementController::class, 'data'])->name('data');
        Route::post('/store', [AgentManagementController::class, 'store'])->name('store');
        
        // Attendance Features (Separate pages like Staff)
        Route::get('/attendance', [AgentManagementController::class, 'attendance'])->name('attendance');
        Route::post('/mark-attendance', [AgentManagementController::class, 'markAttendance'])->name('markAttendance');
        Route::post('/bulk-mark-attendance', [AgentManagementController::class, 'bulkMarkAttendance'])->name('bulkMarkAttendance');
        Route::get('/export-attendance', [AgentManagementController::class, 'exportAttendance'])->name('exportAttendance');
        Route::get('/export-attendance-pdf', [AgentManagementController::class, 'exportAttendancePDF'])->name('exportAttendancePDF');
        Route::get('/print-attendance', [AgentManagementController::class, 'printAttendanceReport'])->name('printAttendance');

        // Detailed views
        Route::get('/view/{id}', [AgentManagementController::class, 'view'])->name('view');
        Route::get('/view/{id}/work', [AgentManagementController::class, 'viewWork'])->name('view-work');
        Route::get('/view/{id}/work/clients', [AgentManagementController::class, 'getAssignedClientsData'])->name('get-assigned-clients');
        Route::get('/view/{id}/work/client/{clientId}', [AgentManagementController::class, 'viewAssignedClient'])->name('view-assigned-client');
        Route::get('/view/{id}/visits', [AgentManagementController::class, 'viewVisits'])->name('view-visits');
        Route::get('/view/{id}/visits/data', [AgentManagementController::class, 'getVisitData'])->name('visits.data');
        Route::get('/visits/{visit_id}', [AgentManagementController::class, 'viewVisitDetails'])->name('visit-details');
        
        Route::post('/add-expense', [AgentManagementController::class, 'addExpense'])->name('addExpense');
        Route::post('/add-advance', [AgentManagementController::class, 'addAdvance'])->name('addAdvance');
        
        // Generic /{id} routes (must come last to avoid matching "attendance")
        Route::post('/{id}/update', [AgentManagementController::class, 'updateAccount'])->name('update-account');
        Route::delete('/{id}', [AgentManagementController::class, 'destroy'])->name('destroy');
    });

    // Fallback for legacy Agent Attendance URL - now points to clean attendance route
    Route::get('/app/agents/agent-attendance', function() {
        return redirect()->route('agent-management.attendance');
    });

    Route::get('/clients/{id}/info', [AgentManagementController::class, 'getClientInfo'])->name('client.info');


    // Agent Assignments
    Route::get('/app/agents/assignments', [\App\Http\Controllers\AgentAssignmentController::class, 'index'])->name('agent-assignments.index');
    Route::get('/app/agents/assignments/list', [\App\Http\Controllers\AgentAssignmentController::class, 'list'])->name('agent-assignments.list');

    // Agent Collections & Dashboard
    Route::get('/app/agents/dashboard', [AgentDashboardController::class, 'index'])->name('agent-dashboard');
    Route::get('/app/agents/agent-collections', [AgentCollectionController::class, 'index'])->name('agent-collections');
    Route::get('/app/agents/agent-collections/stats', [AgentCollectionController::class, 'stats'])->name('agent-collections.stats');
    Route::get('/app/agents/agent-collections/list', [AgentCollectionController::class, 'list'])->name('agent-collections.list');
    Route::get('/app/agents/agent-collections/chit-list', [AgentCollectionController::class, 'chitList'])->name('agent-collections.chit-list');
    Route::get('/app/agents/agent-collections/search-emis', [AgentCollectionController::class, 'searchEmis'])->name('agent-collections.search-emis');
    Route::get('/app/agents/agent-collections/assigned-dues', [AgentCollectionController::class, 'assignedDues'])->name('agent-collections.assigned-dues');
    Route::get('/app/agents/agent-collections/get-emi-info/{id}', [AgentCollectionController::class, 'getEmiInfo'])->name('agent-collections.get-emi-info');
    Route::get('/app/agents/agent-collections/partial-payment-rules/{id}', [AgentCollectionController::class, 'partialPaymentRules'])->name('agent-collections.partial-payment-rules');
    Route::post('/app/agents/agent-collections/assign', [AgentCollectionController::class, 'assign'])->name('agent-collections.assign');
    Route::post('/app/agents/agent-collections/bulk-store', [AgentCollectionController::class, 'bulkStore'])->name('agent-collections.bulk-store');
    Route::post('/app/agents/agent-collections', [AgentCollectionController::class, 'store'])->name('agent-collections.store');
    Route::get('/app/agents/agent-collections/{id}/history', [AgentCollectionController::class, 'getHistory'])->name('agent-collections.history');
    Route::get('/app/agents/agent-collections/{id}', [AgentCollectionController::class, 'show'])->name('agent-collections.show');
    Route::post('/app/agents/agent-collections/verify-one', [AgentCollectionController::class, 'verify'])->name('agent-collections.verify-one');
    Route::post('/app/agents/agent-collections/{id}/verify', [AgentCollectionController::class, 'verify'])->name('agent-collections.verify');
    Route::post('/app/agents/agent-collections/{id}/repay', [AgentCollectionController::class, 'repay'])->name('agent-collections.repay');
    Route::post('/app/agents/agent-collections/bulk-verify', [AgentCollectionController::class, 'bulkVerify'])->name('agent-collections.bulk-verify');





    Route::post('/loan/loan-applications/{application}/approve', [LoanApplicationsController::class, 'approve'])->name('loan-applications.approve');
    Route::post('/loan/loan-applications/{application}/reject', [LoanApplicationsController::class, 'reject'])->name('loan-applications.reject');
    Route::post('/loan/loan-applications/{application}/disburse', [LoanApplicationsController::class, 'disburse'])->name('loan-applications.disburse');
    Route::post('/loan/loan-applications/{application}/admin-proceed', [LoanApplicationsController::class, 'adminProceed'])->name('loan-applications.admin-proceed');
    Route::delete('/loan/loan-applications/{application}', [LoanApplicationsController::class, 'destroy'])->name('loan-applications.destroy');

    //loan accounts
    Route::get('/loan/loan-accounts', [\App\Http\Controllers\LoanAccountsController::class, 'index'])->name('loan-accounts');
    Route::get('/loan/loan-accounts/data', [\App\Http\Controllers\LoanAccountsController::class, 'data'])->name('loan-accounts-data');
    Route::get('/loan/loan-account/{id}', [\App\Http\Controllers\LoanAccountsController::class, 'view'])->name('loan-account-view');
    Route::post('/loan/loan-account/{id}/regenerate-documents', [\App\Http\Controllers\LoanAccountsController::class, 'regenerateDocuments'])->name('loan-account-regenerate-documents');



    // EMI Calculator
    Route::get('/emi/emi-calculator', [\App\Http\Controllers\EmiCalculatorController::class, 'index'])->name('emi-calculator');
    Route::post('/emi/calculate', [\App\Http\Controllers\EmiCalculatorController::class, 'calculate'])->name('emi-calculate');

}); // End of admin group

// Shared routes for Admin, Staff, and Agents
Route::middleware(['auth', 'adminStaffOrAgent'])->group(function () {

    // Admin notifications (header bell + dropdown)
    Route::get('/admin/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('admin-notifications');
    Route::get('/admin/notifications/latest', [\App\Http\Controllers\NotificationController::class, 'getLatest'])->name('admin-notifications.latest');
    Route::get('/admin/notifications/unread-count', [\App\Http\Controllers\NotificationController::class, 'getUnreadCount'])->name('admin-notifications.unread-count');
    Route::post('/admin/notifications/{id}/mark-read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('admin-notifications.mark-read');
    Route::post('/admin/notifications/mark-all-read', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('admin-notifications.mark-all-read');
    Route::delete('/admin/notifications/{id}', [\App\Http\Controllers\NotificationController::class, 'destroy'])->name('admin-notifications.destroy');
    Route::post('/admin/notifications/clear-read', [\App\Http\Controllers\NotificationController::class, 'clearRead'])->name('admin-notifications.clear-read');

    //emi repayments
    Route::get('/emi/repayments', [\App\Http\Controllers\EmiController::class, 'index'])->name('emi-repayments');
    Route::get('/emi/repayments/data', [\App\Http\Controllers\EmiController::class, 'getData'])->name('emi-repayments-data');
    Route::get('/emi/repayments/all-ids', [\App\Http\Controllers\EmiController::class, 'allIds'])->name('emi-repayments-all-ids');
    Route::post('/emi/repayments/bulk-assign', [\App\Http\Controllers\EmiController::class, 'bulkAssignAgent'])->name('emi-repayments-bulk-assign');
    Route::get('/emi/repayments/emi/{emiId}', [\App\Http\Controllers\EmiController::class, 'show'])->name('emi-repayments-show');
    Route::get('/emi/repayments/emi/{emiId}/history', [\App\Http\Controllers\EmiController::class, 'getCollectionHistory'])->name('emi-repayments-history');
    Route::get('/emi/repayments/view/{applicationNumber}', [\App\Http\Controllers\EmiController::class, 'view'])->name('emi-details');

    // Common payment receipts (All / Loan / Chit / FD)
    Route::get('/payment-receipts', [\App\Http\Controllers\PaymentReceiptController::class, 'index'])
        ->defaults('module', 'all')
        ->name('payment-receipts');
    Route::get('/payment-receipts/all', [\App\Http\Controllers\PaymentReceiptController::class, 'index'])
        ->defaults('module', 'all')
        ->name('all-payment-receipts');
    Route::get('/payment-receipts/loan', [\App\Http\Controllers\PaymentReceiptController::class, 'index'])
        ->defaults('module', 'loan')
        ->name('loan-payment-receipts');
    Route::get('/payment-receipts/chit', [\App\Http\Controllers\PaymentReceiptController::class, 'index'])
        ->defaults('module', 'chit')
        ->name('chit-payment-receipts');
    Route::get('/payment-receipts/fd', [\App\Http\Controllers\PaymentReceiptController::class, 'index'])
        ->defaults('module', 'fd')
        ->name('fd-payment-receipts');
    Route::get('/payment-receipts/{module}/data', [\App\Http\Controllers\PaymentReceiptController::class, 'data'])
        ->where('module', 'all|loan|chit|fd')
        ->name('payment-receipts.data');
    Route::get('/payment-receipts/{module}/{id}/print', [\App\Http\Controllers\PaymentReceiptController::class, 'print'])
        ->where('module', 'loan|chit|fd')
        ->name('payment-receipts.print');

    // Combined applications (All / Loan / Chit / FD)
    Route::get('/applications', [\App\Http\Controllers\ApplicationListController::class, 'index'])
        ->defaults('module', 'all')
        ->name('applications');
    Route::get('/applications/all', [\App\Http\Controllers\ApplicationListController::class, 'index'])
        ->defaults('module', 'all')
        ->name('all-applications');
    Route::get('/applications/loan', [\App\Http\Controllers\ApplicationListController::class, 'index'])
        ->defaults('module', 'loan')
        ->name('applications.loan');
    Route::get('/applications/chit', [\App\Http\Controllers\ApplicationListController::class, 'index'])
        ->defaults('module', 'chit')
        ->name('applications.chit');
    Route::get('/applications/fd', [\App\Http\Controllers\ApplicationListController::class, 'index'])
        ->defaults('module', 'fd')
        ->name('applications.fd');
    Route::get('/applications/settlement', [\App\Http\Controllers\ApplicationListController::class, 'index'])
        ->defaults('module', 'settlement')
        ->name('applications.settlement');

    Route::get('/client-collections', [\App\Http\Controllers\ClientCollectionsController::class, 'index'])
        ->name('client-collections');
    Route::get('/client-collections/data', [\App\Http\Controllers\ClientCollectionsController::class, 'data'])
        ->name('client-collections.data');
    Route::post('/client-collections/pay', [\App\Http\Controllers\ClientCollectionsController::class, 'pay'])
        ->name('client-collections.pay');
    Route::post('/client-collections/bulk-pay', [\App\Http\Controllers\ClientCollectionsController::class, 'bulkPay'])
        ->name('client-collections.bulk-pay');


    // Legacy loan receipt URLs
    Route::get('/emi/receipts', [\App\Http\Controllers\EmiController::class, 'receiptsIndex']);
    Route::get('/emi/receipts/data', [\App\Http\Controllers\EmiController::class, 'getReceiptsData'])->name('payment-receipts-data');
    Route::get('/emi/receipts/pending-emis', [\App\Http\Controllers\EmiController::class, 'getPendingEmis'])->name('pending-emis');
    Route::post('/emi/receipts/create', [\App\Http\Controllers\EmiController::class, 'createReceipt'])->name('create-receipt');
    Route::post('/emi/receipts/pay-selected', [\App\Http\Controllers\EmiController::class, 'paySelectedEmis'])->name('emi.receipts.pay-selected');
    Route::get('/emi/receipts/view/{id}', [\App\Http\Controllers\EmiController::class, 'printReceipt'])->name('view-receipt');
    Route::get('/emi/receipts/print/{id}', [\App\Http\Controllers\EmiController::class, 'printReceipt'])->name('print-receipt');
    Route::get('/emi/statement/print/{id}', [\App\Http\Controllers\EmiController::class, 'printStatement'])->name('print-statement');
    Route::post('/emi/partial-payment', [\App\Http\Controllers\EmiController::class, 'processPartialPayment'])->name('emi.partial-payment');
    Route::post('/emi/loan/{id}/generate-open-cycles', [ClientViewLoansController::class, 'generateOpenLoanCycles'])->name('emi.generate-open-cycles');
    Route::get('/emi/{emi_id}/partial-payment-rules', [\App\Http\Controllers\LoanAccountsController::class, 'emiPartialMinAmount'])->name('emi.partial-payment-rules');
    Route::post('/emi/repayments/bulk-pay', [\App\Http\Controllers\EmiController::class, 'bulkPay'])->name('emi-repayments-bulk-pay');
    Route::post('/emi/repayments/bulk-undo', [\App\Http\Controllers\EmiController::class, 'bulkUndo'])->name('emi-repayments-bulk-undo');
    Route::get('/emi/loan-closing/{id}', [\App\Http\Controllers\EmiController::class, 'getLoanClosingDetails'])->name('emi.loan-closing.details');
    Route::post('/emi/loan-closing/{id}', [\App\Http\Controllers\EmiController::class, 'settleAndCloseLoan'])->name('emi.loan-closing.settle');

    // support tickets
    Route::get('/support/tickets', [\App\Http\Controllers\SupportTicketController::class, 'index'])->name('support-tickets');
    Route::get('/support/tickets/data', [\App\Http\Controllers\SupportTicketController::class, 'getData'])->name('support-tickets-data');
    Route::get('/support/tickets/users', [\App\Http\Controllers\SupportTicketController::class, 'getUsers'])->name('support-tickets.users');
    Route::get('/support/tickets/{id}', [\App\Http\Controllers\SupportTicketController::class, 'show'])->name('support-tickets.show');
    Route::post('/support/tickets', [\App\Http\Controllers\SupportTicketController::class, 'store'])->name('support-tickets.store');
    Route::post('/support/tickets/{id}/status', [\App\Http\Controllers\SupportTicketController::class, 'updateStatus'])->name('support-tickets.update-status');
    Route::post('/support/tickets/{id}/reply', [\App\Http\Controllers\SupportTicketController::class, 'addReply'])->name('support-tickets.reply');

    // account deletion requests (loan app)
    Route::get('/admin/account-deletion', [\App\Http\Controllers\AccountDeletionRequestController::class, 'index'])->name('admin-account-deletion');
    Route::get('/admin/account-deletion/data', [\App\Http\Controllers\AccountDeletionRequestController::class, 'getData'])->name('admin-account-deletion.data');
    Route::get('/admin/account-deletion/{id}', [\App\Http\Controllers\AccountDeletionRequestController::class, 'show'])->name('admin-account-deletion.show');
    Route::post('/admin/account-deletion/{id}/status', [\App\Http\Controllers\AccountDeletionRequestController::class, 'updateStatus'])->name('admin-account-deletion.update-status');
    Route::delete('/admin/account-deletion/{id}', [\App\Http\Controllers\AccountDeletionRequestController::class, 'destroy'])->name('admin-account-deletion.destroy');

    // client management
    Route::get('/client-management', [\App\Http\Controllers\ClientManagementController::class, 'ClientManagement'])->name('client-management');
    Route::get('/client-management/add', [\App\Http\Controllers\ClientManagementController::class, 'create'])->name('client-management-add');
    Route::post('/client-management/store', [\App\Http\Controllers\ClientManagementController::class, 'store'])->name('client-management-store');
    Route::post('/client-management/check-duplicate', [\App\Http\Controllers\ClientManagementController::class, 'checkDuplicate'])->name('client-management-check-duplicate');
    Route::post('/client-management/verify-aadhaar', [\App\Http\Controllers\ClientManagementController::class, 'verifyAadhaar'])->name('client-management-verify-aadhaar');
    Route::post('/client-management/verify-aadhaar-otp', [\App\Http\Controllers\ClientManagementController::class, 'verifyAadhaarOtp'])->name('client-management-verify-aadhaar-otp');
    Route::post('/client-management/resend-aadhaar-otp', [\App\Http\Controllers\ClientManagementController::class, 'resendAadhaarOtp'])->name('client-management-resend-aadhaar-otp');
    Route::post('/client-management/verify-pan', [\App\Http\Controllers\ClientManagementController::class, 'verifyPan'])->name('client-management-verify-pan');
    Route::post('/client-management/verify-bank', [\App\Http\Controllers\ClientManagementController::class, 'verifyBank'])->name('client-management-verify-bank');
    Route::get('/client-management/download-template', [\App\Http\Controllers\ClientManagementController::class, 'downloadTemplate'])->name('client-management-download-template');
    Route::post('/client-management/bulk-import', [\App\Http\Controllers\ClientManagementController::class, 'bulkImport'])->name('client-management-bulk-import');
    Route::resource('/client-list', \App\Http\Controllers\ClientManagementController::class);
    Route::post('/client-management/bulk-assign', [\App\Http\Controllers\ClientManagementController::class, 'bulkAssignAgent'])->name('client-bulk-assign');
    Route::post('/client-management/bulk-assign-zone', [\App\Http\Controllers\ClientManagementController::class, 'bulkAssignZone'])->name('client-bulk-assign-zone');
    Route::post('/client-management/bulk-delete', [\App\Http\Controllers\ClientManagementController::class, 'bulkDelete'])->name('client-bulk-delete');
    Route::get('/client-management/recycle-bin', [\App\Http\Controllers\ClientManagementController::class, 'recycleBin'])->name('client-management-recycle-bin');
    Route::post('/client-management/{id}/restore', [\App\Http\Controllers\ClientManagementController::class, 'restore'])->name('client-management-restore');
    Route::delete('/client-management/{id}/force-delete', [\App\Http\Controllers\ClientManagementController::class, 'forceDelete'])->name('client-management-force-delete');
    Route::post('/client-management/{id}/toggle-status', [\App\Http\Controllers\ClientManagementController::class, 'toggleStatus'])->name('client-toggle-status');
    Route::get('/client-management/{client}/penalty-accounts', [\App\Http\Controllers\ClientManagementController::class, 'penaltyAccounts'])->name('client-penalty-accounts');
    Route::post('/client-management/penalty/apply-loan', [\App\Http\Controllers\ClientManagementController::class, 'applyLoanPenalty'])->name('client-penalty-apply-loan');
    Route::post('/client-management/penalty/apply-chit', [\App\Http\Controllers\ClientManagementController::class, 'applyChitPenalty'])->name('client-penalty-apply-chit');

    // Loan applications
    Route::get('/loan/loan-applications', [LoanApplicationsController::class, 'index'])->name('loan-applications');
    Route::get('/loan/loan-applications/data', [LoanApplicationsController::class, 'data'])->name('loan-applications.data');
    Route::get('/loan-application', [LoanApplicationsController::class, 'index'])->name('loan-application-index');
    Route::post('/loan-application/quick-apply', [LoanApplicationsController::class, 'storeQuickApplication'])->name('loan-application-quick-apply');
    Route::post('/loan-application/check-eligibility', [LoanApplicationsController::class, 'checkLoanEligibility'])->name('loan-application-check-eligibility');
    Route::post('/loan-application/preview-emi', [LoanApplicationsController::class, 'previewEmi'])->name('loan-application-preview-emi');
    Route::get('/loan-application/view/{application}', [LoanApplicationsController::class, 'view'])->name('loan-application-view');

}); // End of adminOrStaff group

Route::middleware(['auth', 'admin'])->group(function () {
    // Staff Management
    Route::prefix('staff')->name('admin.staff.')->group(function () {
        Route::get('/', [\App\Http\Controllers\StaffManagementController::class, 'index'])->name('index');
        Route::post('/store', [\App\Http\Controllers\StaffManagementController::class, 'store'])->name('store');
        Route::post('/{id}/update', [\App\Http\Controllers\StaffManagementController::class, 'update'])->name('update');
        Route::delete('/{id}/delete', [\App\Http\Controllers\StaffManagementController::class, 'destroy'])->name('delete');
        Route::get('/attendance', [\App\Http\Controllers\StaffManagementController::class, 'attendance'])->name('attendance');
        Route::get('/attendance-report', [\App\Http\Controllers\StaffManagementController::class, 'attendanceReport'])->name('attendance-report');
        Route::post('/mark-attendance', [\App\Http\Controllers\StaffManagementController::class, 'markAttendance'])->name('markAttendance');
        Route::post('/bulk-mark-attendance', [\App\Http\Controllers\StaffManagementController::class, 'bulkMarkAttendance'])->name('bulkMarkAttendance');
        Route::get('/export-attendance', [\App\Http\Controllers\StaffManagementController::class, 'exportAttendance'])->name('exportAttendance');
        Route::get('/export-attendance-pdf', [\App\Http\Controllers\StaffManagementController::class, 'exportAttendancePDF'])->name('exportAttendancePDF');
        Route::get('/payroll', [\App\Http\Controllers\StaffManagementController::class, 'payroll'])->name('payroll');
        Route::post('/add-expense', [\App\Http\Controllers\StaffManagementController::class, 'addExpense'])->name('addExpense');
        Route::post('/add-advance', [\App\Http\Controllers\StaffManagementController::class, 'addAdvance'])->name('addAdvance');
        Route::get('/holidays', [\App\Http\Controllers\StaffManagementController::class, 'holidayIndex'])->name('holidays.index');
        Route::post('/holidays/store', [\App\Http\Controllers\StaffManagementController::class, 'storeHoliday'])->name('holidays.store');
        Route::post('/holidays/{id}/update', [\App\Http\Controllers\StaffManagementController::class, 'updateHoliday'])->name('holidays.update');
        Route::delete('/holidays/{id}', [\App\Http\Controllers\StaffManagementController::class, 'deleteHoliday'])->name('holidays.delete');

        // Branch Management
        Route::post('/branches/store', [\App\Http\Controllers\StaffManagementController::class, 'storeBranch'])->name('branches.store');
        Route::post('/branches/{id}/update', [\App\Http\Controllers\StaffManagementController::class, 'updateBranch'])->name('branches.update');
        Route::delete('/branches/{id}', [\App\Http\Controllers\StaffManagementController::class, 'deleteBranch'])->name('branches.delete');
    });

    /**
     * Staff Management Submenu Routes (thin wrappers)
     * These provide bookmarkable URLs that map to tab/subtab state inside admin.staff.index.
     */
    Route::prefix('staff-management')->name('staff-management.')->group(function () {
        Route::get('/directory', function (\Illuminate\Http\Request $request) {
            return redirect()->route('admin.staff.index', array_filter([
                'tab' => $request->query('tab', 'staff'),
                '_ts' => $request->query('_ts'),
            ]));
        })->name('directory');

        Route::get('/attendance/daily', function (\Illuminate\Http\Request $request) {
            return redirect()->route('admin.staff.index', array_filter([
                'tab' => 'attendance',
                'subtab' => 'att-daily',
                'date' => $request->query('date'),
                '_ts' => $request->query('_ts'),
            ]));
        })->name('attendance.daily');

        Route::get('/attendance/report', function (\Illuminate\Http\Request $request) {
            return redirect()->route('admin.staff.index', array_filter([
                'tab' => 'attendance',
                'subtab' => 'att-report',
                'month' => $request->query('month'),
                'year' => $request->query('year'),
                '_ts' => $request->query('_ts'),
            ]));
        })->name('attendance.report');

        Route::get('/payroll', function (\Illuminate\Http\Request $request) {
            return redirect()->route('admin.staff.index', array_filter([
                'tab' => 'payroll',
                '_ts' => $request->query('_ts'),
            ]));
        })->name('payroll');

        Route::get('/branches', function (\Illuminate\Http\Request $request) {
            return redirect()->route('admin.staff.index', array_filter([
                'tab' => 'branches',
                '_ts' => $request->query('_ts'),
            ]));
        })->name('branches');

        Route::get('/roles', function (\Illuminate\Http\Request $request) {
            return redirect()->route('admin.staff.index', array_filter([
                'tab' => 'roles',
                '_ts' => $request->query('_ts'),
            ]));
        })->name('roles');

        Route::get('/holidays', function (\Illuminate\Http\Request $request) {
            return redirect()->route('admin.staff.index', array_filter([
                'tab' => 'holidays',
                '_ts' => $request->query('_ts'),
            ]));
        })->name('holidays');
    });

    /**
     * Agent Management Submenu Routes (thin wrappers)
     * These provide bookmarkable URLs that map to tab/subtab state inside agent-management.index.
     */
    Route::prefix('agent-management')->name('agent-management.submenu.')->group(function () {
        Route::get('/directory', function (\Illuminate\Http\Request $request) {
            return redirect()->route('agent-management.index', array_filter([
                'tab' => 'agents',
                '_ts' => $request->query('_ts'),
            ]));
        })->name('directory');

        Route::get('/attendance/daily', function (\Illuminate\Http\Request $request) {
            return redirect()->route('agent-management.index', array_filter([
                'tab' => 'attendance',
                'subtab' => 'att-daily',
                'date' => $request->query('date'),
                '_ts' => $request->query('_ts'),
            ]));
        })->name('attendance.daily');

        Route::get('/attendance/report', function (\Illuminate\Http\Request $request) {
            return redirect()->route('agent-management.index', array_filter([
                'tab' => 'attendance',
                'subtab' => 'att-report',
                'month' => $request->query('month'),
                'year' => $request->query('year'),
                '_ts' => $request->query('_ts'),
            ]));
        })->name('attendance.report');

        Route::get('/payroll', function (\Illuminate\Http\Request $request) {
            return redirect()->route('agent-management.index', array_filter([
                'tab' => 'payroll',
                '_ts' => $request->query('_ts'),
            ]));
        })->name('payroll');

        Route::get('/roles', function (\Illuminate\Http\Request $request) {
            return redirect()->route('agent-management.index', array_filter([
                'tab' => 'roles',
                '_ts' => $request->query('_ts'),
            ]));
        })->name('roles');

        Route::get('/holidays', function (\Illuminate\Http\Request $request) {
            return redirect()->route('agent-management.index', array_filter([
                'tab' => 'holidays',
                '_ts' => $request->query('_ts'),
            ]));
        })->name('holidays');
    });

});

// Profile — available to Admin / Staff / Agent (not CIBIL-gated)
Route::middleware(['auth', 'adminOrStaff'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
});

// Credit score / CIBIL — Admin or CreditVerifier only
Route::middleware(['auth', 'credit_access'])->group(function () {
    Route::get('/verification/credit-score-history', [CreditScoreController::class, 'index'])->name('verification-credit-score-history');
    Route::post('/verification/credit-score-history/fetch', [CreditScoreController::class, 'fetch'])->name('verification-credit-score-fetch');
    Route::get('/verification/credit-score-history/{creditScoreHistory}', [CreditScoreController::class, 'show'])->name('verification-credit-score-show');
    Route::delete('/verification/credit-score-history/{creditScoreHistory}', [CreditScoreController::class, 'destroy'])->name('verification-credit-score-destroy');
    Route::get('/verification/credit-score-history/{creditScoreHistory}/pdf', [CreditScoreController::class, 'exportPdf'])->name('verification-credit-score-pdf');
    Route::post('/verification/credit-score-history/{creditScoreHistory}/mail', [CreditScoreController::class, 'sendMail'])->name('verification-credit-score-mail');
    Route::post('/verification/credit-score-history/{creditScoreHistory}/whatsapp', [CreditScoreController::class, 'sendWhatsapp'])->name('verification-credit-score-whatsapp');
});

Route::get('/loan/{loanAccountId}/document/{type}', [App\Http\Controllers\LoanDocumentTemplateController::class, 'generate']);



// Auto Backup Configuration
Route::post('system/database-backup/auto-config/save', function (Illuminate\Http\Request $request) {
    $validated = $request->validate([
        'enabled' => 'required|boolean',
        'frequency' => 'required|in:daily,weekly,monthly'
    ]);

    try {
        App\Helpers\AutoBackupHelper::saveConfig($validated['enabled'], $validated['frequency']);
        return response()->json(['success' => true, 'message' => 'Auto backup configuration saved successfully']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Failed to save configuration'], 500);
    }
})->name('system-backup-auto-config-save');

// Public Policy Pages (accessible without authentication)
Route::get('/privacy-policy', [PageConfigurationController::class, 'show'])->defaults('slug', 'privacy-policy')->name('public.privacy-policy');
Route::get('/terms-and-conditions', [PageConfigurationController::class, 'show'])->defaults('slug', 'terms-and-conditions')->name('public.terms-and-conditions');
Route::get('/page/{slug}', [PageConfigurationController::class, 'show'])->name('public.page');

// Unified Public Client Schedule (Loans + Chits)
Route::get('/view-schedule/{token}', [\App\Http\Controllers\PublicClientScheduleController::class, 'viewSchedule'])->name('public.view-schedule');
Route::get('/view-chit-schedule/{token}', [\App\Http\Controllers\PublicClientScheduleController::class, 'viewSchedule'])->name('public.view-chit-schedule');
Route::get('/view-client-schedule/{token}', [\App\Http\Controllers\PublicClientScheduleController::class, 'viewSchedule'])->name('public.view-client-schedule');

// Public Credit Check & KYC
Route::prefix('credit-check')->name('public.credit-check.')->group(function () {
    Route::post('/send-otp', [\App\Http\Controllers\PublicCreditCheckController::class, 'sendOtp'])->name('send-otp');
    Route::post('/verify', [\App\Http\Controllers\PublicCreditCheckController::class, 'verifyAndFetch'])->name('verify');
    Route::get('/report/{creditScoreHistory}', [\App\Http\Controllers\PublicCreditCheckController::class, 'downloadReport'])->name('report');
});

// Account Deletion (Play Store / loan app compliance)
Route::get('/account-deletion', [\App\Http\Controllers\PublicAccountDeletionController::class, 'show'])->name('public.account-deletion');
Route::post('/account-deletion', [\App\Http\Controllers\PublicAccountDeletionController::class, 'store'])->name('public.account-deletion.store');

// CLIENT PORTAL ROUTES
Route::prefix('client')->name('client.')->group(function () {
    // Public OTP Routes (Moved out of guest to allow testing while logged in as admin)
    Route::get('/login', [App\Http\Controllers\Auth\ClientAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [App\Http\Controllers\Auth\ClientAuthController::class, 'login'])->name('login.post');
    Route::post('/send-otp', [App\Http\Controllers\Auth\ClientAuthController::class, 'sendOtp'])->name('send-otp');
    Route::post('/verify-otp', [App\Http\Controllers\Auth\ClientAuthController::class, 'verifyOtp'])->name('verify-otp');

    // Authenticated Client Routes
    Route::middleware(['auth'])->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\ClientDashboardController::class, 'index'])->name('dashboard');
        Route::get('/loan/{id}', [App\Http\Controllers\ClientDashboardController::class, 'loanView'])->name('loan-view');
        Route::get('/profile', [App\Http\Controllers\ClientDashboardController::class, 'profile'])->name('profile');
        Route::post('/logout', [App\Http\Controllers\Auth\ClientAuthController::class, 'logout'])->name('logout');
    });
});

// ─── Chit Fund Management ────────────────────────────────────────────────────
Route::middleware(['auth', 'adminOrStaff'])->prefix('admin/chit')->name('chit.')->group(function () {

    // Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\ChitDashboardController::class, 'index'])->name('dashboard');

    // Reports — loan-style analytics pages first so they are not captured by {report}
    Route::get('/reports/applications', [\App\Http\Controllers\ModuleReportAnalyticsController::class, 'chitApplications'])->name('reports.applications');
    Route::get('/reports/applications/export', [\App\Http\Controllers\ModuleReportAnalyticsController::class, 'chitApplications'])->name('reports.applications.export');
    Route::get('/reports/accounts-analytics', [\App\Http\Controllers\ModuleReportAnalyticsController::class, 'chitAccounts'])->name('reports.accounts');
    Route::get('/reports/accounts-analytics/export', [\App\Http\Controllers\ModuleReportAnalyticsController::class, 'chitAccounts'])->name('reports.accounts.export');
    Route::get('/reports/installments-analytics', [\App\Http\Controllers\ModuleReportAnalyticsController::class, 'chitInstallments'])->name('reports.installments-analytics');
    Route::get('/reports/installments-analytics/export', [\App\Http\Controllers\ModuleReportAnalyticsController::class, 'chitInstallments'])->name('reports.installments-analytics.export');
    Route::get('/reports/payments', [\App\Http\Controllers\ModuleReportAnalyticsController::class, 'chitPayments'])->name('reports.payments');
    Route::get('/reports/payments/export', [\App\Http\Controllers\ModuleReportAnalyticsController::class, 'chitPayments'])->name('reports.payments.export');
    Route::get('/reports', [\App\Http\Controllers\ChitReportsController::class, 'index'])->name('reports.index');
    Route::get('/reports/{report}', [\App\Http\Controllers\ChitReportsController::class, 'show'])->name('reports.show');
    Route::get('/reports/{report}/export', [\App\Http\Controllers\ChitReportsController::class, 'export'])->name('reports.export');

    // Chit Applications
    Route::get('/applications',              [\App\Http\Controllers\ChitApplicationController::class, 'index'])->name('applications.index');
    Route::get('/applications/data',         [\App\Http\Controllers\ChitApplicationController::class, 'data'])->name('applications.data');
    Route::post('/applications/apply',       [\App\Http\Controllers\ChitApplicationController::class, 'apply'])->name('applications.apply');
    Route::get('/applications/{member}',         [\App\Http\Controllers\ChitApplicationController::class, 'show'])->name('applications.show');
    Route::post('/applications/{member}/approve', [\App\Http\Controllers\ChitApplicationController::class, 'approve'])->name('applications.approve');
    Route::post('/applications/{member}/reject',  [\App\Http\Controllers\ChitApplicationController::class, 'reject'])->name('applications.reject');

    // Chit Accounts
    Route::get('/accounts',                    [\App\Http\Controllers\ChitAccountsController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/data',               [\App\Http\Controllers\ChitAccountsController::class, 'data'])->name('accounts.data');
    Route::get('/accounts/{member}',           [\App\Http\Controllers\ChitAccountsController::class, 'view'])->name('accounts.show');

    // Schemes
    Route::get('/schemes',                    [\App\Http\Controllers\ChitSchemeController::class,      'index'])  ->name('schemes.index');
    Route::get('/schemes/create',             [\App\Http\Controllers\ChitSchemeController::class,      'create']) ->name('schemes.create');
    Route::post('/schemes',                   [\App\Http\Controllers\ChitSchemeController::class,      'store'])  ->name('schemes.store');
    Route::post('/schemes/payout-template/pdf', [\App\Http\Controllers\ChitSchemeController::class,    'payoutTemplatePdf'])->name('schemes.payout-template-pdf');
    Route::get('/schemes/{scheme}',           [\App\Http\Controllers\ChitSchemeController::class,      'show'])   ->name('schemes.show');
    Route::get('/schemes/{scheme}/flyer',     [\App\Http\Controllers\ChitSchemeController::class,      'flyer'])  ->name('schemes.flyer');
    Route::get('/schemes/{scheme}/edit',      [\App\Http\Controllers\ChitSchemeController::class,      'edit'])   ->name('schemes.edit');
    Route::put('/schemes/{scheme}',           [\App\Http\Controllers\ChitSchemeController::class,      'update']) ->name('schemes.update');
    Route::delete('/schemes/{scheme}',        [\App\Http\Controllers\ChitSchemeController::class,      'destroy'])->name('schemes.destroy');
    Route::patch('/schemes/{scheme}/toggle-status', [\App\Http\Controllers\ChitSchemeController::class, 'toggleStatus'])->name('schemes.toggle-status');
    Route::post('/schemes/{id}/restore',      [\App\Http\Controllers\ChitSchemeController::class,      'restore'])->name('schemes.restore');
    Route::delete('/schemes/{id}/force-delete', [\App\Http\Controllers\ChitSchemeController::class,    'forceDelete'])->name('schemes.force-delete');

    // Groups
    Route::get('/groups',                     [\App\Http\Controllers\ChitGroupController::class,       'index'])  ->name('groups.index');
    Route::get('/groups/create',              [\App\Http\Controllers\ChitGroupController::class,       'create']) ->name('groups.create');
    Route::post('/groups',                    [\App\Http\Controllers\ChitGroupController::class,       'store'])  ->name('groups.store');
    Route::get('/groups/{group}',             [\App\Http\Controllers\ChitGroupController::class,       'show'])   ->name('groups.show');
    Route::get('/groups/{group}/edit',        [\App\Http\Controllers\ChitGroupController::class,       'edit'])   ->name('groups.edit');
    Route::put('/groups/{group}',             [\App\Http\Controllers\ChitGroupController::class,       'update']) ->name('groups.update');
    Route::post('/groups/{group}/activate',   [\App\Http\Controllers\ChitGroupController::class,       'activate'])->name('groups.activate');
    Route::post('/groups/{group}/close',      [\App\Http\Controllers\ChitGroupController::class,       'close'])   ->name('groups.close');
    Route::post('/groups/{group}/reopen',     [\App\Http\Controllers\ChitGroupController::class,       'reopen'])  ->name('groups.reopen');
    Route::post('/groups/{group}/cancel-termination', [\App\Http\Controllers\ChitGroupController::class, 'cancelTermination'])->name('groups.cancel-termination');
    Route::delete('/groups/{group}',          [\App\Http\Controllers\ChitGroupController::class,       'destroy'])->name('groups.destroy');
    Route::post('/groups/{id}/restore',       [\App\Http\Controllers\ChitGroupController::class,       'restore'])->name('groups.restore');
    Route::delete('/groups/{id}/force-delete', [\App\Http\Controllers\ChitGroupController::class,      'forceDelete'])->name('groups.force-delete');

    // Members
    Route::get('/members',                    [\App\Http\Controllers\ChitMemberController::class,      'index'])  ->name('members.index');
    Route::get('/members/create',             [\App\Http\Controllers\ChitMemberController::class,      'create']) ->name('members.create');
    Route::post('/members',                   [\App\Http\Controllers\ChitMemberController::class,      'store'])  ->name('members.store');
    Route::get('/members/{member}/edit',      [\App\Http\Controllers\ChitMemberController::class,      'edit'])   ->name('members.edit');
    Route::put('/members/{member}',           [\App\Http\Controllers\ChitMemberController::class,      'update'])->name('members.update');
    Route::post('/members/{member}/approve',  [\App\Http\Controllers\ChitMemberController::class,      'approve'])->name('members.approve');
    Route::post('/members/{member}/reject',   [\App\Http\Controllers\ChitMemberController::class,      'reject']) ->name('members.reject');
    Route::post('/members/{member}/cancel',   [\App\Http\Controllers\ChitMemberController::class,      'cancel']) ->name('members.cancel');
    Route::delete('/members/{member}',        [\App\Http\Controllers\ChitMemberController::class,      'destroy'])->name('members.destroy');

    // Member Transfers
    Route::get('/transfers',                              [\App\Http\Controllers\ChitMemberTransferController::class, 'index'])->name('transfers.index');
    Route::get('/transfers/{transfer}',                   [\App\Http\Controllers\ChitMemberTransferController::class, 'show'])->name('transfers.show');
    Route::post('/transfers/{transfer}/release-outgoing-settlement', [\App\Http\Controllers\ChitMemberTransferController::class, 'releaseOutgoingSettlement'])->name('transfers.release-outgoing-settlement');
    Route::get('/members/{member}/transfer',              [\App\Http\Controllers\ChitMemberTransferController::class, 'create'])->name('members.transfer.create');
    Route::get('/members/{member}/transfer/preview',      [\App\Http\Controllers\ChitMemberTransferController::class, 'preview'])->name('members.transfer.preview');
    Route::post('/members/{member}/transfer',             [\App\Http\Controllers\ChitMemberTransferController::class, 'store'])->name('members.transfer.store');

    // Families
    Route::get('/families',                                      [\App\Http\Controllers\ChitFamilyController::class, 'index'])       ->name('families.index');
    Route::post('/families',                                     [\App\Http\Controllers\ChitFamilyController::class, 'store'])       ->name('families.store');
    Route::get('/families/{family}',                             [\App\Http\Controllers\ChitFamilyController::class, 'show'])        ->name('families.show');
    Route::put('/families/{family}',                             [\App\Http\Controllers\ChitFamilyController::class, 'update'])      ->name('families.update');
    Route::delete('/families/{family}',                          [\App\Http\Controllers\ChitFamilyController::class, 'destroy'])     ->name('families.destroy');
    Route::post('/families/{family}/members',                    [\App\Http\Controllers\ChitFamilyController::class, 'addMember'])   ->name('families.members.store');
    Route::delete('/families/{family}/members/{member}',         [\App\Http\Controllers\ChitFamilyController::class, 'removeMember']) ->name('families.members.destroy');
    Route::post('/families/{family}/bulk-collect',               [\App\Http\Controllers\ChitFamilyController::class, 'bulkCollect'])  ->name('families.bulk-collect');

    // Installments
    Route::get('/installments',                              [\App\Http\Controllers\ChitInstallmentController::class, 'index'])  ->name('installments.index');
    Route::get('/installments/data',                         [\App\Http\Controllers\ChitInstallmentController::class, 'getData'])->name('installments.data');
    Route::get('/installments/client/{client}',              [\App\Http\Controllers\ChitInstallmentController::class, 'clientShow'])->name('installments.client');
    Route::post('/installments/bulk-collect',                [\App\Http\Controllers\ChitInstallmentController::class, 'bulkCollect'])->name('installments.bulk-collect');
    Route::get('/installments/{group}/{month}',              [\App\Http\Controllers\ChitInstallmentController::class, 'show'])   ->name('installments.show')->whereNumber('month');
    Route::post('/installments/{installment}/collect',       [\App\Http\Controllers\ChitInstallmentController::class, 'collect'])->name('installments.collect');
    Route::get('/installments/{installment}/partial-rules',  [\App\Http\Controllers\ChitInstallmentController::class, 'partialPaymentRules'])->name('installments.partial-rules');
    Route::post('/installments/{installment}/undo',          [\App\Http\Controllers\ChitInstallmentController::class, 'undo'])->name('installments.undo');

    // Auctions
    Route::get('/auctions',                           [\App\Http\Controllers\ChitAuctionController::class,  'index'])         ->name('auctions.index');
    Route::get('/auctions/create',                    [\App\Http\Controllers\ChitAuctionController::class,  'create'])        ->name('auctions.create');
    Route::post('/auctions',                          [\App\Http\Controllers\ChitAuctionController::class,  'store'])         ->name('auctions.store');
    Route::get('/auctions/{auction}',                 [\App\Http\Controllers\ChitAuctionController::class,  'show'])          ->name('auctions.show');
    Route::post('/auctions/{auction}/bid',            [\App\Http\Controllers\ChitAuctionController::class,  'bid'])           ->name('auctions.bid');
    Route::post('/auctions/{auction}/declare-winner', [\App\Http\Controllers\ChitAuctionController::class,  'declareWinner']) ->name('auctions.declare-winner');

    // Dividends
    Route::get('/dividends',                       [\App\Http\Controllers\ChitDividendController::class, 'index'])     ->name('dividends.index');
    Route::get('/dividends/pool/{group}',          [\App\Http\Controllers\ChitDividendController::class, 'showPool'])  ->name('dividends.pool');
    Route::get('/dividends/{dividend}',            [\App\Http\Controllers\ChitDividendController::class, 'show'])      ->name('dividends.show');
    Route::post('/dividends/{dividend}/distribute',[\App\Http\Controllers\ChitDividendController::class, 'distribute'])->name('dividends.distribute');

    // Settlements
    Route::get('/settlements',                                      [\App\Http\Controllers\ChitSettlementController::class, 'manage'])  ->name('settlements.manage');
    Route::get('/settlements/history',                              [\App\Http\Controllers\ChitSettlementController::class, 'history']) ->name('settlements.history');
    Route::get('/settlements/export',                               [\App\Http\Controllers\ChitSettlementController::class, 'export'])  ->name('settlements.export');
    Route::post('/settlements/apply',                               [\App\Http\Controllers\ChitSettlementController::class, 'apply'])   ->name('settlements.apply');
    Route::get('/settlements/{group}/{member}/confirm',           [\App\Http\Controllers\ChitSettlementController::class, 'confirm'])  ->name('settlements.confirm');
    Route::post('/settlements/{group}/{member}/process',          [\App\Http\Controllers\ChitSettlementController::class, 'process'])  ->name('settlements.process');
    Route::post('/settlements/{group}/{member}/update-future-installment', [\App\Http\Controllers\ChitSettlementController::class, 'updateFutureInstallment'])->name('settlements.update-future-installment');
    Route::get('/settlements/{payout}/promissory-note',           [\App\Http\Controllers\ChitSettlementController::class, 'promissoryNote'])->name('settlements.promissory-note');
    Route::get('/settlements/{payout}/promissory-note/pdf',       [\App\Http\Controllers\ChitSettlementController::class, 'promissoryNotePdf'])->name('settlements.promissory-note.pdf');
    Route::post('/settlements/{payout}/cancel',                     [\App\Http\Controllers\ChitSettlementController::class, 'cancel'])   ->name('settlements.cancel');

    // Chit Settlement Applications (Dedicated Module)
    Route::get('/settlement-applications',          [\App\Http\Controllers\ChitSettlementApplicationController::class, 'index'])->name('settlement-applications.index');
    Route::get('/settlement-applications/data',     [\App\Http\Controllers\ChitSettlementApplicationController::class, 'data'])->name('settlement-applications.data');
    Route::get('/settlement-applications/{payout}', [\App\Http\Controllers\ChitSettlementApplicationController::class, 'show'])->name('settlement-applications.show');
    Route::post('/settlement-applications/{payout}/update-month', [\App\Http\Controllers\ChitSettlementApplicationController::class, 'updateMonth'])->name('settlement-applications.update-month');

    // Payouts (legacy routes — redirect to settlements)
    Route::get('/payouts',                     [\App\Http\Controllers\ChitPayoutController::class, 'index'])  ->name('payouts.index');
    Route::post('/payouts',                    [\App\Http\Controllers\ChitPayoutController::class, 'store'])  ->name('payouts.store');
    Route::get('/payouts/{payout}',            [\App\Http\Controllers\ChitPayoutController::class, 'show'])   ->name('payouts.show');
    Route::post('/payouts/{payout}/process',   [\App\Http\Controllers\ChitPayoutController::class, 'process'])->name('payouts.process');

    // Chit Configuration
    Route::get('/chit-configuration',          [\App\Http\Controllers\ChitConfigurationController::class, 'index'])->name('configuration.index');
    Route::post('/chit-configuration',         [\App\Http\Controllers\ChitConfigurationController::class, 'update'])->name('configuration.update');

    // Referral Bonuses
    Route::get('/referral-bonuses',            [\App\Http\Controllers\ChitReferralBonusController::class, 'index'])->name('referral-bonuses.index');
    Route::get('/referral-bonuses/options',    [\App\Http\Controllers\ChitReferralBonusController::class, 'getOptions'])->name('referral-bonuses.options');
    Route::post('/referral-bonuses',           [\App\Http\Controllers\ChitReferralBonusController::class, 'store'])->name('referral-bonuses.store');
    Route::post('/referral-bonuses/{bonus}/pay', [\App\Http\Controllers\ChitReferralBonusController::class, 'pay'])->name('referral-bonuses.pay');
});
// ─────────────────────────────────────────────────────────────────────────────

// ─── Fixed Deposit Management ────────────────────────────────────────────────
Route::middleware(['auth', 'adminOrStaff'])->prefix('admin/fd')->name('fd.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\FixedDepositDashboardController::class, 'index'])->name('dashboard');

    Route::get('/reports/applications', [\App\Http\Controllers\ModuleReportAnalyticsController::class, 'fdApplications'])->name('reports.applications');
    Route::get('/reports/applications/export', [\App\Http\Controllers\ModuleReportAnalyticsController::class, 'fdApplications'])->name('reports.applications.export');
    Route::get('/reports/deposits-analytics', [\App\Http\Controllers\ModuleReportAnalyticsController::class, 'fdDeposits'])->name('reports.deposits');
    Route::get('/reports/deposits-analytics/export', [\App\Http\Controllers\ModuleReportAnalyticsController::class, 'fdDeposits'])->name('reports.deposits.export');
    Route::get('/reports/payments', [\App\Http\Controllers\ModuleReportAnalyticsController::class, 'fdPayments'])->name('reports.payments');
    Route::get('/reports/payments/export', [\App\Http\Controllers\ModuleReportAnalyticsController::class, 'fdPayments'])->name('reports.payments.export');
    Route::get('/reports', [\App\Http\Controllers\FixedDepositReportsController::class, 'index'])->name('reports.index');
    Route::get('/reports/{report}', [\App\Http\Controllers\FixedDepositReportsController::class, 'show'])->name('reports.show');
    Route::get('/reports/{report}/export', [\App\Http\Controllers\FixedDepositReportsController::class, 'export'])->name('reports.export');

    Route::get('/schemes', [\App\Http\Controllers\FixedDepositSchemeController::class, 'index'])->name('schemes.index');
    Route::get('/schemes/create', [\App\Http\Controllers\FixedDepositSchemeController::class, 'create'])->name('schemes.create');
    Route::post('/schemes', [\App\Http\Controllers\FixedDepositSchemeController::class, 'store'])->name('schemes.store');
    Route::get('/schemes/{scheme}', [\App\Http\Controllers\FixedDepositSchemeController::class, 'show'])->name('schemes.show');
    Route::get('/schemes/{scheme}/edit', [\App\Http\Controllers\FixedDepositSchemeController::class, 'edit'])->name('schemes.edit');
    Route::put('/schemes/{scheme}', [\App\Http\Controllers\FixedDepositSchemeController::class, 'update'])->name('schemes.update');
    Route::delete('/schemes/{scheme}', [\App\Http\Controllers\FixedDepositSchemeController::class, 'destroy'])->name('schemes.destroy');

    Route::get('/applications', [FdApplicationsController::class, 'index'])->name('applications.index');
    Route::get('/applications/data', [FdApplicationsController::class, 'data'])->name('applications.data');
    Route::post('/applications', [FdApplicationsController::class, 'store'])->name('applications.store');
    Route::get('/applications/{application}', [FdApplicationsController::class, 'show'])->name('applications.show')->whereNumber('application');
    Route::post('/applications/{application}/approve', [FdApplicationsController::class, 'approve'])->name('applications.approve')->whereNumber('application');
    Route::post('/applications/{application}/reject', [FdApplicationsController::class, 'reject'])->name('applications.reject')->whereNumber('application');
    Route::post('/applications/{application}/book', [FdApplicationsController::class, 'book'])->name('applications.book')->whereNumber('application');

    Route::get('/deposits/calculate', [\App\Http\Controllers\FixedDepositController::class, 'calculate'])->name('deposits.calculate');
    Route::get('/deposits', [\App\Http\Controllers\FixedDepositController::class, 'index'])->name('deposits.index');
    Route::get('/deposits/create', [\App\Http\Controllers\FixedDepositController::class, 'create'])->name('deposits.create');
    Route::post('/deposits', [\App\Http\Controllers\FixedDepositController::class, 'store'])->name('deposits.store');
    Route::get('/deposits/{deposit}/edit', [\App\Http\Controllers\FixedDepositController::class, 'edit'])->name('deposits.edit');
    Route::put('/deposits/{deposit}', [\App\Http\Controllers\FixedDepositController::class, 'update'])->name('deposits.update');
    Route::get('/deposits/{deposit}', [\App\Http\Controllers\FixedDepositController::class, 'show'])->name('deposits.show');
    Route::get('/deposits/{deposit}/certificate', [\App\Http\Controllers\FixedDepositController::class, 'certificate'])->name('deposits.certificate');
    Route::get('/deposits/{deposit}/premature-receipt', [\App\Http\Controllers\FixedDepositController::class, 'prematureReceipt'])->name('deposits.premature-receipt');
    Route::post('/deposits/{deposit}/maturity', [\App\Http\Controllers\FixedDepositController::class, 'processMaturity'])->name('deposits.maturity');
    Route::post('/deposits/{deposit}/premature', [\App\Http\Controllers\FixedDepositController::class, 'prematureWithdraw'])->name('deposits.premature');
    Route::post('/deposits/{deposit}/renew', [\App\Http\Controllers\FixedDepositController::class, 'renew'])->name('deposits.renew');
    Route::post('/deposits/{deposit}/close', [\App\Http\Controllers\FixedDepositController::class, 'close'])->name('deposits.close');
    Route::post('/deposits/{deposit}/toggle-auto-renewal', [\App\Http\Controllers\FixedDepositController::class, 'toggleAutoRenewal'])->name('deposits.toggle-auto-renewal');
    Route::delete('/deposits/{deposit}', [\App\Http\Controllers\FixedDepositController::class, 'destroy'])->name('deposits.destroy');

    Route::get('/wallets', [\App\Http\Controllers\CustomerWalletController::class, 'index'])->name('wallets.index');
    Route::get('/wallets/client/{client}/balance', [\App\Http\Controllers\CustomerWalletController::class, 'balance'])->name('wallets.balance');
    Route::post('/wallets/{wallet}/withdraw', [\App\Http\Controllers\CustomerWalletController::class, 'withdraw'])->name('wallets.withdraw');
    Route::get('/wallets/client/{client}', [\App\Http\Controllers\CustomerWalletController::class, 'showByClient'])->name('wallets.client');
    Route::get('/wallets/{wallet}', [\App\Http\Controllers\CustomerWalletController::class, 'show'])->name('wallets.show');
});
// ─────────────────────────────────────────────────────────────────────────────

// Public receipt and statement view routes (for Customer Mobile App view links)
Route::get('/payment-receipts/{module}/{id}', [\App\Http\Controllers\PaymentReceiptController::class, 'print'])
    ->where('module', 'loan|chit|fd')
    ->name('public-payment-receipts.print');
Route::get('/payment-receipts/{module}/{id}/print', [\App\Http\Controllers\PaymentReceiptController::class, 'print'])
    ->where('module', 'loan|chit|fd')
    ->name('payment-receipts.print');
Route::get('/chit/receipt/{id}', function ($id) {
    return app(\App\Http\Controllers\PaymentReceiptController::class)->print('chit', $id);
});
Route::get('/emi/receipt/{id}', [\App\Http\Controllers\EmiController::class, 'printReceipt'])->name('public-view-receipt');
Route::get('/loan/statement/{id}', [\App\Http\Controllers\EmiController::class, 'printStatement'])->name('public-print-statement');

// Backward-compatibility: legacy /public/ prefixed routes
Route::get('/public/payment-receipts/{module}/{id}', [\App\Http\Controllers\PaymentReceiptController::class, 'print'])
    ->where('module', 'loan|chit|fd')
    ->name('public-payment-receipts.print.legacy');
Route::get('/public/payment-receipts/{module}/{id}/print', [\App\Http\Controllers\PaymentReceiptController::class, 'print'])
    ->where('module', 'loan|chit|fd');
Route::get('/public/chit/receipt/{id}', function ($id) {
    return app(\App\Http\Controllers\PaymentReceiptController::class)->print('chit', $id);
});
Route::get('/public/emi/receipt/{id}', [\App\Http\Controllers\EmiController::class, 'printReceipt'])->name('public-view-receipt.legacy');
Route::get('/public/loan/statement/{id}', [\App\Http\Controllers\EmiController::class, 'printStatement'])->name('public-print-statement.legacy');
Route::get('/schemes/{scheme}/flyer.pdf', [\App\Http\Controllers\ChitSchemeController::class, 'flyerPdf'])
    ->name('public.schemes.flyer.pdf');
Route::get('/schemes/{scheme}/flyer', [\App\Http\Controllers\ChitSchemeController::class, 'flyer'])
    ->name('public.schemes.flyer');
Route::get('/public/schemes/{scheme}/flyer.pdf', [\App\Http\Controllers\ChitSchemeController::class, 'flyerPdf'])
    ->name('public.schemes.flyer.pdf.legacy-public');
Route::get('/public/schemes/{scheme}/flyer', [\App\Http\Controllers\ChitSchemeController::class, 'flyer'])
    ->name('public.schemes.flyer.legacy-public');

// Accounting module (integrated from ERPSoftware / WorkDo Account — see app/Modules/Account)
require __DIR__ . '/account.php';

// authentication - handled by auth.php
require __DIR__ . '/auth.php';

// Card to Cash module
require __DIR__ . '/card_cash.php';

