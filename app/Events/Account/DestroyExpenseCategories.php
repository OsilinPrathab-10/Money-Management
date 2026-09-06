<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Account\ExpenseCategories;

class DestroyExpenseCategories
{
    use Dispatchable;

    public function __construct(
        public ExpenseCategories $expenseCategories
    ) {}
}
