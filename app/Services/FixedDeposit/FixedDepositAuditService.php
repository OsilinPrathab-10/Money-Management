<?php

namespace App\Services\FixedDeposit;

use App\Models\FixedDepositAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class FixedDepositAuditService
{
    public function log(
        string $action,
        ?int $fixedDepositId = null,
        ?int $schemeId = null,
        ?array $previous = null,
        ?array $updated = null,
        ?string $remarks = null
    ): FixedDepositAuditLog {
        $user = Auth::user();

        return FixedDepositAuditLog::create([
            'fixed_deposit_id' => $fixedDepositId,
            'scheme_id' => $schemeId,
            'action' => $action,
            'user_id' => $user?->id,
            'role' => $user?->getRoleNames()?->first(),
            'previous_values' => $previous,
            'updated_values' => $updated,
            'ip_address' => Request::ip(),
            'remarks' => $remarks,
        ]);
    }
}
