<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CreditScoreHistory;
use App\Services\ApiHubCibilService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CibilReportController extends Controller
{
    public function __construct(protected ApiHubCibilService $cibil) {}

    public function index(Request $request): View
    {
        $tab = $request->get('tab') === 'history' ? 'history' : 'new';

        $stats = [
            'total' => CreditScoreHistory::query()->count(),
            'this_month' => CreditScoreHistory::query()->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)->count(),
            'linked' => CreditScoreHistory::query()->whereNotNull('client_id')->count(),
            'pdfs_saved' => CreditScoreHistory::query()->where('pdf_status', 'saved')->count(),
            'avg_score' => (int) round((float) CreditScoreHistory::query()->whereNotNull('score')->avg('score')),
        ];

        $clients = Client::query()
            ->orderBy('client_name')
            ->select('id', 'client_name', 'client_phone', 'gender', 'cibil_score')
            ->with(['kycDetail:id,client_id,pan_number'])
            ->limit(500)
            ->get()
            ->map(function (Client $client) {
                return [
                    'id' => $client->id,
                    'name' => $client->client_name,
                    'phone' => $client->client_phone,
                    'pan' => $client->kycDetail?->pan_number,
                    'gender' => $client->gender,
                ];
            });

        $histories = null;
        if ($tab === 'history') {
            $histories = $this->historyQuery($request)
                ->with(['client:id,client_name,customer_id', 'creator:id,name'])
                ->orderByDesc('created_at')
                ->paginate(15)
                ->withQueryString();
        }

        $config = [
            'mock' => (bool) config('cibil.mock'),
            'path' => config('cibil.path', 'v3/cibil'),
        ];

        return view('admin.cibil-report.index', compact('tab', 'stats', 'clients', 'histories', 'config', 'request'));
    }

    public function store(Request $request)
    {
        $data = $this->validateFetch($request);

        if ($data['client_id']) {
            $this->prefillClientOnHistory($data);
        }

        $result = $this->cibil->fetchReport([
            'pan' => $data['pan'],
            'name' => $data['name'],
            'mobile' => $data['mobile'],
            'gender' => $data['gender'],
        ]);

        $history = CreditScoreHistory::create([
            'client_id' => $data['client_id'],
            'linked_at' => $data['client_id'] ? now() : null,
            'linked_by' => $data['client_id'] ? Auth::id() : null,
            'applicant_name' => $data['name'],
            'pan_number' => $data['pan'],
            'phone' => $data['mobile'],
            'gender' => $data['gender'],
            'email' => $result['email'],
            'score' => $result['score'],
            'rating' => $result['rating'],
            'report_json' => $result['response'],
            'status' => $result['status'],
            'provider' => 'apihub',
            'is_mock' => $result['is_mock'],
            'doc_id' => $result['doc_id'],
            'credit_report_link' => $result['credit_report_link'],
            'link_expires_at' => $result['link_expires_at'],
            'consolidated_pdf_url' => $result['consolidated_pdf_url'],
            'pdf_status' => $result['ok'] && ! $result['is_mock'] ? 'pending' : null,
            'error_message' => $result['error_message'],
            'created_by' => Auth::id(),
        ]);

        if ($result['ok'] && ! $result['is_mock']) {
            $this->trySavePdf($history);
        }

        if ($data['client_id'] && $result['score'] !== null) {
            Client::where('id', $data['client_id'])->update(['cibil_score' => (string) $result['score']]);
        }

        return redirect()
            ->route('cibil-report.show', ['id' => $history->id, 'download' => $result['ok'] && $history->pdf_status === 'saved' ? 1 : 0]);
    }

    public function show(Request $request, int $id): View
    {
        $history = CreditScoreHistory::with(['client', 'creator'])->findOrFail($id);

        if ($history->status === 'success'
            && $history->pdf_status !== 'saved'
            && ! $history->is_mock
            && $history->credit_report_link
            && ($history->link_expires_at === null || $history->link_expires_at->isFuture())
        ) {
            $this->trySavePdf($history->fresh());
            $history->refresh();
        }

        $analysis = is_array($history->report_json)
            ? ($history->report_json['data']['analysis'] ?? [])
            : [];

        $suggestedClients = collect();
        if ($history->status === 'success' && ! $history->client_id) {
            $pan = $history->pan_number;
            $phone = $history->phone;
            $suggestedClients = Client::query()
                ->with('kycDetail:id,client_id,pan_number')
                ->where(function ($q) use ($pan, $phone) {
                    if ($pan) {
                        $q->whereHas('kycDetail', fn ($kq) => $kq->where('pan_number', $pan));
                    }
                    if ($phone) {
                        $q->orWhere('client_phone', 'like', '%' . substr($phone, -10));
                    }
                })
                ->limit(10)
                ->get();
        }

        $allClients = Client::orderBy('client_name')->select('id', 'client_name', 'customer_id')->limit(300)->get();

        return view('admin.cibil-report.show', compact('history', 'analysis', 'suggestedClients', 'allClients', 'request'));
    }

    public function pdf(Request $request, int $id)
    {
        $history = CreditScoreHistory::findOrFail($id);
        abort_unless($history->pdf_status === 'saved' && $history->pdf_path, 404);

        $filename = 'cibil-' . $history->id . '.pdf';
        $path = Storage::disk('local')->path($history->pdf_path);

        if ($request->boolean('inline')) {
            return response()->file($path, [
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]);
        }

        return response()->download($path, $filename);
    }

    public function retryPdf(int $id)
    {
        $history = CreditScoreHistory::findOrFail($id);
        $this->trySavePdf($history);

        return back()->with(
            $history->pdf_status === 'saved' ? 'success' : 'error',
            $history->pdf_status === 'saved'
                ? __('TransUnion PDF saved.')
                : ($history->pdf_error ?? __('Could not download PDF.'))
        );
    }

    public function link(Request $request, int $id)
    {
        $history = CreditScoreHistory::findOrFail($id);
        abort_if($history->status !== 'success', 422, __('Cannot link a failed report.'));

        $data = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
        ]);

        if ($history->client_id && (int) $history->client_id !== (int) $data['client_id']) {
            return back()->with('error', __('This report is already linked to another client.'));
        }

        $history->update([
            'client_id' => $data['client_id'],
            'linked_at' => now(),
            'linked_by' => Auth::id(),
        ]);

        $this->syncClientScore((int) $data['client_id']);

        return back()->with('success', __('Report linked to client.'));
    }

    public function createClient(int $id)
    {
        $history = CreditScoreHistory::findOrFail($id);
        abort_if($history->status !== 'success', 422);

        $analysis = $history->report_json['data']['analysis']['personal_info'] ?? [];
        $raw = $history->report_json['data']['results']['cibil']['data']['credit_report'][0] ?? [];

        session()->put('cibil_report_prefill', [
            'history_id' => $history->id,
            'client_name' => $history->applicant_name,
            'phone' => $history->phone,
            'pan_number' => $history->pan_number,
            'gender' => $history->gender,
            'email' => $history->email ?? ($analysis['email'] ?? null),
            'date_of_birth' => $analysis['dob'] ?? Arr::get($raw, 'names.0.birthDate'),
            'address' => $analysis['address'] ?? null,
        ]);

        return redirect()->route('client-management-add', ['from_cibil' => $history->id]);
    }

    public function destroy(int $id)
    {
        $history = CreditScoreHistory::findOrFail($id);

        if ($history->pdf_path && Storage::disk('local')->exists($history->pdf_path)) {
            Storage::disk('local')->delete($history->pdf_path);
        }

        $clientId = $history->client_id;
        $history->delete();

        if ($clientId) {
            $this->syncClientScore((int) $clientId);
        }

        return redirect()->route('cibil-report.index', ['tab' => 'history'])->with('success', __('Report deleted.'));
    }

    protected function historyQuery(Request $request)
    {
        $query = CreditScoreHistory::query();

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('applicant_name', 'like', '%' . $search . '%')
                    ->orWhere('pan_number', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        if ($request->get('status') === 'success') {
            $query->where('status', 'success');
        } elseif ($request->get('status') === 'failed') {
            $query->where('status', 'failed');
        }

        if ($request->get('linked') === 'linked') {
            $query->whereNotNull('client_id');
        } elseif ($request->get('linked') === 'unlinked') {
            $query->whereNull('client_id');
        }

        [$from, $to] = $this->resolveDatePreset($request);
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    protected function resolveDatePreset(Request $request): array
    {
        $preset = $request->get('date_preset');
        return match ($preset) {
            'today' => [now()->toDateString(), now()->toDateString()],
            'this_week' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'this_month' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'custom' => [
                $request->filled('from') ? Carbon::parse($request->get('from'))->toDateString() : null,
                $request->filled('to') ? Carbon::parse($request->get('to'))->toDateString() : null,
            ],
            default => [null, null],
        };
    }

    protected function validateFetch(Request $request): array
    {
        $request->merge([
            'pan' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('pan', ''))),
            'mobile' => preg_replace('/\D+/', '', (string) $request->input('mobile', '')),
            'client_id' => $request->filled('client_id') ? $request->input('client_id') : null,
        ]);

        $data = $request->validate([
            'client_id' => 'nullable|integer|exists:clients,id',
            'pan' => ['required', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'name' => 'required|string|max:255',
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'gender' => 'required|in:male,female,other',
            'consent' => 'accepted',
        ]);

        return [
            'client_id' => $data['client_id'] ? (int) $data['client_id'] : null,
            'pan' => $data['pan'],
            'name' => $data['name'],
            'mobile' => $data['mobile'],
            'gender' => $data['gender'],
        ];
    }

    protected function prefillClientOnHistory(array &$data): void
    {
        $client = Client::with('kycDetail')->find($data['client_id']);
        if (! $client) {
            return;
        }
        if (empty($data['name'])) {
            $data['name'] = $client->client_name;
        }
        if (empty($data['mobile']) && $client->client_phone) {
            $data['mobile'] = preg_replace('/\D+/', '', $client->client_phone);
        }
        if (empty($data['pan']) && $client->kycDetail?->pan_number) {
            $data['pan'] = strtoupper($client->kycDetail->pan_number);
        }
        if (empty($data['gender']) && $client->gender) {
            $g = strtolower((string) $client->gender);
            $data['gender'] = in_array($g, ['male', 'female', 'other'], true) ? $g : 'other';
        }
    }

    protected function trySavePdf(CreditScoreHistory $history): void
    {
        $out = $this->cibil->downloadTransUnionPdf($history);
        if ($out['saved']) {
            $history->update([
                'pdf_path' => $out['path'],
                'pdf_source' => 'bureau',
                'pdf_status' => 'saved',
                'pdf_saved_at' => now(),
                'pdf_error' => null,
            ]);

            return;
        }

        $history->update([
            'pdf_status' => 'failed',
            'pdf_error' => $out['error'],
        ]);
    }

    protected function syncClientScore(int $clientId): void
    {
        $latest = CreditScoreHistory::query()
            ->where('client_id', $clientId)
            ->where('status', 'success')
            ->whereNotNull('score')
            ->orderByDesc('created_at')
            ->value('score');

        Client::where('id', $clientId)->update(['cibil_score' => $latest !== null ? (string) $latest : null]);
    }
}
