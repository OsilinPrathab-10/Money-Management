<?php

namespace App\Http\Controllers;

use App\Models\SmsOtpLog;
use App\Models\VerificationApiHit;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationApiWalletController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->get('tab', 'sms') === 'verification' ? 'verification' : 'sms';

        if ($tab === 'verification') {
            return $this->verificationTab($request, $tab);
        }

        return $this->smsTab($request, $tab);
    }

    public function export(Request $request)
    {
        $format = strtolower((string) $request->get('format', 'csv'));
        if (! in_array($format, ['csv', 'excel', 'pdf'], true)) {
            $format = 'csv';
        }

        $tab = $request->get('tab', 'sms') === 'verification' ? 'verification' : 'sms';

        if ($tab === 'verification') {
            return $this->exportVerification($request, $format);
        }

        return $this->exportSms($request, $format);
    }

    protected function verificationTab(Request $request, string $tab): View
    {
        [$service, $status, $from, $to] = $this->verificationFilters($request);

        $baseCountsQuery = VerificationApiHit::query();
        if ($from) {
            $baseCountsQuery->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $baseCountsQuery->whereDate('created_at', '<=', $to);
        }

        $counts = [
            'aadhaar' => (clone $baseCountsQuery)->where('service', 'aadhaar')->count(),
            'pan' => (clone $baseCountsQuery)->where('service', 'pan')->count(),
            'bank' => (clone $baseCountsQuery)->where('service', 'bank')->count(),
            'success' => (clone $baseCountsQuery)->where('success', true)->count(),
            'failed' => (clone $baseCountsQuery)->where('success', false)->count(),
            'total' => (clone $baseCountsQuery)->count(),
        ];

        $hits = $this->verificationQuery($request, true)
            ->with('user')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $otpCounts = $this->emptyOtpCounts();
        $logs = collect();
        $purpose = 'all';
        $otpStatus = 'all';
        $mobile = '';

        return view('admin.management.api-wallet.index', compact(
            'tab',
            'hits',
            'counts',
            'service',
            'status',
            'from',
            'to',
            'otpCounts',
            'logs',
            'purpose',
            'otpStatus',
            'mobile'
        ));
    }

    protected function smsTab(Request $request, string $tab): View
    {
        [$purpose, $otpStatus, $mobile, $from, $to] = $this->smsFilters($request);

        $otpCounts = $this->otpCounts($request);
        $logs = $this->smsQuery($request)
            ->with(['user', 'client'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $hits = collect();
        $counts = [
            'aadhaar' => 0,
            'pan' => 0,
            'bank' => 0,
            'success' => 0,
            'failed' => 0,
            'total' => 0,
        ];
        $service = 'all';
        $status = 'all';

        return view('admin.management.api-wallet.index', compact(
            'tab',
            'hits',
            'counts',
            'service',
            'status',
            'from',
            'to',
            'otpCounts',
            'logs',
            'purpose',
            'otpStatus',
            'mobile'
        ));
    }

    protected function resolveDateRange(Request $request): array
    {
        $preset = $request->get('date_preset');
        $from = $request->get('from');
        $to = $request->get('to');

        if ($preset && $preset !== 'custom') {
            $today = Carbon::today();
            if ($preset === 'today') {
                $from = $today->format('Y-m-d');
                $to = $today->format('Y-m-d');
            } elseif (in_array($preset, ['this_week', 'week'], true)) {
                $from = $today->copy()->startOfWeek()->format('Y-m-d');
                $to = $today->copy()->endOfWeek()->format('Y-m-d');
            } elseif (in_array($preset, ['this_month', 'month'], true)) {
                $from = $today->copy()->startOfMonth()->format('Y-m-d');
                $to = $today->copy()->endOfMonth()->format('Y-m-d');
            } elseif (in_array($preset, ['this_year', 'year'], true)) {
                $from = $today->copy()->startOfYear()->format('Y-m-d');
                $to = $today->copy()->endOfYear()->format('Y-m-d');
            } elseif (in_array($preset, ['all', 'all_time'], true)) {
                $from = null;
                $to = null;
            }
        }

        return [$from, $to];
    }

    protected function verificationFilters(Request $request): array
    {
        $service = $request->get('service', 'all');
        $status = $request->get('status', 'all');
        [$from, $to] = $this->resolveDateRange($request);

        return [$service, $status, $from, $to];
    }

    protected function smsFilters(Request $request): array
    {
        $purpose = $request->get('purpose', 'all');
        $otpStatus = $request->get('otp_status', 'all');
        $mobile = trim((string) $request->get('mobile', ''));
        [$from, $to] = $this->resolveDateRange($request);

        return [$purpose, $otpStatus, $mobile, $from, $to];
    }

    protected function verificationQuery(Request $request, bool $applyServiceStatus)
    {
        $query = VerificationApiHit::query();
        [$from, $to] = $this->resolveDateRange($request);

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        if (! $applyServiceStatus) {
            return $query;
        }

        $service = $request->get('service', 'all');
        $status = $request->get('status', 'all');

        if ($service !== 'all' && in_array($service, ['aadhaar', 'pan', 'bank'], true)) {
            $query->where('service', $service);
        }
        if ($status === 'success') {
            $query->where('success', true);
        } elseif ($status === 'failed') {
            $query->where('success', false);
        }

        return $query;
    }

    protected function smsQuery(Request $request, bool $applyPurpose = true)
    {
        $query = SmsOtpLog::query();
        [$purpose, $otpStatus, $mobile, $from, $to] = $this->smsFilters($request);

        if ($applyPurpose && $purpose !== 'all') {
            if (in_array($purpose, ['first_login', 'login'], true)) {
                $query->whereIn('purpose', ['first_login', 'login']);
            } elseif (in_array($purpose, ['forgot_mpin', 'reset_mpin', 'mpin'], true)) {
                $query->whereIn('purpose', ['forgot_mpin', 'reset_mpin', 'mpin']);
            } elseif (in_array($purpose, ['forgot_password', 'reset_password', 'password'], true)) {
                $query->whereIn('purpose', ['forgot_password', 'reset_password', 'password']);
            } else {
                $query->where('purpose', $purpose);
            }
        }
        if (in_array($otpStatus, ['sent', 'failed', 'test'], true)) {
            $query->where('status', $otpStatus);
        }
        if ($mobile !== '') {
            $digits = preg_replace('/\D+/', '', $mobile) ?? $mobile;
            $query->where('mobile', 'like', '%'.$digits.'%');
        }
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    protected function otpCounts(Request $request): array
    {
        $base = SmsOtpLog::query();
        [$purpose, $otpStatus, $mobile, $from, $to] = $this->smsFilters($request);

        if ($mobile !== '') {
            $digits = preg_replace('/\D+/', '', $mobile) ?? $mobile;
            $base->where('mobile', 'like', '%'.$digits.'%');
        }
        if ($from) {
            $base->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $base->whereDate('created_at', '<=', $to);
        }

        return [
            'total' => (clone $base)->count(),
            'first_login' => (clone $base)->whereIn('purpose', ['first_login', 'login'])->count(),
            'forgot_mpin' => (clone $base)->whereIn('purpose', ['forgot_mpin', 'reset_mpin', 'mpin'])->count(),
            'forgot_password' => (clone $base)->whereIn('purpose', ['forgot_password', 'reset_password', 'password'])->count(),
            'sent' => (clone $base)->where('status', SmsOtpLog::STATUS_SENT)->count(),
            'failed' => (clone $base)->where('status', SmsOtpLog::STATUS_FAILED)->count(),
            'test' => (clone $base)->where('status', SmsOtpLog::STATUS_TEST)->count(),
        ];
    }

    protected function emptyOtpCounts(): array
    {
        return [
            'total' => 0,
            'first_login' => 0,
            'forgot_mpin' => 0,
            'forgot_password' => 0,
            'sent' => 0,
            'failed' => 0,
            'test' => 0,
        ];
    }

    protected function exportVerification(Request $request, string $format)
    {
        $rows = $this->verificationQuery($request, true)
            ->with('user')
            ->latest()
            ->limit(5000)
            ->get()
            ->map(fn (VerificationApiHit $hit) => [
                'Date' => $hit->created_at?->format('d-m-Y h:i A'),
                'Service' => strtoupper((string) $hit->service),
                'Endpoint' => $hit->endpoint,
                'Status' => $hit->success ? 'Success' : 'Failed',
                'HTTP' => $hit->http_status,
                'Message' => $hit->response_message,
                'User' => $hit->user?->name ?? 'System',
            ]);

        return $this->downloadExport($rows, 'api_usage_verification', 'API Usage — Verification Hits', $format);
    }

    protected function exportSms(Request $request, string $format)
    {
        $rows = $this->smsQuery($request)
            ->with(['user', 'client'])
            ->latest()
            ->limit(5000)
            ->get()
            ->map(fn (SmsOtpLog $log) => [
                'Date' => $log->created_at?->format('d-m-Y h:i A'),
                'Type' => $log->purposeLabel(),
                'Mobile' => $log->mobile,
                'Status' => ucfirst((string) $log->status),
                'Client' => $log->client?->client_name ?? $log->user?->name ?? '—',
                'Message' => $log->provider_message,
            ]);

        return $this->downloadExport($rows, 'api_usage_sms_otp_logs', 'API Usage — SMS OTP Logs', $format);
    }

    protected function downloadExport($rows, string $filename, string $title, string $format)
    {
        $rows = collect($rows);

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('admin.management.api-wallet.export-pdf', [
                'title' => $title,
                'rows' => $rows,
                'generatedAt' => now(),
            ])->setPaper('A4', 'landscape');

            return $pdf->download($filename.'_'.now()->format('Y-m-d').'.pdf');
        }

        $extension = $format === 'excel' ? 'xls' : 'csv';
        $headers = [
            'Content-Type' => $format === 'excel' ? 'application/vnd.ms-excel' : 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'_'.now()->format('Y-m-d').'.'.$extension.'"',
        ];

        return response()->stream(function () use ($rows) {
            $file = fopen('php://output', 'w');
            if ($rows->isNotEmpty()) {
                fputcsv($file, array_keys($rows->first()));
            } else {
                fputcsv($file, ['Date', 'Type', 'Mobile', 'Status', 'Client', 'Message']);
            }
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        }, 200, $headers);
    }
}
