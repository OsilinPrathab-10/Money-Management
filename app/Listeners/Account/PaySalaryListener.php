<?php

namespace App\Listeners\Account;

use App\Models\Account\BankAccount;
use Workdo\Hrm\Events\PaySalary;
use App\Services\Account\JournalService;
use App\Services\Account\BankTransactionsService;
use App\Models\Account\ChartOfAccount;

class PaySalaryListener
{
    protected $journalService;
    protected $bankTransactionsService;

    public function __construct(JournalService $journalService, BankTransactionsService $bankTransactionsService)
    {
        $this->journalService = $journalService;
        $this->bankTransactionsService = $bankTransactionsService;
    }

    public function handle(PaySalary $event)
    {
        if (Module_is_active('Account'))
        {
            $this->journalService->createPayrollJournal($event->payrollEntry);
            $this->bankTransactionsService->createPayrollPayment($event->payrollEntry);
        }
    }
}

