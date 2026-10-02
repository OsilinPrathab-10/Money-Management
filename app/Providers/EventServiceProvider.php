<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\LoanApplicationApproved;
use App\Listeners\SendLoanApplicationApprovedNotification;
use App\Events\LoanDisbursement;
use App\Listeners\SendLoanDisbursementNotification;
use App\Events\LoanApplicationRejected;
use App\Listeners\SendLoanApplicationRejectedNotification;
use App\Events\GenerateDocument;
use App\Listeners\GenerateLoanDocuments;
use App\Events\KycApproved;
use App\Listeners\SendKycApprovedNotification;
use App\Events\KycRejected;
use App\Listeners\SendKycRejectedNotification;
use App\Events\WhatsAppCommunicationEvent;
use App\Listeners\SendWhatsAppNotificationListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        LoanApplicationApproved::class => [
            SendLoanApplicationApprovedNotification::class,
        ],
        LoanDisbursement::class => [
            SendLoanDisbursementNotification::class,
        ],
        LoanApplicationRejected::class => [
            SendLoanApplicationRejectedNotification::class,
        ],
        GenerateDocument::class => [
            GenerateLoanDocuments::class,
        ],
        KycApproved::class => [
            SendKycApprovedNotification::class,
        ],
        KycRejected::class => [
            SendKycRejectedNotification::class,
        ],
        \App\Events\EmiOverdueEvent::class => [
            \App\Listeners\SendEmiOverdueNotification::class,
            \App\Listeners\CreateAdminNotificationForEmiOverdue::class,
        ],
        \App\Events\NewLoanApplicationEvent::class => [
            \App\Listeners\CreateAdminNotificationForLoanApplication::class,
        ],
        \App\Events\NewUserRegistrationEvent::class => [
            \App\Listeners\CreateAdminNotificationForUserRegistration::class,
        ],
        \App\Events\PaymentReceivedEvent::class => [
            \App\Listeners\CreateAdminNotificationForPayment::class,
        ],
        \App\Events\NewChitApplicationEvent::class => [
            \App\Listeners\SendNewChitApplicationNotification::class,
        ],
        \App\Events\NewFdApplicationEvent::class => [
            \App\Listeners\SendNewFdApplicationNotification::class,
        ],
        \App\Events\NewSettlementApplicationEvent::class => [
            \App\Listeners\SendNewSettlementApplicationNotification::class,
        ],
        \App\Events\ChitInstallmentCollectedEvent::class => [
            \App\Listeners\SendChitInstallmentCollectedNotification::class,
        ],
        \App\Events\ChitSettlementPaidEvent::class => [
            \App\Listeners\SendChitSettlementPaidNotification::class,
        ],
        \App\Events\ClientAssignedToAgentEvent::class => [
            \App\Listeners\SendClientAssignedNotification::class,
        ],
        \App\Events\ChitApplicationApproved::class => [
            \App\Listeners\SendChitApplicationApprovedNotification::class,
        ],
        \App\Events\ChitApplicationRejected::class => [
            \App\Listeners\SendChitApplicationRejectedNotification::class,
        ],
        \App\Events\FdApplicationApproved::class => [
            \App\Listeners\SendFdApplicationApprovedNotification::class,
        ],
        \App\Events\FdApplicationRejected::class => [
            \App\Listeners\SendFdApplicationRejectedNotification::class,
        ],
        \App\Events\FdApplicationBooked::class => [
            \App\Listeners\SendFdApplicationBookedNotification::class,
        ],
        WhatsAppCommunicationEvent::class => [
            SendWhatsAppNotificationListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }
}
