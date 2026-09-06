<?php

namespace App\Http\Controllers\Account;

use App\Events\Account\CreateCustomer;
use App\Events\Account\DestroyCustomer;
use App\Events\Account\UpdateCustomer;
use App\Http\Requests\Account\StoreCustomerRequest;
use App\Http\Requests\Account\UpdateCustomerRequest;
use App\Models\Account\Customer;
use App\Services\Account\AccountExportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class CustomerController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-customers')){
            $customers = Customer::query()
                ->with('user:id,name,email')
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-customers')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-customers')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('company_name'), fn($q) => $q->where('company_name', 'like', '%' . request('company_name') . '%'))
                ->when(request('customer_code'), fn($q) => $q->where('customer_code', 'like', '%' . request('customer_code') . '%'))
                ->when(request('tax_number'), fn($q) => $q->where('tax_number', 'like', '%' . request('tax_number') . '%'))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 20))
                ->withQueryString();

            $users = User::query()->whereRaw('1 = 0')->get();

            return view('admin.account.erp-customers.index', [
                'customers' => $customers,
                'users' => $users,
            ]);
        }
        return back()->with('error', __('Permission denied'));
    }

    public function export(Request $request, AccountExportService $exportService)
    {
        if (! Auth::user()->can('manage-customers')) {
            abort(403);
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'company_name' => 'nullable|string|max:255',
            'customer_code' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:255',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
        ]);

        $query = Customer::query()
            ->with('user:id,name,email')
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-customers')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-customers')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when(!empty($validated['company_name']), fn($q) => $q->where('company_name', 'like', '%' . $validated['company_name'] . '%'))
            ->when(!empty($validated['customer_code']), fn($q) => $q->where('customer_code', 'like', '%' . $validated['customer_code'] . '%'))
            ->when(!empty($validated['tax_number']), fn($q) => $q->where('tax_number', 'like', '%' . $validated['tax_number'] . '%'));

        if (!empty($validated['sort'])) {
            $query->orderBy($validated['sort'], $validated['direction'] ?? 'asc');
        } else {
            $query->latest();
        }

        $customers = $query->get();

        $rows = $customers->map(function ($c) {
            return [
                'code' => $c->customer_code,
                'company' => $c->company_name,
                'contact' => $c->contact_person_name ?? '—',
                'email' => $c->contact_person_email ?? '—',
                'tax' => $c->tax_number ?? '—',
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
        if (!empty($validated['customer_code'])) {
            $subtitleParts[] = 'Customer code: ' . $validated['customer_code'];
        }
        if (!empty($validated['tax_number'])) {
            $subtitleParts[] = 'Tax: ' . $validated['tax_number'];
        }

        $subtitle = implode(' | ', $subtitleParts);

        return $exportService->exportByFormat(
            $validated['format'],
            'admin.account.exports.generic-table',
            [
                'pageTitle' => __('ERP customers'),
                'subtitle' => $subtitle ?: null,
                'columns' => $columns,
                'rows' => $rows,
            ],
            'customers-export'
        );
    }

    public function store(StoreCustomerRequest $request)
    {
        if(Auth::user()->can('create-customers')){
            $validated = $request->validated();

            $customer = new Customer();
            $customer->user_id = $validated['user_id'] ?? null;
            $customer->company_name = $validated['company_name'];
            $customer->contact_person_name = $validated['contact_person_name'];
            $customer->contact_person_email = $validated['contact_person_email'] ?? null;
            $customer->contact_person_mobile = $validated['contact_person_mobile'] ?? null;
            $customer->tax_number = $validated['tax_number'] ?? null;
            $customer->payment_terms = $validated['payment_terms'] ?? null;
            $customer->billing_address = $validated['billing_address'];
            $customer->shipping_address = $validated['same_as_billing'] ? $validated['billing_address'] : $validated['shipping_address'];
            $customer->same_as_billing = $validated['same_as_billing'] ?? false;
            $customer->notes = $validated['notes'] ?? null;
            $customer->creator_id = Auth::id();
            $customer->created_by = creatorId();
            $customer->save();

            CreateCustomer::dispatch($request, $customer);

            return redirect()->route('account.customers.index')->with('success', __('The customer has been created successfully.'));
        }
        return redirect()->route('account.customers.index')->with('error', __('Permission denied'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        if(Auth::user()->can('edit-customers')){
            $validated = $request->validated();

            $customer->company_name = $validated['company_name'];
            $customer->contact_person_name = $validated['contact_person_name'];
            $customer->contact_person_email = $validated['contact_person_email'] ?? null;
            $customer->contact_person_mobile = $validated['contact_person_mobile'] ?? null;
            $customer->tax_number = $validated['tax_number'] ?? null;
            $customer->payment_terms = $validated['payment_terms'] ?? null;
            $customer->billing_address = $validated['billing_address'];
            $customer->shipping_address = $validated['same_as_billing'] ? $validated['billing_address'] : $validated['shipping_address'];
            $customer->same_as_billing = $validated['same_as_billing'] ?? false;
            $customer->notes = $validated['notes'] ?? null;
            $customer->save();

            UpdateCustomer::dispatch($request, $customer);

            return back()->with('success', __('The customer details are updated successfully.'));
        }
        return back()->with('error', __('Permission denied'));
    }

    public function destroy(Customer $customer)
    {
        if(Auth::user()->can('delete-customers')){
            DestroyCustomer::dispatch($customer);
            $customer->delete();
            return back()->with('success', __('The customer has been deleted.'));
        }
        return back()->with('error', __('Permission denied'));
    }
}

