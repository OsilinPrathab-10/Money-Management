<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\LoanApplication;

class NewLoanApplicationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $loanApplication;

    public string $source;

    public function __construct(LoanApplication $loanApplication, string $source = 'admin')
    {
        $this->loanApplication = $loanApplication;
        $this->source = $source;
    }
}
