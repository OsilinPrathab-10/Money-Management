<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationControllerApi extends Controller
{
    public function __construct(
        protected LoanControllerApi $loanController,
        protected FdControllerApi $fdController
    ) {}

    public function loanDropdowns(): JsonResponse
    {
        return $this->loanController->loanDropdowns();
    }

    public function applyLoan(Request $request): JsonResponse
    {
        return $this->loanController->applyLoan($request);
    }

    public function loanApplications(Request $request, $id = null): JsonResponse
    {
        return $this->loanController->loanApplications($request, $id);
    }

    public function fdDropdowns(): JsonResponse
    {
        return $this->fdController->fdDropdowns();
    }

    public function applyFd(Request $request): JsonResponse
    {
        return $this->fdController->applyFd($request);
    }

    public function fdApplications(Request $request, $id = null): JsonResponse
    {
        return $this->fdController->fdApplications($request, $id);
    }
}
