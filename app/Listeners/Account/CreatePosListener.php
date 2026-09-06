<?php

namespace App\Listeners\Account;

use App\Models\Account\BankAccount;
use Workdo\Pos\Events\CreatePos;
use App\Services\Account\JournalService;
use App\Services\Account\BankTransactionsService;
use App\Models\Account\ChartOfAccount;

class CreatePosListener
{
    protected $journalService;
    protected $bankTransactionsService;

    public function __construct(JournalService $journalService, BankTransactionsService $bankTransactionsService)
    {
        $this->journalService = $journalService;
        $this->bankTransactionsService = $bankTransactionsService;
    }

    public function handle(CreatePos $event)
    {
        if (Module_is_active('Account')) {

            $bankAccount = BankAccount::where('id', $event->posSale->bank_account_id)
                ->where('created_by', creatorId())
                ->first();

            if ($bankAccount) {
                $this->bankTransactionsService->createPosPayment($event->posSale, $bankAccount->id);
            }

            $this->journalService->createPosJournal($event->posSale);
            $this->journalService->createPosCOGSJournal($event->posSale);
        }
    }
}
