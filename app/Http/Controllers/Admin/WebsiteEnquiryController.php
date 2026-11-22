<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebsiteContactEnquiry;
use App\Services\WebsiteClientConversionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebsiteEnquiryController extends Controller
{
    public function show(int $id): View
    {
        $enquiry = WebsiteContactEnquiry::query()
            ->with(['handler', 'client.user'])
            ->findOrFail($id);

        return view('admin.website.enquiries.show', compact('enquiry'));
    }

    public function update(Request $request, int $id)
    {
        $enquiry = WebsiteContactEnquiry::findOrFail($id);

        $data = $request->validate([
            'status' => 'required|in:new,in_progress,closed',
            'admin_notes' => 'nullable|string|max:5000',
            'mark_called' => 'nullable|boolean',
        ]);

        $enquiry->fill([
            'status' => $data['status'],
            'admin_notes' => $data['admin_notes'] ?? $enquiry->admin_notes,
        ]);

        if ($request->boolean('mark_called')) {
            $enquiry->called_at = now();
            $enquiry->handled_by = auth()->id();
        }

        $enquiry->save();

        return back()->with('success', __('Enquiry updated.'));
    }

    public function convertToClient(int $id, WebsiteClientConversionService $conversion)
    {
        $enquiry = WebsiteContactEnquiry::findOrFail($id);

        $result = $conversion->convertToClient(
            $enquiry->name,
            $enquiry->email,
            $enquiry->phone
        );

        $enquiry->client_id = $result['client']->id;
        if ($enquiry->status === 'new') {
            $enquiry->status = 'in_progress';
        }
        $enquiry->save();

        $message = $result['created']
            ? __('Client profile created and linked. Portal login: email on file, default password is the mobile number.')
            : __('Existing client linked to this enquiry.');

        return back()->with('success', $message);
    }
}