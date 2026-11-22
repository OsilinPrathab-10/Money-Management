<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\WebsiteContactEnquiry;
use App\Services\WebsiteService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(WebsiteService $website): View
    {
        return view('website.contact', ['brand' => $website->brand()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'email' => 'nullable|email|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        WebsiteContactEnquiry::create([
            'reference' => 'ENQ' . strtoupper(Str::random(8)),
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'],
            'subject' => $data['subject'] ?? 'Website enquiry',
            'message' => $data['message'],
            'status' => 'new',
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', __('Thank you! Our team will contact you shortly.'));
    }
}
