<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Account\Expense;

class PostExpense
{
    use Dispatchable;

    public function __construct(
        public Expense $expense
    ) {}
}
