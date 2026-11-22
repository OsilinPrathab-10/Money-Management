<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use App\Models\WebsiteLeadApplication;
use App\Services\WebsiteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function create(Request $request, WebsiteService $website, ?string $productSlug = null): View
    {
        $product = null;
        if ($productSlug) {
            $product = $website->productBySlug($productSlug);
        } elseif ($request->filled('product')) {
            $product = $website->productBySlug((string) $request->get('product'));
        }

        $products = $website->websiteProductsQuery(LoanProduct::query()->with('loanType'))->get();

        return view('website.apply', [
            'brand' => $website->brand(),
            'product' => $product,
            'products' => $products,
        ]);
    }

    public function store(Request $request, WebsiteService $website)
    {
        $data = $request->validate([
            'loan_product_id' => 'required|exists:loan_products,id',
            'applicant_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'dob' => 'nullable|date',
            'gender' => 'nullable|string|in:male,female,other',
            'pan_number' => 'nullable|string|max:16',
            'aadhaar_number' => ['nullable', 'regex:/^\d{4}\s?\d{4}\s?\d{4}$/'],
            'address' => 'nullable|string|max:500',
            'pincode' => ['nullable', 'regex:/^\d{6}$/'],
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:120',

            // Loan request
            'requested_amount' => 'required|integer|min:1000',
            'requested_tenure_months' => 'required|integer|min:1|max:360',

            // Employment
            'employment_type' => 'nullable|string|max:64',
            'monthly_income' => 'nullable|integer|min:0',

            // Bank Details
            'bank_name' => 'nullable|string|max:150',
            'bank_account_holder' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:30',
            'bank_ifsc' => ['nullable', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/i'],
            'bank_account_type' => 'nullable|string|in:savings,current',

            // Nominee Details
            'nominee_name' => 'nullable|string|max:255',
            'nominee_relation' => 'nullable|string|max:64',
            'nominee_phone' => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'nominee_age' => 'nullable|integer|min:1|max:120',

            'message' => 'nullable|string|max:2000',
            'consent' => 'accepted',
        ]);

        $product = LoanProduct::findOrFail($data['loan_product_id']);
        abort_unless($product->online_application_enabled !== false && $product->show_on_website, 422);

        $clientId = Auth::check() && Auth::user()->client ? Auth::user()->client->id : null;

        // Clean formatted values
        if (!empty($data['aadhaar_number'])) {
            $data['aadhaar_number'] = str_replace(' ', '', $data['aadhaar_number']);
        }
        if (!empty($data['bank_ifsc'])) {
            $data['bank_ifsc'] = strtoupper($data['bank_ifsc']);
        }
        if (!empty($data['pan_number'])) {
            $data['pan_number'] = strtoupper($data['pan_number']);
        }

        $lead = WebsiteLeadApplication::create([
            'application_ref' => 'SF' . now()->format('ymd') . strtoupper(Str::random(6)),
            'loan_product_id' => $product->id,
            'client_id' => $clientId,
            'applicant_name' => $data['applicant_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'pan_number' => $data['pan_number'] ?? null,
            'requested_amount' => $data['requested_amount'],
            'requested_tenure_months' => $data['requested_tenure_months'],
            'employment_type' => $data['employment_type'] ?? null,
            'monthly_income' => $data['monthly_income'] ?? null,
            'city' => $data['city'] ?? null,
            'message' => $data['message'] ?? null,
            'form_data' => $data,
            'ip_address' => $request->ip(),
        ]);

        return redirect()
            ->route('website.application.track.form')
            ->with('success', __('Application submitted successfully! Your application reference number is :ref', ['ref' => $lead->application_ref]));
    }

    public function trackForm(WebsiteService $website): View
    {
        return view('website.track', ['brand' => $website->brand()]);
    }

    public function track(Request $request, WebsiteService $website): View
    {
        $data = $request->validate([
            'application_ref' => 'required|string|max:32',
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
        ]);

        $lead = WebsiteLeadApplication::query()
            ->where('application_ref', $data['application_ref'])
            ->where('phone', $data['phone'])
            ->with('product')
            ->first();

        return view('website.track', [
            'brand' => $website->brand(),
            'lead' => $lead,
            'search' => $data,
        ]);
    }
}
