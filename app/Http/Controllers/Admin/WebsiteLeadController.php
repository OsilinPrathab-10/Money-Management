<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebsiteLeadApplication;
use App\Services\WebsiteClientConversionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebsiteLeadController extends Controller
{
    public function index(Request $request): View
    {
        $query = WebsiteLeadApplication::query()->with(['product.loanType', 'client', 'assignee']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('application_ref', 'like', "%{$search}%")
                    ->orWhere('applicant_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $leads = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('admin.website.leads.index', compact('leads'));
    }

    public function show(int $id): View
    {
        $lead = WebsiteLeadApplication::with(['product.loanType', 'client', 'assignee'])->findOrFail($id);

        return view('admin.website.leads.show', compact('lead'));
    }

    public function update(Request $request, int $id)
    {
        $lead = WebsiteLeadApplication::findOrFail($id);

        $data = $request->validate([
            'status' => 'required|in:new,under_review,documents_pending,verification,approved,rejected,disbursed,closed',
            'admin_notes' => 'nullable|string|max:5000',
            'mark_called' => 'nullable|boolean',
        ]);

        $lead->status = $data['status'];
        $lead->admin_notes = $data['admin_notes'] ?? $lead->admin_notes;
        if ($request->boolean('mark_called')) {
            $lead->called_at = now();
            $lead->assigned_to = auth()->id();
        }
        $lead->save();

        return back()->with('success', __('Application updated.'));
    }

    public function convertToClient(int $id, WebsiteClientConversionService $conversion)
    {
        $lead = WebsiteLeadApplication::findOrFail($id);

        $additionalData = array_merge($lead->form_data ?? [], [
            'pan_number' => $lead->pan_number ?? ($lead->form_data['pan_number'] ?? null),
            'city' => $lead->city ?? ($lead->form_data['city'] ?? null),
        ]);

        $result = $conversion->convertToClient(
            $lead->applicant_name,
            $lead->email,
            $lead->phone,
            $additionalData
        );

        $lead->client_id = $result['client']->id;
        if ($lead->status === 'new') {
            $lead->status = 'under_review';
        }
        $lead->save();

        $message = $result['created']
            ? __('Client profile created and linked. Portal login: email on file, default password is the mobile number.')
            : __('Existing client linked to this application.');

        return back()->with('success', $message);
    }
}
