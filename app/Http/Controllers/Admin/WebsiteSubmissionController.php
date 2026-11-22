<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebsiteContactEnquiry;
use App\Models\WebsiteLeadApplication;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class WebsiteSubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->get('type', 'all');
        $status = $request->get('status');
        $subject = $request->get('subject');
        $search = trim((string) $request->get('search'));

        $subjectOptions = WebsiteContactEnquiry::query()
            ->whereNotNull('subject')
            ->distinct()
            ->orderBy('subject')
            ->pluck('subject');

        $enquiries = null;
        $leads = null;
        $submissions = null;

        if ($type === 'contact') {
            $enquiries = $this->contactQuery($status, $subject, $search)
                ->paginate(20)
                ->withQueryString();
        } elseif ($type === 'application') {
            $leads = $this->leadQuery($status, $search)
                ->paginate(20)
                ->withQueryString();
        } else {
            $submissions = $this->mergedSubmissions($status, $subject, $search, $request);
        }

        return view('admin.website.enquiries.index', compact(
            'type',
            'status',
            'subject',
            'search',
            'subjectOptions',
            'enquiries',
            'leads',
            'submissions'
        ) + [
            'contactStatuses' => ['new', 'in_progress', 'closed'],
            'leadStatuses' => ['new', 'under_review', 'documents_pending', 'verification', 'approved', 'rejected', 'disbursed', 'closed'],
        ]);
    }

    protected function contactQuery(?string $status, ?string $subject, string $search)
    {
        return WebsiteContactEnquiry::query()
            ->with(['handler', 'client'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($subject, fn ($q) => $q->where('subject', $subject))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('reference', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at');
    }

    protected function leadQuery(?string $status, string $search)
    {
        return WebsiteLeadApplication::query()
            ->with(['product.loanType', 'client', 'assignee'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('application_ref', 'like', "%{$search}%")
                        ->orWhere('applicant_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at');
    }

    protected function mergedSubmissions(?string $status, ?string $subject, string $search, Request $request): LengthAwarePaginator
    {
        $contactStatuses = ['new', 'in_progress', 'closed'];
        $leadStatuses = ['new', 'under_review', 'documents_pending', 'verification', 'approved', 'rejected', 'disbursed', 'closed'];

        $contacts = $this->contactQuery($status, $subject, $search)->limit(500)->get()->map(function (WebsiteContactEnquiry $e) {
            return (object) [
                'kind' => 'contact',
                'id' => $e->id,
                'ref' => $e->reference,
                'name' => $e->name,
                'phone' => $e->phone,
                'email' => $e->email,
                'category' => $e->subject ?? 'Contact',
                'status' => $e->status,
                'created_at' => $e->created_at,
                'client' => $e->client,
                'pan_number' => null,
                'aadhaar_number' => null,
                'bank_name' => null,
                'bank_account_number' => null,
                'bank_ifsc' => null,
                'nominee_name' => null,
                'requested_amount' => null,
                'form_data' => null,
                'show_url' => route('admin.website.enquiries.show', $e->id),
                'convert_url' => route('admin.website.enquiries.convert-client', $e->id),
            ];
        });

        $apps = $this->leadQuery($status, $search)->limit(500)->get()->map(function (WebsiteLeadApplication $lead) {
            $fd = $lead->form_data ?? [];
            return (object) [
                'kind' => 'application',
                'id' => $lead->id,
                'ref' => $lead->application_ref,
                'name' => $lead->applicant_name,
                'phone' => $lead->phone,
                'email' => $lead->email,
                'category' => $lead->product?->loan_name ?? 'Loan application',
                'status' => $lead->status,
                'created_at' => $lead->created_at,
                'client' => $lead->client,
                'pan_number' => $lead->pan_number ?? ($fd['pan_number'] ?? null),
                'aadhaar_number' => $fd['aadhaar_number'] ?? null,
                'bank_name' => $fd['bank_name'] ?? null,
                'bank_account_holder' => $fd['bank_account_holder'] ?? null,
                'bank_account_number' => $fd['bank_account_number'] ?? null,
                'bank_ifsc' => $fd['bank_ifsc'] ?? null,
                'nominee_name' => $fd['nominee_name'] ?? null,
                'nominee_relation' => $fd['nominee_relation'] ?? null,
                'nominee_phone' => $fd['nominee_phone'] ?? null,
                'requested_amount' => $lead->requested_amount,
                'requested_tenure_months' => $lead->requested_tenure_months,
                'form_data' => $fd,
                'show_url' => route('admin.website.leads.show', $lead->id),
                'convert_url' => route('admin.website.leads.convert-client', $lead->id),
            ];
        });

        $merged = $contacts->concat($apps);
        if ($status !== null && $status !== '') {
            $merged = $merged->filter(fn ($row) => (string) $row->status === (string) $status);
        }
        $merged = $merged->sortByDesc('created_at')->values();
        $page = max(1, (int) $request->get('page', 1));
        $perPage = 20;
        $slice = $merged->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $merged->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }
}