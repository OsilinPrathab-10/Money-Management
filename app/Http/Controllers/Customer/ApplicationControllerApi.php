<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationControllerApi extends Controller
{
    public function __construct(
        protected LoanControllerApi $loanController,
        protected ChitControllerApi $chitController,
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

    public function chitDropdowns(): JsonResponse
    {
        return $this->chitController->chitDropdowns();
    }

    public function applyChit(Request $request): JsonResponse
    {
        return $this->chitController->applyChit($request);
    }

    public function chitApplications(Request $request, $id = null): JsonResponse
    {
        return $this->chitController->chitApplications($request, $id);
    }

    public function chitAccounts(Request $request, $id = null): JsonResponse
    {
        return $this->chitController->chitAccounts($request, $id);
    }

    public function settlementMetadata(Request $request): JsonResponse
    {
        return $this->chitController->settlementMetadata($request);
    }

    public function settlementPreview(Request $request): JsonResponse
    {
        return $this->chitController->settlementPreview($request);
    }

    public function settlementApplications(Request $request, $id = null): JsonResponse
    {
        return $this->chitController->settlementApplications($request, $id);
    }

    public function applySettlement(Request $request): JsonResponse
    {
        return $this->chitController->applySettlement($request);
    }

    public function familyMembers(Request $request): JsonResponse
    {
        return $this->chitController->familyMembers($request);
    }

    public function addFamilyMember(Request $request): JsonResponse
    {
        return $this->chitController->addFamilyMember($request);
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
