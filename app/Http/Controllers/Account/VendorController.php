<?php

namespace App\Http\Controllers\Account;

use App\Events\Account\CreateVendor;
use App\Events\Account\DestroyVendor;
use App\Events\Account\UpdateVendor;
use App\Http\Requests\Account\StoreVendorRequest;
use App\Http\Requests\Account\UpdateVendorRequest;
use App\Models\Account\Vendor;
use App\Services\Account\AccountExportService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class VendorController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-vendors')){
            $vendors = Vendor::query()
                ->with('user:id,name,email')
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-vendors')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-vendors')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('company_name'), fn($q) => $q->where('company_name', 'like', '%' . request('company_name') . '%'))
                ->when(request('vendor_code'), fn($q) => $q->where('vendor_code', 'like', '%' . request('vendor_code') . '%'))
                ->when(request('tax_number'), fn($q) => $q->where('tax_number', 'like', '%' . request('tax_number') . '%'))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 20))
                ->withQueryString();

            // ERP linked users to vendors; Loan app users may not have `type` / `mobile_no` — keep empty for safety.
            $users = User::query()->whereRaw('1 = 0')->get();

            return view('admin.account.erp-vendors.index', [
                'vendors' => $vendors,
                'users' => $users,
            ]);
        }
        return back()->with('error', __('Permission denied'));
    }

    public function export(Request $request, AccountExportService $exportService)
    {
        if (! Auth::user()->can('manage-vendors')) {
            abort(403);
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'company_name' => 'nullable|string|max:255',
            'vendor_code' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:255',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
        ]);

        $query = Vendor::query()
            ->with('user:id,name,email')
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-vendors')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-vendors')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when(!empty($validated['company_name']), fn($q) => $q->where('company_name', 'like', '%' . $validated['company_name'] . '%'))
            ->when(!empty($validated['vendor_code']), fn($q) => $q->where('vendor_code', 'like', '%' . $validated['vendor_code'] . '%'))
            ->when(!empty($validated['tax_number']), fn($q) => $q->where('tax_number', 'like', '%' . $validated['tax_number'] . '%'));

        if (!empty($validated['sort'])) {
            $query->orderBy($validated['sort'], $validated['direction'] ?? 'asc');
        } else {
            $query->latest();
        }

        $vendors = $query->get();

        $rows = $vendors->map(function ($v) {
            return [
                'code' => $v->vendor_code,
                'company' => $v->company_name,
                'contact' => $v->contact_person_name ?? '—',
                'email' => $v->contact_person_email ?? $v->primary_email ?? '—',
                'tax' => $v->tax_number ?? '—',
            ];
        })->values()->all();

        $columns = [
            ['key' => 'code', 'label' => __('Code')],
            ['key' => 'company', 'label' => __('Company')],
            ['key' => 'contact', 'label' => __('Contact')],
            ['key' => 'email', 'label' => __('Email')],
            ['key' => 'tax', 'label' => __('Tax #')],
        ];

        $subtitleParts = [];
        if (!empty($validated['company_name'])) {
            $subtitleParts[] = 'Company: ' . $validated['company_name'];
        }
        if (!empty($validated['vendor_code'])) {
            $subtitleParts[] = 'Vendor code: ' . $validated['vendor_code'];
        }
        if (!empty($validated['tax_number'])) {
            $subtitleParts[] = 'Tax: ' . $validated['tax_number'];
        }
        $subtitle = implode(' | ', $subtitleParts);

        return $exportService->exportByFormat(
            $validated['format'],
            'admin.account.exports.generic-table',
            [
                'pageTitle' => __('ERP vendors'),
                'subtitle' => $subtitle ?: null,
                'columns' => $columns,
                'rows' => $rows,
            ],
            'vendors-export'
        );
    }

    public function store(StoreVendorRequest $request)
    {
        if(Auth::user()->can('create-vendors')){
            $validated = $request->validated();

            $vendor = new Vendor();
            $vendor->user_id = $validated['user_id'] ?? null;
            $vendor->company_name = $validated['company_name'];
            $vendor->contact_person_name = $validated['contact_person_name'];
            $vendor->contact_person_email = $validated['contact_person_email'] ?? null;
            $vendor->contact_person_mobile = $validated['contact_person_mobile'] ?? null;
            $vendor->tax_number = $validated['tax_number'] ?? null;
            $vendor->payment_terms = $validated['payment_terms'] ?? null;
            $vendor->billing_address = $validated['billing_address'];
            $vendor->shipping_address = $validated['same_as_billing'] ? $validated['billing_address'] : $validated['shipping_address'];
            $vendor->same_as_billing = $validated['same_as_billing'] ?? false;
            $vendor->notes = $validated['notes'] ?? null;
            $vendor->creator_id = Auth::id();
            $vendor->created_by = creatorId();
            $vendor->save();

            CreateVendor::dispatch($request, $vendor);

            return redirect()->route('account.vendors.index')->with('success', __('The vendor has been created successfully.'));
        }
        return redirect()->route('account.vendors.index')->with('error', __('Permission denied'));
    }

    public function update(UpdateVendorRequest $request, Vendor $vendor)
    {
        if(Auth::user()->can('edit-vendors')){
            $validated = $request->validated();

            $vendor->company_name = $validated['company_name'];
            $vendor->contact_person_name = $validated['contact_person_name'];
            $vendor->contact_person_email = $validated['contact_person_email'] ?? null;
            $vendor->contact_person_mobile = $validated['contact_person_mobile'] ?? null;
            $vendor->tax_number = $validated['tax_number'] ?? null;
            $vendor->payment_terms = $validated['payment_terms'] ?? null;
            $vendor->billing_address = $validated['billing_address'];
            $vendor->shipping_address = $validated['same_as_billing'] ? $validated['billing_address'] : $validated['shipping_address'];
            $vendor->same_as_billing = $validated['same_as_billing'] ?? false;
            $vendor->notes = $validated['notes'] ?? null;
            $vendor->save();

            UpdateVendor::dispatch($request, $vendor);

            return back()->with('success', __('The vendor details are updated successfully.'));
        }
        return back()->with('error', __('Permission denied'));
    }

    public function destroy(Vendor $vendor)
    {
        if(Auth::user()->can('delete-vendors')){
            DestroyVendor::dispatch($vendor);
            $vendor->delete();
            return back()->with('success', __('The vendor has been deleted.'));
        }
        return back()->with('error', __('Permission denied'));
    }
}

