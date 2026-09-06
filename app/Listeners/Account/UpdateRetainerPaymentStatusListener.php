<?php

namespace App\Listeners\Account;

use App\Services\Account\BankTransactionsService;
use Workdo\Retainer\Events\UpdateRetainerPaymentStatus;
use App\Services\Account\JournalService;

class UpdateRetainerPaymentStatusListener
{
    protected $journalService;
    protected $bankTransactionsService;

    public function __construct(JournalService $journalService, BankTransactionsService $bankTransactionsService)
    {
        $this->journalService = $journalService;
        $this->bankTransactionsService = $bankTransactionsService;
    }

    public function handle(UpdateRetainerPaymentStatus $event)
    {
        if (Module_is_active('Account') && $event->request->status === 'cleared') {
            $this->journalService->createRetainerPaymentJournal($event->retainerPayment);
            $this->bankTransactionsService->createRetainerPayment($event->retainerPayment);
        }
    }
}
