<?php

namespace App\Listeners\Account;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Account\BankTransactionsService;
use App\Services\Account\JournalService;
use Workdo\MobileServiceManagement\Events\UpdateMobileServicePaymentStatus;

class UpdateMobileServicePaymentStatusLis
{
    protected $journalService;
    protected $bankTransactionsService;

    public function __construct(JournalService $journalService, BankTransactionsService $bankTransactionsService)
    {
        $this->journalService = $journalService;
        $this->bankTransactionsService = $bankTransactionsService;
    }

    public function handle(UpdateMobileServicePaymentStatus $event)
    {
        if(Module_is_active('Account'))
        {
            $this->bankTransactionsService->createMobileServicePayment($event->payment);
            $this->journalService->createMobileServicePaymentJournal($event->payment);
        }
    }
}
