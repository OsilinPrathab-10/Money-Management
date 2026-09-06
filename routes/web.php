<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StorageServeController;
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
use App\Http\Controllers\ClientViewAccountController;
use App\Http\Controllers\ClientViewLoansController;
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

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats'])->name('dashboard.stats');


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

    //Alert
    Route::get('/notifications', [\App\Http\Controllers\AdminBroadCastController::class, 'create'])->name('notifications');
    Route::get('/admin/notification/send', [\App\Http\Controllers\AdminBroadCastController::class, 'create']);
    Route::post('/admin/notification/send', [\App\Http\Controllers\AdminBroadCastController::class, 'send']);

    // Admin Notifications
    Route::get('/admin/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('admin-notifications');
    Route::get('/admin/notifications/latest', [\App\Http\Controllers\NotificationController::class, 'getLatest'])->name('admin-notifications.latest');
    Route::get('/admin/notifications/unread-count', [\App\Http\Controllers\NotificationController::class, 'getUnreadCount'])->name('admin-notifications.unread-count');
    Route::post('/admin/notifications/{id}/mark-read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('admin-notifications.mark-read');
    Route::post('/admin/notifications/mark-all-read', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('admin-notifications.mark-all-read');
    Route::delete('/admin/notifications/{id}', [\App\Http\Controllers\NotificationController::class, 'destroy'])->name('admin-notifications.destroy');
    Route::post('/admin/notifications/clear-read', [\App\Http\Controllers\NotificationController::class, 'clearRead'])->name('admin-notifications.clear-read');

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
    Route::post('/clients/{id}/blacklist', [ClientViewAccountController::class, 'blacklist'])->name('client-blacklist');
    Route::get('/client/view/loans/{id}', [ClientViewLoansController::class, 'index'])->name('client-view-loans');
    Route::get('/client/loan/{loanId}/emis', [ClientViewLoansController::class, 'getEmiDetails'])->name('client-loan-emis');
    Route::get('/client/loan/{loanId}/emi-details', [ClientViewLoansController::class, 'emiDetailsPage'])->name('client-loan-emi-details');
    Route::get('/client/emi/{emiId}/history', [ClientViewLoansController::class, 'getEmiHistory'])->name('client-emi-history');
    Route::post('/client/loan/emi/pay', [ClientViewLoansController::class, 'payEmi'])->name('client-loan-emi-pay');
    Route::get('/client/loan/{loanId}/document/{documentType}/view', [ClientViewLoansController::class, 'viewDocument'])->name('client-loan-document-view');
    Route::get('/client/loan/{loanId}/document/{documentType}/download', [ClientViewLoansController::class, 'downloadDocument'])->name('client-loan-document-download');
    Route::get('/clients/view/notifications', [UserViewNotifications::class, 'index'])->name('client-view-notifications');

    //kyc verification
    Route::get('/verification/kyc-verification', [KycVerificationController::class, 'index'])->name('verification-kyc-verification');
    Route::get('/verification/view/kyc/{id}', [KycVerificationController::class, 'view'])->name('verification-kyc-view');
    Route::post('/verification/view/kyc/{id}/update', [KycVerificationController::class, 'update'])->name('verification-kyc-update');
    Route::post('/verification/view/kyc/{id}/approve', [KycVerificationController::class, 'approve'])->name('verification-kyc-approve');
    Route::post('/verification/view/kyc/{id}/reject', [KycVerificationController::class, 'reject'])->name('verification-kyc-reject');

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
    Route::get('/app/agents/agent-collections/search-emis', [AgentCollectionController::class, 'searchEmis'])->name('agent-collections.search-emis');
    Route::get('/app/agents/agent-collections/get-emi-info/{id}', [AgentCollectionController::class, 'getEmiInfo'])->name('agent-collections.get-emi-info');
    Route::get('/app/agents/agent-collections/partial-payment-rules/{id}', [AgentCollectionController::class, 'partialPaymentRules'])->name('agent-collections.partial-payment-rules');
    Route::post('/app/agents/agent-collections/assign', [AgentCollectionController::class, 'assign'])->name('agent-collections.assign');
    Route::post('/app/agents/agent-collections', [AgentCollectionController::class, 'store'])->name('agent-collections.store');
    Route::get('/app/agents/agent-collections/{id}/history', [AgentCollectionController::class, 'getHistory'])->name('agent-collections.history');
    Route::get('/app/agents/agent-collections/{id}', [AgentCollectionController::class, 'show'])->name('agent-collections.show');
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
    Route::post('/emi/repayments/bulk-pay', [\App\Http\Controllers\EmiController::class, 'bulkPay'])->name('emi-repayments-bulk-pay');
    Route::post('/emi/repayments/bulk-undo', [\App\Http\Controllers\EmiController::class, 'bulkUndo'])->name('emi-repayments-bulk-undo');

}); // End of admin group

// Shared routes for Admin, Staff, and Agents
Route::middleware(['auth', 'adminStaffOrAgent'])->group(function () {

    //emi repayments
    Route::get('/emi/repayments', [\App\Http\Controllers\EmiController::class, 'index'])->name('emi-repayments');
    Route::get('/emi/repayments/data', [\App\Http\Controllers\EmiController::class, 'getData'])->name('emi-repayments-data');
    Route::post('/emi/repayments/bulk-assign', [\App\Http\Controllers\EmiController::class, 'bulkAssignAgent'])->name('emi-repayments-bulk-assign');
    Route::get('/emi/repayments/emi/{emiId}', [\App\Http\Controllers\EmiController::class, 'show'])->name('emi-repayments-show');
    Route::get('/emi/repayments/emi/{emiId}/history', [\App\Http\Controllers\EmiController::class, 'getCollectionHistory'])->name('emi-repayments-history');
    Route::get('/emi/repayments/view/{applicationNumber}', [\App\Http\Controllers\EmiController::class, 'view'])->name('emi-details');

    //payment receipts
    Route::get('/emi/receipts', [\App\Http\Controllers\EmiController::class, 'receiptsIndex'])->name('payment-receipts');
    Route::get('/emi/receipts/data', [\App\Http\Controllers\EmiController::class, 'getReceiptsData'])->name('payment-receipts-data');
    Route::get('/emi/receipts/pending-emis', [\App\Http\Controllers\EmiController::class, 'getPendingEmis'])->name('pending-emis');
    Route::post('/emi/receipts/create', [\App\Http\Controllers\EmiController::class, 'createReceipt'])->name('create-receipt');
    Route::get('/emi/receipts/view/{id}', [\App\Http\Controllers\EmiController::class, 'printReceipt'])->name('view-receipt');
    Route::get('/emi/receipts/print/{id}', [\App\Http\Controllers\EmiController::class, 'printReceipt'])->name('print-receipt');
    Route::get('/emi/statement/print/{id}', [\App\Http\Controllers\EmiController::class, 'printStatement'])->name('print-statement');
    Route::post('/emi/partial-payment', [\App\Http\Controllers\EmiController::class, 'processPartialPayment'])->name('emi.partial-payment');
    Route::get('/emi/{emi_id}/partial-payment-rules', [\App\Http\Controllers\LoanAccountsController::class, 'emiPartialMinAmount'])->name('emi.partial-payment-rules');

    // support tickets
    Route::get('/support/tickets', [\App\Http\Controllers\SupportTicketController::class, 'index'])->name('support-tickets');
    Route::get('/support/tickets/data', [\App\Http\Controllers\SupportTicketController::class, 'getData'])->name('support-tickets-data');
    Route::get('/support/tickets/users', [\App\Http\Controllers\SupportTicketController::class, 'getUsers'])->name('support-tickets.users');
    Route::get('/support/tickets/{id}', [\App\Http\Controllers\SupportTicketController::class, 'show'])->name('support-tickets.show');
    Route::post('/support/tickets', [\App\Http\Controllers\SupportTicketController::class, 'store'])->name('support-tickets.store');
    Route::post('/support/tickets/{id}/status', [\App\Http\Controllers\SupportTicketController::class, 'updateStatus'])->name('support-tickets.update-status');
    Route::post('/support/tickets/{id}/reply', [\App\Http\Controllers\SupportTicketController::class, 'addReply'])->name('support-tickets.reply');

    // client management
    Route::get('/client-management', [\App\Http\Controllers\ClientManagementController::class, 'ClientManagement'])->name('client-management');
    Route::get('/client-management/add', [\App\Http\Controllers\ClientManagementController::class, 'create'])->name('client-management-add');
    Route::post('/client-management/store', [\App\Http\Controllers\ClientManagementController::class, 'store'])->name('client-management-store');
    Route::post('/client-management/check-duplicate', [\App\Http\Controllers\ClientManagementController::class, 'checkDuplicate'])->name('client-management-check-duplicate');
    Route::resource('/client-list', \App\Http\Controllers\ClientManagementController::class);
    Route::post('/client-management/bulk-assign', [\App\Http\Controllers\ClientManagementController::class, 'bulkAssignAgent'])->name('client-bulk-assign');
    Route::post('/client-management/bulk-delete', [\App\Http\Controllers\ClientManagementController::class, 'bulkDelete'])->name('client-bulk-delete');
    Route::post('/client-management/{id}/toggle-status', [\App\Http\Controllers\ClientManagementController::class, 'toggleStatus'])->name('client-toggle-status');

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

// Credit score / CIBIL + profile — Admin or CreditVerifier (see CreditAccessMiddleware)
Route::middleware(['auth', 'credit_access'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

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

// Repayment Schedule Public Link
Route::get('/view-schedule/{token}', [PublicLoanController::class, 'viewSchedule'])->name('public.view-schedule');

// Public Credit Check & KYC
Route::prefix('credit-check')->name('public.credit-check.')->group(function () {
    Route::post('/send-otp', [\App\Http\Controllers\PublicCreditCheckController::class, 'sendOtp'])->name('send-otp');
    Route::post('/verify', [\App\Http\Controllers\PublicCreditCheckController::class, 'verifyAndFetch'])->name('verify');
    Route::get('/report/{creditScoreHistory}', [\App\Http\Controllers\PublicCreditCheckController::class, 'downloadReport'])->name('report');
});

// Account Deletion Page (required for Play Store compliance)
Route::get('/account-deletion', function () {
    return view('public.account-deletion');
})->name('public.account-deletion');

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

// Accounting module (integrated from ERPSoftware / WorkDo Account — see app/Modules/Account)
require __DIR__ . '/account.php';

// authentication - handled by auth.php
require __DIR__ . '/auth.php';
