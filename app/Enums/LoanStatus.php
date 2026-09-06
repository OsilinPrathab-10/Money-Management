<?php

namespace App\Enums;

class LoanStatus
{
    public const PENDING   = 'pending';
    public const APPROVED  = 'approved';
    public const PROCESS   = 'process';
    public const REJECTED  = 'rejected';
    public const DISBURSED = 'disbursed';

    public const ACTIVE = [
        self::PENDING,
        self::APPROVED,
        self::PROCESS,
    ];
}
