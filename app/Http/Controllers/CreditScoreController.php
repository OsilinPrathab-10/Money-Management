<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CreditScoreHistory;
use App\Services\CibilApiService;
use App\Services\GallaboxService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreditScoreController extends Controller
{
    public function index()
    {
        $histories = CreditScoreHistory::with(['client'])
            ->orderByDesc('created_at')
            ->paginate(15);

        $clients = Client::orderBy('client_name')
            ->select('id', 'client_name', 'client_phone', 'client_email', 'cibil_score')
            ->limit(500)
            ->get();

        $cibilConfigured = \App\Models\ApiConfiguration::where('service', 'cibil')
            ->where('is_enabled', true)
            ->whereNotNull('credentials')
            ->exists();

        $stats = [
            'total_checks' => CreditScoreHistory::query()->count(),
            'success_checks' => CreditScoreHistory::query()->where('status', 'success')->count(),
            'last_score' => CreditScoreHistory::query()->whereNotNull('score')->latest('created_at')->value('score'),
        ];

        return view('admin.verification.credit-score-history', compact('histories', 'clients', 'cibilConfigured', 'stats'));
    }

    /**
     * JSON detail for modal / API (CIBIL history row).
     */
    public function show(CreditScoreHistory $creditScoreHistory)
    {
        $this->authorizeHistory($creditScoreHistory);

        $creditScoreHistory->load(['client', 'creator']);

        return response()->json([
            'success' => true,
            'history' => [
                'id' => $creditScoreHistory->id,
                'applicant_name' => $creditScoreHistory->applicant_name,
                'pan_number' => $creditScoreHistory->pan_number,
                'aadhar_number' => $creditScoreHistory->aadhar_number,
                'email' => $creditScoreHistory->email,
                'phone' => $creditScoreHistory->phone,
                'date_of_birth' => $creditScoreHistory->date_of_birth?->format('Y-m-d'),
                'score' => $creditScoreHistory->score,
                'rating' => $creditScoreHistory->rating,
                'status' => $creditScoreHistory->status,
                'error_message' => $creditScoreHistory->error_message,
                'report_json' => $creditScoreHistory->report_json,
                'client_name' => $creditScoreHistory->client?->client_name,
                'client_id' => $creditScoreHistory->client_id,
                'created_at' => $creditScoreHistory->created_at?->toIso8601String(),
                'created_by_name' => $creditScoreHistory->creator?->name,
            ],
        ]);
    }

    /**
     * Remove a CIBIL / credit check history row.
     */
    public function destroy(CreditScoreHistory $creditScoreHistory)
    {
        $this->authorizeHistory($creditScoreHistory);

        $creditScoreHistory->delete();

        return response()->json([
            'success' => true,
            'message' => __('Credit check record deleted.'),
        ]);
    }

    public function fetch(Request $request, CibilApiService $cibilApi)
    {
        $request->merge([
            'client_id' => $request->filled('client_id') ? $request->input('client_id') : null,
        ]);

        $data = $request->validate([
            'client_id' => 'nullable|integer|exists:clients,id',
            'applicant_name' => 'required|string|max:255',
            'pan_number' => 'nullable|string|max:20',
            'aadhar_number' => 'nullable|string|max:12',
            'email' => 'nullable|email|max:128',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
        ]);

        $input = [
            'client_id' => ! empty($data['client_id']) ? (int) $data['client_id'] : null,
            'applicant_name' => $data['applicant_name'],
            'pan_number' => $data['pan_number'] ?? null,
            'aadhar_number' => $data['aadhar_number'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
        ];

        $result = $cibilApi->fetchReport($input);

        $history = CreditScoreHistory::create([
            'client_id' => $input['client_id'],
            'applicant_name' => $input['applicant_name'],
            'pan_number' => $input['pan_number'],
            'aadhar_number' => $input['aadhar_number'],
            'email' => $input['email'],
            'phone' => $input['phone'],
            'date_of_birth' => $input['date_of_birth'] ? \Carbon\Carbon::parse($input['date_of_birth']) : null,
            'score' => $result['score'],
            'rating' => $result['rating'],
            'report_json' => $result['report'],
            'status' => $result['status'],
            'error_message' => $result['error_message'],
            'created_by' => Auth::id(),
        ]);

        if ($input['client_id'] && $result['score'] !== null) {
            Client::where('id', $input['client_id'])->update(['cibil_score' => (string) $result['score']]);
        }

        return response()->json([
            'success' => true,
            'history' => $history->load('client'),
            'is_demo' => $result['is_demo'] ?? false,
            'message' => ($result['is_demo'] ?? false)
                ? __('Credit bureau returned demo/sample data. Configure CIBIL API under Setup → API Configuration.')
                : __('Credit report retrieved.'),
        ]);
    }

    public function exportPdf(CreditScoreHistory $creditScoreHistory)
    {
        $this->authorizeHistory($creditScoreHistory);

        $pdf = Pdf::loadView('admin.verification.credit-score-pdf', [
            'row' => $creditScoreHistory,
        ])->setPaper('a4', 'portrait')
          ->setOptions([
              'isHtml5ParserEnabled' => true,
              'isRemoteEnabled' => true,
              'defaultFont' => 'sans-serif',
              'tempDir' => storage_path('app/public'),
              'chroot'  => [
                  base_path(),
                  public_path(),
                  storage_path('app/public')
              ],
          ]);

        $filename = 'cibil-report-' . $creditScoreHistory->id . '-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    public function sendMail(Request $request, CreditScoreHistory $creditScoreHistory)
    {
        $this->authorizeHistory($creditScoreHistory);

        $data = $request->validate([
            'email' => 'required|email',
        ]);

        $pdf = Pdf::loadView('admin.verification.credit-score-pdf', [
            'row' => $creditScoreHistory,
        ])->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'sans-serif',
            'tempDir' => storage_path('app/public'),
            'chroot'  => [
                base_path(),
                public_path(),
                storage_path('app/public')
            ],
        ])->output();

        try {
            Mail::send('emails.credit-score-report', [
                'row' => $creditScoreHistory,
            ], function ($message) use ($data, $creditScoreHistory, $pdf) {
                $message->to($data['email'])
                    ->subject(__('CIBIL / credit report — :name', ['name' => $creditScoreHistory->applicant_name]));
                $message->attachData($pdf, 'cibil-report.pdf', ['mime' => 'application/pdf']);
            });

            return response()->json(['success' => true, 'message' => __('Report sent by email.')]);
        } catch (\Throwable $e) {
            Log::error('Credit score mail failed', ['e' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function sendWhatsapp(Request $request, CreditScoreHistory $creditScoreHistory, GallaboxService $gallabox)
    {
        $this->authorizeHistory($creditScoreHistory);

        $data = $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $phone = $data['phone'];
        $score = $creditScoreHistory->score ?? '—';
        $rating = $creditScoreHistory->rating ?? '—';
        $name = $creditScoreHistory->applicant_name;
        $appUrl = config('app.url');
        $pdfLink = route('verification-credit-score-pdf', ['creditScoreHistory' => $creditScoreHistory->id], true);

        $body = "*CIBIL / Credit report*\n"
            . "Name: {$name}\n"
            . "Score: {$score}\n"
            . "Rating: {$rating}\n"
            . "Details: {$pdfLink}\n"
            . ($appUrl ? "\n_" . __('Generated from') . ' ' . $appUrl . '_' : '');

        $out = $gallabox->sendTextMessage($phone, $name, $body);

        if (isset($out['error']) && $out['error'] !== null && $out['error'] !== '') {
            return response()->json(['success' => false, 'message' => is_string($out['error']) ? $out['error'] : json_encode($out['error'])], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('WhatsApp message queued/sent (check Gallabox dashboard if needed).'),
            'response' => $out,
        ]);
    }

    protected function authorizeHistory(CreditScoreHistory $creditScoreHistory): void
    {
        if (! Auth::check()) {
            abort(403);
        }
    }
}
