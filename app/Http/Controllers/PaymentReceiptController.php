<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Services\PaymentReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentReceiptController extends Controller
{
    public function __construct(protected PaymentReceiptService $receipts)
    {
    }

    public function index(Request $request, string $module = 'all'): View
    {
        $module = $this->guardModule($module);
        $stats = $this->receipts->stats($module, $request);
        try {
            $rows = $this->receipts->paginateRows($module, $request);
        } catch (\Throwable $e) {
            report($e);
            $rows = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25, 1, [
                'path' => $request->url(),
                'query' => $request->except('page'),
            ]);
        }

        return view('admin.payment-receipts.index', [
            'module' => $module,
            'stats' => $stats,
            'rows' => $rows,
            'locations' => Location::query()->orderBy('name')->get(['id', 'name']),
            'accountHeading' => $this->receipts->accountHeading($module),
            'moduleTitle' => $this->receipts->moduleTitle($module),
        ]);
    }

    public function data(Request $request, string $module): JsonResponse
    {
        $module = $this->guardModule($module);

        return response()->json([
            'data' => $this->receipts->listRows($module, $request),
        ]);
    }

    public function print(string $module, $id)
    {
        $module = strtolower(trim($module));
        if (!in_array($module, PaymentReceiptService::PRINT_MODULES, true)) {
            abort(404);
        }

        try {
            $receiptData = $this->receipts->payload($module, $id, request()->query('bulk'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404, 'Receipt not found.');
        } catch (\Throwable $e) {
            report($e);
            abort(500, 'Unable to generate this payment receipt.');
        }

        return view('pdf.payment_receipt', compact('receiptData'));
    }

    protected function guardModule(string $module): string
    {
        $module = strtolower(trim($module));
        if (!in_array($module, PaymentReceiptService::MODULES, true)) {
            abort(404);
        }

        return $module;
    }
}
