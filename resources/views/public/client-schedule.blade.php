<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $company = \App\Models\CompanyDetail::first();
        $companyName = \App\Helpers\SettingsHelper::get('admin_title', $company->company_name ?? config('app.name', 'Fintronix Chit Funds & Finance'));
    @endphp
    <title>{{ $companyName }} - Public Client Account & Repayment View</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #e0e7ff;
            --primary-dark: #3730a3;
            --chit-purple: #7c3aed;
            --chit-light: #f3e8ff;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --info: #06b6d4;
            --info-light: #ecfeff;
            --dark: #0f172a;
            --slate: #64748b;
            --light: #f8fafc;
            --border: #e2e8f0;
            --card-shadow: 0 12px 30px -5px rgba(15, 23, 42, 0.08), 0 4px 12px -2px rgba(15, 23, 42, 0.03);
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
            color: #334155;
            padding-bottom: 4rem;
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', sans-serif;
            color: var(--dark);
            font-weight: 700;
        }

        .bg-decorations {
            position: absolute;
            width: 100%;
            height: 380px;
            top: 0;
            left: 0;
            background: linear-gradient(135deg, #312e81 0%, #4338ca 50%, #6d28d9 100%);
            z-index: -2;
            border-bottom-left-radius: 40px;
            border-bottom-right-radius: 40px;
        }

        .bg-circle-1 {
            position: absolute;
            width: 320px;
            height: 320px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.06);
            top: -60px;
            right: -40px;
            z-index: -1;
        }

        .bg-circle-2 {
            position: absolute;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.04);
            top: 190px;
            left: -40px;
            z-index: -1;
        }

        .container {
            max-width: 1080px;
            margin-top: 25px;
        }

        .brand-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.75rem;
            padding: 0.5rem 0;
        }

        .brand-logo {
            font-family: 'Outfit', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .hero-card {
            background: white;
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.9);
            overflow: hidden;
            margin-bottom: 1.75rem;
        }

        .client-avatar {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--chit-purple) 100%);
            color: white;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            font-weight: 800;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 1.25rem;
            margin-top: 1.25rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--border);
        }

        .stat-item {
            background: #f8fafc;
            padding: 1rem 1.25rem;
            border-radius: 16px;
            border: 1px solid var(--border);
        }

        .stat-item .stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--slate);
            text-transform: uppercase;
            letter-spacing: 0.75px;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stat-item .stat-value {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--dark);
            font-family: 'Outfit', sans-serif;
        }

        /* Nav Module Tabs */
        .nav-module-pills {
            display: flex;
            gap: 12px;
            margin-bottom: 1.75rem;
            background: white;
            padding: 8px;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
        }

        .nav-module-pills .nav-link {
            border-radius: 14px;
            padding: 12px 24px;
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--slate);
            background: transparent;
            border: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            justify-content: center;
        }

        .nav-module-pills .nav-link:hover {
            color: var(--primary);
            background: rgba(79, 70, 229, 0.05);
        }

        .nav-module-pills .nav-link.active {
            background: linear-gradient(135deg, var(--primary) 0%, #6366f1 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
        }

        /* Section Cards */
        .schedule-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            margin-bottom: 1.75rem;
            overflow: hidden;
        }

        .schedule-card-header {
            padding: 1.25rem 1.75rem;
            background: #fafafc;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-custom {
            width: 100%;
            margin-bottom: 0;
        }

        .table-custom th {
            background-color: #f8fafc;
            border-bottom: 1px solid var(--border);
            color: var(--slate);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.8px;
            padding: 1rem 1.5rem;
        }

        .table-custom td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .table-custom tbody tr:hover {
            background-color: rgba(248, 250, 252, 0.8);
        }

        .badge-status {
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-status.paid { background-color: var(--success-light); color: var(--success); }
        .badge-status.overdue { background-color: var(--danger-light); color: var(--danger); }
        .badge-status.pending { background-color: var(--warning-light); color: #b45309; }
        .badge-status.partial { background-color: var(--info-light); color: var(--info); }
        .badge-status.upcoming { background-color: #f1f5f9; color: var(--slate); }

        .progress-custom {
            height: 8px;
            border-radius: 10px;
            background-color: #f1f5f9;
            overflow: hidden;
        }

        .progress-bar-custom {
            height: 100%;
            border-radius: 10px;
            background: linear-gradient(90deg, var(--primary) 0%, #818cf8 100%);
        }

        .sub-nav-btn {
            border-radius: 30px;
            padding: 8px 18px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition);
        }

        @media (max-width: 768px) {
            .nav-module-pills {
                flex-direction: column;
                gap: 6px;
            }
            .nav-module-pills .nav-link {
                justify-content: flex-start;
            }
            .table-custom th, .table-custom td {
                padding: 0.75rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="bg-decorations">
        <div class="bg-circle-1"></div>
        <div class="bg-circle-2"></div>
    </div>

    <div class="container">
        <!-- Brand Header -->
        <div class="brand-header">
            @php
                $adminLogo = \App\Helpers\SettingsHelper::get('admin_logo');
                $company = \App\Models\CompanyDetail::first();
                $companyName = \App\Helpers\SettingsHelper::get('admin_title', $company->company_name ?? config('app.name', 'Fintronix Chit Funds & Finance'));
                $companySlogan = $company->company_slogan ?? \App\Helpers\SettingsHelper::get('admin_subtitle', '');
                $logoExists = $adminLogo && \Illuminate\Support\Facades\Storage::disk('public')->exists($adminLogo);
                if (!$logoExists && !empty($company->logo_path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($company->logo_path)) {
                    $adminLogo = $company->logo_path;
                    $logoExists = true;
                }
            @endphp
            <a href="#" class="brand-logo d-flex align-items-center">
                @if($logoExists)
                    <img src="{{ asset('storage/' . $adminLogo) }}" alt="{{ $companyName }}" style="max-height: 52px; max-width: 180px; object-fit: contain;" class="rounded bg-white p-1.5 shadow-sm me-2_5">
                @else
                    <i class="ri-bank-card-line me-2"></i>
                @endif
                <div class="d-flex flex-column">
                    <span class="fs-4 fw-bold text-white lh-1">{{ $companyName }}</span>
                    @if(!empty($companySlogan))
                        <small class="text-white-50 mt-1" style="font-size: 0.8rem; font-weight: 500; letter-spacing: 0.3px;">{{ $companySlogan }}</small>
                    @endif
                </div>
            </a>
            <span class="badge bg-white text-dark px-3.5 py-2 rounded-pill shadow-sm fw-semibold">
                <i class="ri-shield-check-line text-success me-1"></i> Verified Client Passbook
            </span>
        </div>

        <!-- Client Hero Summary Card -->
        <div class="hero-card">
            <div class="p-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="client-avatar">
                            {{ strtoupper(substr($client->client_name ?? 'C', 0, 1)) }}
                        </div>
                        <div>
                            <h4 class="mb-1 fw-bold">{{ $client->client_name }}</h4>
                            <p class="text-muted mb-0 small">
                                @if($client->client_phone)
                                    <i class="ri-phone-line me-1"></i>{{ $client->client_phone }}
                                @endif
                                @if(optional($client->location)->name)
                                    <span class="mx-1">•</span><i class="ri-map-pin-line me-1"></i>{{ $client->location->name }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        @if($summary['has_chits'])
                            <span class="badge text-white px-3 py-2 rounded-pill" style="background: linear-gradient(135deg, var(--chit-purple) 0%, #a855f7 100%);">
                                <i class="ri-group-2-line me-1"></i>{{ $summary['total_chits'] }} Chit Subscription(s)
                            </span>
                        @endif
                        @if($summary['has_loans'])
                            <span class="badge text-white px-3 py-2 rounded-pill" style="background: linear-gradient(135deg, var(--primary) 0%, #6366f1 100%);">
                                <i class="ri-file-list-3-line me-1"></i>{{ $summary['total_loans'] }} Loan Account(s)
                            </span>
                        @endif
                    </div>
                </div>

                <div class="stat-grid">
                    <div class="stat-item">
                        <span class="stat-label"><i class="ri-checkbox-circle-line text-success"></i> Total Paid</span>
                        <span class="stat-value text-success">₹{{ number_format($summary['total_paid'], 2) }}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><i class="ri-error-warning-line text-danger"></i> Outstanding</span>
                        <span class="stat-value text-danger">₹{{ number_format($summary['total_balance'], 2) }}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><i class="ri-time-line text-warning"></i> Overdue Count</span>
                        <span class="stat-value {{ $summary['total_overdue'] > 0 ? 'text-danger' : 'text-success' }}">
                            {{ $summary['total_overdue'] }}
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><i class="ri-user-star-line text-primary"></i> Client Status</span>
                        <span class="stat-value text-primary fs-6 text-capitalize">
                            <span class="badge bg-success-light text-success px-2.5 py-1 rounded-pill"><i class="ri-check-line me-1"></i>Active</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── TAB-WISE MAIN NAVIGATION BAR ── --}}
        <ul class="nav nav-pills nav-module-pills" id="mainScheduleTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-overview-btn" data-bs-toggle="pill" data-bs-target="#tab-overview-pane" type="button" role="tab">
                    <i class="ri-dashboard-line"></i>Accounts Overview Table
                </button>
            </li>
            @if($summary['has_chits'])
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-chits-btn" data-bs-toggle="pill" data-bs-target="#tab-chits-pane" type="button" role="tab">
                    <i class="ri-group-2-line"></i>Chit Groups ({{ $summary['total_chits'] }})
                </button>
            </li>
            @endif
            @if($summary['has_loans'])
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-loans-btn" data-bs-toggle="pill" data-bs-target="#tab-loans-pane" type="button" role="tab">
                    <i class="ri-file-list-3-line"></i>Loans Schedule ({{ $summary['total_loans'] }})
                </button>
            </li>
            @endif
        </ul>

        {{-- ── TAB CONTENT CONTAINERS ── --}}
        <div class="tab-content" id="mainScheduleTabContent">

            {{-- ── TAB 1: ACCOUNTS OVERVIEW TABLE (TABLE WISE) ── --}}
            <div class="tab-pane fade show active" id="tab-overview-pane" role="tabpanel">
                <div class="schedule-card mb-4">
                    <div class="schedule-card-header">
                        <div>
                            <h5 class="mb-0 text-dark fw-bold">
                                <i class="ri-table-line me-2 text-primary"></i>All Accounts & Subscriptions Summary Table
                            </h5>
                            <small class="text-muted">Table-wise consolidated view of all active loans and chit group memberships</small>
                        </div>
                        <span class="badge bg-primary text-white rounded-pill px-3 py-1_5">
                            {{ $summary['total_chits'] + $summary['total_loans'] }} Total Accounts
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Account / Code</th>
                                    <th>Plan / Scheme Name</th>
                                    <th>Category</th>
                                    <th class="text-end">Total Amount</th>
                                    <th class="text-end">Paid Amount</th>
                                    <th class="text-end">Balance</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $accCounter = 1; @endphp

                                {{-- Render Chits Rows --}}
                                @foreach($memberships as $membership)
                                    @php
                                        $memberId = $membership->id;
                                        $group = $membership->group;
                                        if (!$group) continue;
                                        $memberInsts = $byMember->get($memberId, collect());
                                        $seatLetter = $seatLetterMap[$memberId] ?? null;
                                        $cPaid = $memberInsts->sum(fn ($i) => isset($i->client_share_paid) ? (float) $i->client_share_paid : (float) $i->paid_amount);
                                        $cBal = $memberInsts->sum(fn ($i) => isset($i->client_share_balance) ? max(0, (float) $i->client_share_balance) : max(0, (float) $i->balance));
                                        $sharePctSummary = (float) ($membership->ownership_percentage ?? $membership->effective_share_percentage ?? 100);
                                        $cTotal = $membership->client_chit_value ?? round(($group->chit_value ?? 0) * ($sharePctSummary / 100), 2);
                                        $wonPayout = $membership->won_payout ?? null;
                                        $pendingPayout = $membership->pending_payout ?? null;
                                    @endphp
                                    <tr>
                                        <td class="fw-semibold">{{ $accCounter++ }}</td>
                                        <td>
                                            <span class="badge text-white px-2.5 py-1" style="background-color: var(--chit-purple);">
                                                Group: {{ $group->group_code }}
                                            </span>
                                            @if($seatLetter)
                                                <span class="badge bg-warning text-dark ms-1">Seat {{ $seatLetter }}</span>
                                            @endif
                                            @if($sharePctSummary < 100)
                                                <span class="badge bg-info text-white ms-1" style="font-size:.65rem;">{{ rtrim(rtrim(number_format($sharePctSummary, 2), '0'), '.') }}% Share</span>
                                            @endif
                                        </td>
                                        <td><span class="fw-medium text-dark">{{ $group->scheme->name ?? 'Chit Scheme' }}</span></td>
                                        <td><span class="badge bg-light fw-semibold" style="color: var(--chit-purple); border: 1px solid var(--chit-purple);">Chit Fund</span></td>
                                        <td class="text-end fw-bold">₹{{ number_format($cTotal, 2) }}</td>
                                        <td class="text-end text-success fw-semibold">₹{{ number_format($cPaid, 2) }}</td>
                                        <td class="text-end text-danger fw-semibold">₹{{ number_format($cBal, 2) }}</td>
                                        <td class="text-center">
                                            @if($wonPayout)
                                                <span class="badge bg-success text-white px-2.5 py-1 rounded-pill shadow-sm" style="font-size: 0.78rem;">
                                                    <i class="ri-trophy-fill text-warning me-1"></i>Settlement Released (Month {{ $wonPayout->month_number }})
                                                </span>
                                            @elseif($pendingPayout)
                                                <span class="badge bg-warning text-dark px-2.5 py-1 rounded-pill shadow-sm" style="font-size: 0.78rem;">
                                                    <i class="ri-history-line me-1"></i>Settlement Requested
                                                </span>
                                            @else
                                                <span class="badge-status paid">
                                                    <i class="ri-checkbox-blank-circle-fill me-1" style="font-size: 6px;"></i>Active
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <button type="button" onclick="switchToChitTab('chit-pane-{{ $memberId }}')" class="btn btn-sm btn-outline-primary sub-nav-btn py-1 px-3">
                                                <i class="ri-eye-line me-1"></i>View Schedule
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach

                                {{-- Render Loans Rows --}}
                                @foreach($loans as $loan)
                                    @php
                                        $lPaid = (float) ($loan->total_paid ?? $loan->paid_amount ?? $loan->emis->sum('paid_amount'));
                                        $lBal = (isset($loan->outstanding_amount) && (float) $loan->outstanding_amount >= 0)
                                            ? (float) $loan->outstanding_amount
                                            : $loan->emis->sum(fn ($e) => max(0, (float) $e->total_amount - (float) $e->paid_amount));
                                        $lTotal = (float) ($loan->total_payable ?: ($loan->loan_amount ?? 0));
                                        if ($lTotal < ($lPaid + $lBal)) {
                                            $lTotal = $lPaid + $lBal;
                                        }
                                        $loanPlanName = $loan->loanApplication?->product?->loan_name
                                            ?? $loan->loanProduct?->loan_name
                                            ?? $loan->loan_code
                                            ?? 'Loan Product';
                                    @endphp
                                    <tr>
                                        <td class="fw-semibold">{{ $accCounter++ }}</td>
                                        <td>
                                            <span class="badge bg-primary px-2.5 py-1">
                                                Loan: {{ $loan->account_number ?? $loan->loanApplication->application_number ?? 'Loan' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-medium text-dark">{{ $loanPlanName }}</span>
                                            @if($loan->loan_amount && $loan->total_payable && $loan->total_payable > $loan->loan_amount)
                                                <small class="text-muted d-block font-normal" style="font-size: 0.72rem;">Principal: ₹{{ number_format($loan->loan_amount, 2) }}</small>
                                            @endif
                                        </td>
                                        <td><span class="badge bg-light text-primary border border-primary fw-semibold">Loan</span></td>
                                        <td class="text-end fw-bold">₹{{ number_format($lTotal, 2) }}</td>
                                        <td class="text-end text-success fw-semibold">₹{{ number_format($lPaid, 2) }}</td>
                                        <td class="text-end text-danger fw-semibold">₹{{ number_format($lBal, 2) }}</td>
                                        <td class="text-center">
                                            <span class="badge-status {{ $loan->status === 'active' ? 'paid' : 'pending' }}">
                                                <i class="ri-checkbox-blank-circle-fill me-1" style="font-size: 6px;"></i>{{ ucfirst($loan->status) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" onclick="switchToLoanTab('loan-pane-{{ $loan->id }}')" class="btn btn-sm btn-outline-primary sub-nav-btn py-1 px-3">
                                                <i class="ri-eye-line me-1"></i>View Schedule
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                                @if($memberships->isEmpty() && $loans->isEmpty())
                                    <tr>
                                        <td colspan="9" class="text-center py-5 text-muted">
                                            <i class="ri-information-line fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                            No active loan accounts or chit subscriptions to display.
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                            @if($memberships->isNotEmpty() || $loans->isNotEmpty())
                                <tfoot>
                                    <tr class="fw-bold bg-light" style="border-top: 2px solid var(--border);">
                                        <td colspan="4" class="text-end text-dark">Consolidated Total:</td>
                                        <td class="text-end text-dark">₹{{ number_format($summary['total_payable_sum'] ?? ($summary['total_paid'] + $summary['total_balance']), 2) }}</td>
                                        <td class="text-end text-success">₹{{ number_format($summary['total_paid'], 2) }}</td>
                                        <td class="text-end text-danger">₹{{ number_format($summary['total_balance'], 2) }}</td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

            {{-- ── TAB 2: CHIT GROUPS TAB PANE ── --}}
            @if($summary['has_chits'])
            <div class="tab-pane fade" id="tab-chits-pane" role="tabpanel">
                @if($memberships->count() > 1)
                    <!-- Sub Tabs for Multiple Chit Memberships -->
                    <div class="d-flex align-items-center gap-2 mb-3 overflow-auto pb-1" id="chitSubTabs" role="tablist">
                        <button class="btn btn-sm btn-outline-primary sub-nav-btn active px-3" id="btn-chit-pane-all" data-bs-toggle="pill" data-bs-target="#chit-pane-all" type="button">
                            <i class="ri-layout-grid-line me-1"></i>All Chits ({{ $memberships->count() }})
                        </button>
                        @foreach($memberships as $membership)
                            @php $seatLetter = $seatLetterMap[$membership->id] ?? null; @endphp
                            <button class="btn btn-sm btn-outline-primary sub-nav-btn px-3" id="btn-chit-pane-{{ $membership->id }}" data-bs-toggle="pill" data-bs-target="#chit-pane-{{ $membership->id }}" type="button">
                                Group {{ $membership->group->group_code ?? '' }} @if($seatLetter)(Seat {{ $seatLetter }})@endif
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="tab-content" id="chitSubTabContent">
                    {{-- All Chits Sub-pane --}}
                    <div class="tab-pane fade show active" id="chit-pane-all" role="tabpanel">
                        @foreach($memberships as $membership)
                            @php
                                $memberId = $membership->id;
                                $memberInsts = $byMember->get($memberId, collect());
                                $group = $membership->group;
                                if (!$group) continue;
                                $seatLetter = $seatLetterMap[$memberId] ?? null;
                                $paidCount = $memberInsts->where('display_status', 'paid')->count();
                                $totalCount = $memberInsts->count();
                                $progressPct = $totalCount > 0 ? round(($paidCount / $totalCount) * 100) : 0;

                                $wonPayout = $membership->won_payout ?? null;
                                $pendingPayout = $membership->pending_payout ?? null;
                                $wonAuction = $membership->won_auction ?? null;
                                $payoutsByMonth = $membership->payouts_by_month ?? collect();
                                $auctionsByMonth = $membership->auctions_by_month ?? collect();
                            @endphp

                            <div class="schedule-card mb-4">
                                <div class="schedule-card-header">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <span class="badge bg-primary px-2_5 py-1">Group: {{ $group->group_code }}</span>
                                            @if($seatLetter)
                                                <span class="badge bg-warning text-dark">Seat {{ $seatLetter }}</span>
                                            @endif
                                            @if(isset($membership->ownership_percentage) && $membership->ownership_percentage < 100)
                                                <span class="badge bg-info text-white"><i class="ri-pie-chart-line me-1"></i>{{ round($membership->ownership_percentage) }}% Share</span>
                                            @endif
                                            <span class="fw-semibold text-dark">{{ $group->scheme->name ?? '' }}</span>

                                            @if($wonPayout)
                                                <span class="badge bg-success text-white shadow-sm d-inline-flex align-items-center gap-1 px-2.5 py-1">
                                                    <i class="ri-trophy-fill text-warning"></i> Won (Month #{{ $wonPayout->month_number }}) - Settled
                                                </span>
                                            @elseif($pendingPayout)
                                                <span class="badge bg-warning text-dark shadow-sm d-inline-flex align-items-center gap-1 px-2.5 py-1">
                                                    <i class="ri-history-line"></i> Settlement Requested (Month #{{ $pendingPayout->month_number }})
                                                </span>
                                            @elseif($wonAuction)
                                                <span class="badge bg-success text-white shadow-sm d-inline-flex align-items-center gap-1 px-2.5 py-1">
                                                    <i class="ri-trophy-line text-warning"></i> Won Auction (Month #{{ $wonAuction->month_number }})
                                                </span>
                                            @endif
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            @if(isset($membership->ownership_percentage) && $membership->ownership_percentage < 100)
                                                Chit Value Share: <strong>₹{{ number_format($membership->client_chit_value ?? 0) }}</strong> ({{ round($membership->ownership_percentage) }}% Share of ₹{{ number_format($group->chit_value ?? 0) }}) | Monthly Share: <strong>₹{{ number_format($membership->client_monthly_amount ?? 0) }}</strong>
                                            @else
                                                Chit Value: ₹{{ number_format($group->chit_value ?? 0) }} | Monthly: ₹{{ number_format($group->installment_amount ?? 0) }}
                                            @endif
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="fw-bold text-success">{{ $paidCount }}/{{ $totalCount }} Paid</span>
                                        <div class="progress-custom mt-1" style="width: 120px;">
                                            <div class="progress-bar-custom" style="width: {{ $progressPct }}%;"></div>
                                        </div>
                                    </div>
                                </div>

                                @if($wonPayout)
                                    <div class="p-3 mx-4 mt-3 rounded-4 shadow-sm border-0 d-flex align-items-center justify-content-between flex-wrap gap-2" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: white;">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-white text-success d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                                                <i class="ri-trophy-fill fs-4 text-warning"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 text-white fw-bold">Chit Settlement Completed &amp; Released</h6>
                                                <small class="text-white-50">
                                                    Month {{ $wonPayout->month_number }} Payout &bull; Gross Settlement: ₹{{ number_format((float) $wonPayout->payout_amount, 2) }}
                                                    @if($wonPayout->net_payout_amount)
                                                        &bull; Net Paid: <strong>₹{{ number_format((float) $wonPayout->net_payout_amount, 2) }}</strong>
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                        <span class="badge bg-white text-success fw-bold px-3 py-1_5 rounded-pill shadow-sm"><i class="ri-checkbox-circle-fill me-1"></i>Settlement Released</span>
                                    </div>
                                @elseif($pendingPayout)
                                    <div class="p-3 mx-4 mt-3 rounded-4 shadow-sm border-0 d-flex align-items-center justify-content-between flex-wrap gap-2" style="background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); color: white;">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-white text-warning d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                                                <i class="ri-time-line fs-4 text-warning"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 text-white fw-bold">Chit Settlement Application Under Review</h6>
                                                <small class="text-white-50">
                                                    Month {{ $pendingPayout->month_number }} Settlement Application Submitted &bull; Amount: ₹{{ number_format((float) $pendingPayout->payout_amount, 2) }}
                                                </small>
                                            </div>
                                        </div>
                                        <span class="badge bg-white text-dark fw-bold px-3 py-1_5 rounded-pill shadow-sm"><i class="ri-history-line me-1"></i>Application Pending</span>
                                    </div>
                                @endif

                                <div class="table-responsive">
                                    <table class="table table-custom">
                                        <thead>
                                            <tr>
                                                <th>Period</th>
                                                <th>Due Date</th>
                                                <th>Amount</th>
                                                <th>Paid</th>
                                                <th>Payout Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($memberInsts as $inst)
                                                @php
                                                    $statusClass = match($inst->display_status) {
                                                        'paid' => 'paid',
                                                        'overdue' => 'overdue',
                                                        'pending' => 'pending',
                                                        'partial' => 'partial',
                                                        default => 'upcoming'
                                                    };
                                                    $freq = $group->installment_frequency ?? 'monthly';
                                                    $periodLabel = match($freq) {
                                                        'daily' => 'Day ' . $inst->month_number,
                                                        'weekly' => 'Week ' . $inst->month_number,
                                                        default => 'Month ' . $inst->month_number,
                                                    };

                                                    $monthPayout = $payoutsByMonth->get($inst->month_number);
                                                    $monthAuction = $auctionsByMonth->get($inst->month_number);

                                                    $rowStyle = '';
                                                    if ($monthPayout) {
                                                        if (in_array(strtolower($monthPayout->status ?? ''), ['paid', 'completed', 'disbursed'], true)) {
                                                            $rowStyle = 'style="background-color: #ecfdf5;"';
                                                        } elseif (in_array(strtolower($monthPayout->status ?? ''), ['pending', 'processing', 'submitted', 'requested'], true)) {
                                                            $rowStyle = 'style="background-color: #fffbeb;"';
                                                        }
                                                    }
                                                @endphp
                                                <tr {!! $rowStyle !!}>
                                                    <td class="fw-semibold">
                                                        {{ $periodLabel }}
                                                        @if($monthPayout)
                                                            @if(in_array(strtolower($monthPayout->status ?? ''), ['paid', 'completed', 'disbursed'], true))
                                                                <span class="badge bg-success text-white ms-1 shadow-sm px-2 py-0.5" style="font-size: 0.75rem;">
                                                                    <i class="ri-trophy-fill text-warning me-1"></i> Won
                                                                </span>
                                                            @elseif(in_array(strtolower($monthPayout->status ?? ''), ['pending', 'processing', 'submitted', 'requested'], true))
                                                                <span class="badge bg-warning text-dark ms-1 shadow-sm px-2 py-0.5" style="font-size: 0.75rem;">
                                                                    <i class="ri-history-line me-1"></i> Settlement Requested
                                                                </span>
                                                            @else
                                                                <span class="badge bg-info text-white ms-1 shadow-sm px-2 py-0.5" style="font-size: 0.75rem;">
                                                                    <i class="ri-award-line me-1"></i> {{ ucfirst($monthPayout->status) }}
                                                                </span>
                                                            @endif
                                                        @elseif($monthAuction)
                                                            <span class="badge bg-success text-white ms-1 shadow-sm px-2 py-0.5" style="font-size: 0.75rem;">
                                                                <i class="ri-trophy-line me-1"></i> Won
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $inst->due_date ? $inst->due_date->format('d M Y') : '—' }}</td>
                                                    @php
                                                        $dispInstAmt2 = isset($inst->client_share_amount) ? (float)$inst->client_share_amount : (float)$inst->amount;
                                                        $dispInstPaid2 = isset($inst->client_share_paid) ? (float)$inst->client_share_paid : (float)$inst->paid_amount;
                                                        $instSharePct2 = (float) ($inst->ownership_percentage ?? $membership?->effective_share_percentage ?? 100);

                                                        // If real payout/auction record exists, use it directly (already the correct amount for this member).
                                                        // Only apply amountForClient() scaling for estimated amounts from the group schedule.
                                                        if ($monthPayout?->payout_amount) {
                                                            $dispPayoutAmt2 = (float) $monthPayout->payout_amount;
                                                        } elseif ($monthAuction?->payout_amount) {
                                                            $dispPayoutAmt2 = (float) $monthAuction->payout_amount;
                                                        } else {
                                                            $scheduledPayout2 = $group->getScheduledPayoutAmountForMonth($inst->month_number) ?? (float)$group->chit_value;
                                                            $dispPayoutAmt2 = ($inst->member)
                                                                ? $inst->member->amountForClient((float)$scheduledPayout2, (int)$client->id)
                                                                : (float)$scheduledPayout2;
                                                        }
                                                    @endphp
                                                    <td class="fw-bold">
                                                        ₹{{ number_format($dispInstAmt2, 2) }}
                                                        @if($instSharePct2 < 100)
                                                            <small class="text-muted d-block font-normal" style="font-size: 0.7rem; font-weight: normal;">({{ rtrim(rtrim(number_format($instSharePct2, 2), '0'), '.') }}% share)</small>
                                                        @endif
                                                    </td>
                                                    <td class="text-success">₹{{ number_format($dispInstPaid2, 2) }}</td>
                                                    <td class="text-primary fw-semibold">
                                                        {{ $dispPayoutAmt2 ? '₹' . number_format($dispPayoutAmt2, 2) : '—' }}
                                                        @if($instSharePct2 < 100)
                                                            <small class="text-muted d-block font-normal" style="font-size: 0.7rem; font-weight: normal;">({{ rtrim(rtrim(number_format($instSharePct2, 2), '0'), '.') }}% share)</small>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge-status {{ $statusClass }}">
                                                            <i class="ri-checkbox-blank-circle-fill me-1" style="font-size: 6px;"></i>
                                                            {{ ucfirst($inst->display_status) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center py-3 text-muted">No installments generated yet.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Individual Chit Sub-panes --}}
                    @foreach($memberships as $membership)
                        @php
                            $memberId = $membership->id;
                            $memberInsts = $byMember->get($memberId, collect());
                            $group = $membership->group;
                            if (!$group) continue;
                            $seatLetter = $seatLetterMap[$memberId] ?? null;
                            $paidCount = $memberInsts->where('display_status', 'paid')->count();
                            $totalCount = $memberInsts->count();
                            $progressPct = $totalCount > 0 ? round(($paidCount / $totalCount) * 100) : 0;

                            $wonPayout = $membership->won_payout ?? null;
                            $pendingPayout = $membership->pending_payout ?? null;
                            $wonAuction = $membership->won_auction ?? null;
                            $payoutsByMonth = $membership->payouts_by_month ?? collect();
                            $auctionsByMonth = $membership->auctions_by_month ?? collect();
                        @endphp

                        <div class="tab-pane fade" id="chit-pane-{{ $memberId }}" role="tabpanel">
                            <div class="schedule-card">
                                <div class="schedule-card-header">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <span class="badge bg-primary px-2_5 py-1">Group: {{ $group->group_code }}</span>
                                            @if($seatLetter)
                                                <span class="badge bg-warning text-dark">Seat {{ $seatLetter }}</span>
                                            @endif
                                            @if(isset($membership->ownership_percentage) && $membership->ownership_percentage < 100)
                                                <span class="badge bg-info text-white"><i class="ri-pie-chart-line me-1"></i>{{ round($membership->ownership_percentage) }}% Share</span>
                                            @endif
                                            <span class="fw-semibold text-dark">{{ $group->scheme->name ?? '' }}</span>

                                            @if($wonPayout)
                                                <span class="badge bg-success text-white shadow-sm d-inline-flex align-items-center gap-1 px-2.5 py-1">
                                                    <i class="ri-trophy-fill text-warning"></i> Won (Month #{{ $wonPayout->month_number }}) - Settled
                                                </span>
                                            @elseif($pendingPayout)
                                                <span class="badge bg-warning text-dark shadow-sm d-inline-flex align-items-center gap-1 px-2.5 py-1">
                                                    <i class="ri-history-line"></i> Settlement Requested (Month #{{ $pendingPayout->month_number }})
                                                </span>
                                            @elseif($wonAuction)
                                                <span class="badge bg-success text-white shadow-sm d-inline-flex align-items-center gap-1 px-2.5 py-1">
                                                    <i class="ri-trophy-line text-warning"></i> Won Auction (Month #{{ $wonAuction->month_number }})
                                                </span>
                                            @endif
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            @if(isset($membership->ownership_percentage) && $membership->ownership_percentage < 100)
                                                Chit Value Share: <strong>₹{{ number_format($membership->client_chit_value ?? 0) }}</strong> ({{ round($membership->ownership_percentage) }}% Share of ₹{{ number_format($group->chit_value ?? 0) }}) | Monthly Share: <strong>₹{{ number_format($membership->client_monthly_amount ?? 0) }}</strong>
                                            @else
                                                Chit Value: ₹{{ number_format($group->chit_value ?? 0) }} | Monthly: ₹{{ number_format($group->installment_amount ?? 0) }}
                                            @endif
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="fw-bold text-success">{{ $paidCount }}/{{ $totalCount }} Paid</span>
                                        <div class="progress-custom mt-1" style="width: 120px;">
                                            <div class="progress-bar-custom" style="width: {{ $progressPct }}%;"></div>
                                        </div>
                                    </div>
                                </div>

                                @if($wonPayout)
                                    <div class="p-3 mx-4 mt-3 rounded-4 shadow-sm border-0 d-flex align-items-center justify-content-between flex-wrap gap-2" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: white;">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-white text-success d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                                                <i class="ri-trophy-fill fs-4 text-warning"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 text-white fw-bold">Chit Settlement Completed &amp; Released</h6>
                                                <small class="text-white-50">
                                                    Month {{ $wonPayout->month_number }} Payout &bull; Gross Settlement: ₹{{ number_format((float) $wonPayout->payout_amount, 2) }}
                                                    @if($wonPayout->net_payout_amount)
                                                        &bull; Net Paid: <strong>₹{{ number_format((float) $wonPayout->net_payout_amount, 2) }}</strong>
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                        <span class="badge bg-white text-success fw-bold px-3 py-1_5 rounded-pill shadow-sm"><i class="ri-checkbox-circle-fill me-1"></i>Settlement Released</span>
                                    </div>
                                @elseif($pendingPayout)
                                    <div class="p-3 mx-4 mt-3 rounded-4 shadow-sm border-0 d-flex align-items-center justify-content-between flex-wrap gap-2" style="background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); color: white;">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-white text-warning d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                                                <i class="ri-time-line fs-4 text-warning"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 text-white fw-bold">Chit Settlement Application Under Review</h6>
                                                <small class="text-white-50">
                                                    Month {{ $pendingPayout->month_number }} Settlement Application Submitted &bull; Amount: ₹{{ number_format((float) $pendingPayout->payout_amount, 2) }}
                                                </small>
                                            </div>
                                        </div>
                                        <span class="badge bg-white text-dark fw-bold px-3 py-1_5 rounded-pill shadow-sm"><i class="ri-history-line me-1"></i>Application Pending</span>
                                    </div>
                                @endif

                                <div class="table-responsive">
                                    <table class="table table-custom">
                                        <thead>
                                            <tr>
                                                <th>Period</th>
                                                <th>Due Date</th>
                                                <th>Amount</th>
                                                <th>Paid</th>
                                                <th>Payout Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($memberInsts as $inst)
                                                @php
                                                    $statusClass = match($inst->display_status) {
                                                        'paid' => 'paid',
                                                        'overdue' => 'overdue',
                                                        'pending' => 'pending',
                                                        'partial' => 'partial',
                                                        default => 'upcoming'
                                                    };
                                                    $freq = $group->installment_frequency ?? 'monthly';
                                                    $periodLabel = match($freq) {
                                                        'daily' => 'Day ' . $inst->month_number,
                                                        'weekly' => 'Week ' . $inst->month_number,
                                                        default => 'Month ' . $inst->month_number,
                                                    };

                                                    $monthPayout = $payoutsByMonth->get($inst->month_number);
                                                    $monthAuction = $auctionsByMonth->get($inst->month_number);

                                                    $rowStyle = '';
                                                    if ($monthPayout) {
                                                        if (in_array(strtolower($monthPayout->status ?? ''), ['paid', 'completed', 'disbursed'], true)) {
                                                            $rowStyle = 'style="background-color: #ecfdf5;"';
                                                        } elseif (in_array(strtolower($monthPayout->status ?? ''), ['pending', 'processing', 'submitted', 'requested'], true)) {
                                                            $rowStyle = 'style="background-color: #fffbeb;"';
                                                        }
                                                    }
                                                @endphp
                                                <tr {!! $rowStyle !!}>
                                                    <td class="fw-semibold">
                                                        {{ $periodLabel }}
                                                        @if($monthPayout)
                                                            @if(in_array(strtolower($monthPayout->status ?? ''), ['paid', 'completed', 'disbursed'], true))
                                                                <span class="badge bg-success text-white ms-1 shadow-sm px-2 py-0.5" style="font-size: 0.75rem;">
                                                                    <i class="ri-trophy-fill text-warning me-1"></i> Won
                                                                </span>
                                                            @elseif(in_array(strtolower($monthPayout->status ?? ''), ['pending', 'processing', 'submitted', 'requested'], true))
                                                                <span class="badge bg-warning text-dark ms-1 shadow-sm px-2 py-0.5" style="font-size: 0.75rem;">
                                                                    <i class="ri-history-line me-1"></i> Settlement Requested
                                                                </span>
                                                            @else
                                                                <span class="badge bg-info text-white ms-1 shadow-sm px-2 py-0.5" style="font-size: 0.75rem;">
                                                                    <i class="ri-award-line me-1"></i> {{ ucfirst($monthPayout->status) }}
                                                                </span>
                                                            @endif
                                                        @elseif($monthAuction)
                                                            <span class="badge bg-success text-white ms-1 shadow-sm px-2 py-0.5" style="font-size: 0.75rem;">
                                                                <i class="ri-trophy-line me-1"></i> Won
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $inst->due_date ? $inst->due_date->format('d M Y') : '—' }}</td>
                                                    @php
                                                        $dispInstAmt2 = isset($inst->client_share_amount) ? (float)$inst->client_share_amount : (float)$inst->amount;
                                                        $dispInstPaid2 = isset($inst->client_share_paid) ? (float)$inst->client_share_paid : (float)$inst->paid_amount;
                                                        $instSharePct2 = (float) ($inst->ownership_percentage ?? $membership?->effective_share_percentage ?? 100);

                                                        // If real payout/auction record exists, use it directly (already the correct amount for this member).
                                                        // Only apply amountForClient() scaling for estimated amounts from the group schedule.
                                                        if ($monthPayout?->payout_amount) {
                                                            $dispPayoutAmt2 = (float) $monthPayout->payout_amount;
                                                        } elseif ($monthAuction?->payout_amount) {
                                                            $dispPayoutAmt2 = (float) $monthAuction->payout_amount;
                                                        } else {
                                                            $scheduledPayout2 = $group->getScheduledPayoutAmountForMonth($inst->month_number) ?? (float)$group->chit_value;
                                                            $dispPayoutAmt2 = ($inst->member)
                                                                ? $inst->member->amountForClient((float)$scheduledPayout2, (int)$client->id)
                                                                : (float)$scheduledPayout2;
                                                        }
                                                    @endphp
                                                    <td class="fw-bold">
                                                        ₹{{ number_format($dispInstAmt2, 2) }}
                                                        @if($instSharePct2 < 100)
                                                            <small class="text-muted d-block font-normal" style="font-size: 0.7rem; font-weight: normal;">({{ rtrim(rtrim(number_format($instSharePct2, 2), '0'), '.') }}% share)</small>
                                                        @endif
                                                    </td>
                                                    <td class="text-success">₹{{ number_format($dispInstPaid2, 2) }}</td>
                                                    <td class="text-primary fw-semibold">
                                                        {{ $dispPayoutAmt2 ? '₹' . number_format($dispPayoutAmt2, 2) : '—' }}
                                                        @if($instSharePct2 < 100)
                                                            <small class="text-muted d-block font-normal" style="font-size: 0.7rem; font-weight: normal;">({{ rtrim(rtrim(number_format($instSharePct2, 2), '0'), '.') }}% share)</small>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge-status {{ $statusClass }}">
                                                            <i class="ri-checkbox-blank-circle-fill me-1" style="font-size: 6px;"></i>
                                                            {{ ucfirst($inst->display_status) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center py-3 text-muted">No installments generated yet.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- ── TAB 3: LOANS TAB PANE ── --}}
            @if($summary['has_loans'])
            <div class="tab-pane fade" id="tab-loans-pane" role="tabpanel">
                @if($loans->count() > 1)
                    <!-- Account-wise Sub Tabs for Multiple Loans -->
                    <div class="d-flex align-items-center gap-2 mb-3 overflow-auto pb-1" id="loanSubTabs" role="tablist">
                        <button class="btn btn-sm btn-outline-primary sub-nav-btn active px-3" id="btn-loan-pane-all" data-bs-toggle="pill" data-bs-target="#loan-pane-all" type="button">
                            <i class="ri-layout-grid-line me-1"></i>All Loans ({{ $loans->count() }})
                        </button>
                        @foreach($loans as $loan)
                            @php
                                $loanTabName = $loan->loanApplication?->product?->loan_name
                                    ?? $loan->loanProduct?->loan_name
                                    ?? $loan->loan_code
                                    ?? ('Loan #' . $loop->iteration);
                            @endphp
                            <button class="btn btn-sm btn-outline-primary sub-nav-btn px-3" id="btn-loan-pane-{{ $loan->id }}" data-bs-toggle="pill" data-bs-target="#loan-pane-{{ $loan->id }}" type="button">
                                {{ $loanTabName }}{{ $loan->account_number ? ' (' . $loan->account_number . ')' : '' }}
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="tab-content" id="loanSubTabContent">
                    {{-- All Loans Sub-pane --}}
                    <div class="tab-pane fade show active" id="loan-pane-all" role="tabpanel">
                        @foreach($loans as $loan)
                            @php
                                $paidEmis = $loan->emis->where('status', 'paid')->count();
                                $totalEmis = $loan->emis->count();
                                $progressPct = $totalEmis > 0 ? round(($paidEmis / $totalEmis) * 100) : 0;
                                $loanPlanName = $loan->loanApplication?->product?->loan_name
                                    ?? $loan->loanProduct?->loan_name
                                    ?? $loan->loan_code
                                    ?? 'Loan Account';
                            @endphp

                            <div class="schedule-card">
                                <div class="schedule-card-header">
                                    <div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-primary px-2_5 py-1">Account: {{ $loan->account_number }}</span>
                                            <span class="fw-semibold text-dark">{{ $loanPlanName }}</span>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            Principal: <strong>₹{{ number_format($loan->loan_amount ?? 0, 2) }}</strong> &bull; Total Payable: <strong>₹{{ number_format($loan->total_payable ?: $loan->loan_amount, 2) }}</strong> &bull; Outstanding: <strong class="text-danger">₹{{ number_format((isset($loan->outstanding_amount) && (float) $loan->outstanding_amount >= 0) ? (float) $loan->outstanding_amount : $loan->emis->sum(fn ($e) => max(0, (float) $e->total_amount - (float) $e->paid_amount)), 2) }}</strong> &bull; Duration: {{ $loan->duration_months }} Months
                                        </small>
                                        <small class="text-muted d-block mt-1">
                                            <i class="ri-information-line me-1"></i>Showing dues up to {{ \Carbon\Carbon::now()->format('M Y') }} only
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="fw-bold text-success">{{ $paidEmis }}/{{ $totalEmis }} EMIs Paid</span>
                                        <div class="progress-custom mt-1" style="width: 120px;">
                                            <div class="progress-bar-custom" style="width: {{ $progressPct }}%;"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-custom">
                                        <thead>
                                            <tr>
                                                <th>Inst. #</th>
                                                <th>Due Date</th>
                                                <th>EMI Amount</th>
                                                <th>Paid Amount</th>
                                                <th>Balance</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($loan->due_emis ?? $loan->currentlyDueEmis() as $emi)
                                                @php
                                                    $today = \Carbon\Carbon::now()->startOfDay();
                                                    $dueDate = $emi->due_date ? \Carbon\Carbon::parse($emi->due_date)->startOfDay() : null;
                                                    $displayStatus = $emi->status;
                                                    if ($emi->status !== 'paid' && $dueDate && $dueDate->lt($today)) {
                                                        $displayStatus = 'overdue';
                                                    }
                                                    $statusClass = match($displayStatus) {
                                                        'paid' => 'paid',
                                                        'overdue' => 'overdue',
                                                        'pending' => 'pending',
                                                        'partial' => 'partial',
                                                        default => 'upcoming'
                                                    };
                                                    $balance = max(0, (float)$emi->total_amount - (float)$emi->paid_amount);
                                                @endphp
                                                <tr>
                                                    <td class="fw-semibold">Inst #{{ $emi->instalment_number }}</td>
                                                    <td>{{ $emi->due_date ? \Carbon\Carbon::parse($emi->due_date)->format('d M Y') : '—' }}</td>
                                                    <td class="fw-bold">₹{{ number_format($emi->total_amount, 2) }}</td>
                                                    <td class="text-success">₹{{ number_format($emi->paid_amount, 2) }}</td>
                                                    <td class="text-danger">₹{{ number_format($balance, 2) }}</td>
                                                    <td>
                                                        <span class="badge-status {{ $statusClass }}">
                                                            <i class="ri-checkbox-blank-circle-fill me-1" style="font-size: 6px;"></i>
                                                            {{ ucfirst($displayStatus) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center py-4">
                                                        <i class="ri-checkbox-circle-fill text-success d-block mb-1" style="font-size: 1.75rem;"></i>
                                                        <span class="fw-bold text-dark d-block">No dues pending</span>
                                                        <small class="text-muted">Nothing is outstanding up to {{ \Carbon\Carbon::now()->format('F Y') }}.</small>
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Individual Loan Sub-panes --}}
                    @foreach($loans as $loan)
                        <div class="tab-pane fade" id="loan-pane-{{ $loan->id }}" role="tabpanel">
                            @php
                                $paidEmis = $loan->emis->where('status', 'paid')->count();
                                $totalEmis = $loan->emis->count();
                                $progressPct = $totalEmis > 0 ? round(($paidEmis / $totalEmis) * 100) : 0;
                                $loanPlanName = $loan->loanApplication?->product?->loan_name
                                    ?? $loan->loanProduct?->loan_name
                                    ?? $loan->loan_code
                                    ?? 'Loan Account';
                            @endphp

                            <div class="schedule-card">
                                <div class="schedule-card-header">
                                    <div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-primary px-2_5 py-1">Account: {{ $loan->account_number }}</span>
                                            <span class="fw-semibold text-dark">{{ $loanPlanName }}</span>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            Principal: <strong>₹{{ number_format($loan->loan_amount ?? 0, 2) }}</strong> &bull; Total Payable: <strong>₹{{ number_format($loan->total_payable ?: $loan->loan_amount, 2) }}</strong> &bull; Outstanding: <strong class="text-danger">₹{{ number_format((isset($loan->outstanding_amount) && (float) $loan->outstanding_amount >= 0) ? (float) $loan->outstanding_amount : $loan->emis->sum(fn ($e) => max(0, (float) $e->total_amount - (float) $e->paid_amount)), 2) }}</strong> &bull; Duration: {{ $loan->duration_months }} Months
                                        </small>
                                        <small class="text-muted d-block mt-1">
                                            <i class="ri-information-line me-1"></i>Showing dues up to {{ \Carbon\Carbon::now()->format('M Y') }} only
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="fw-bold text-success">{{ $paidEmis }}/{{ $totalEmis }} EMIs Paid</span>
                                        <div class="progress-custom mt-1" style="width: 120px;">
                                            <div class="progress-bar-custom" style="width: {{ $progressPct }}%;"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-custom">
                                        <thead>
                                            <tr>
                                                <th>Inst. #</th>
                                                <th>Due Date</th>
                                                <th>EMI Amount</th>
                                                <th>Paid Amount</th>
                                                <th>Balance</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($loan->due_emis ?? $loan->currentlyDueEmis() as $emi)
                                                @php
                                                    $today = \Carbon\Carbon::now()->startOfDay();
                                                    $dueDate = $emi->due_date ? \Carbon\Carbon::parse($emi->due_date)->startOfDay() : null;
                                                    $displayStatus = $emi->status;
                                                    if ($emi->status !== 'paid' && $dueDate && $dueDate->lt($today)) {
                                                        $displayStatus = 'overdue';
                                                    }
                                                    $statusClass = match($displayStatus) {
                                                        'paid' => 'paid',
                                                        'overdue' => 'overdue',
                                                        'pending' => 'pending',
                                                        'partial' => 'partial',
                                                        default => 'upcoming'
                                                    };
                                                    $balance = max(0, (float)$emi->total_amount - (float)$emi->paid_amount);
                                                @endphp
                                                <tr>
                                                    <td class="fw-semibold">Inst #{{ $emi->instalment_number }}</td>
                                                    <td>{{ $emi->due_date ? \Carbon\Carbon::parse($emi->due_date)->format('d M Y') : '—' }}</td>
                                                    <td class="fw-bold">₹{{ number_format($emi->total_amount, 2) }}</td>
                                                    <td class="text-success">₹{{ number_format($emi->paid_amount, 2) }}</td>
                                                    <td class="text-danger">₹{{ number_format($balance, 2) }}</td>
                                                    <td>
                                                        <span class="badge-status {{ $statusClass }}">
                                                            <i class="ri-checkbox-blank-circle-fill me-1" style="font-size: 6px;"></i>
                                                            {{ ucfirst($displayStatus) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center py-4">
                                                        <i class="ri-checkbox-circle-fill text-success d-block mb-1" style="font-size: 1.75rem;"></i>
                                                        <span class="fw-bold text-dark d-block">No dues pending</span>
                                                        <small class="text-muted">Nothing is outstanding up to {{ \Carbon\Carbon::now()->format('F Y') }}.</small>
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>

        <div class="text-center mt-4 text-muted small">
            <p class="mb-1">Thank you for choosing <strong>{{ $companyName }}</strong> @if(!empty($companySlogan)) &mdash; <em>{{ $companySlogan }}</em> @endif.</p>
            @if(!empty($company->company_mobile))
                <p class="mb-0"><i class="ri-phone-fill me-1"></i> Contact Us: {{ $company->company_mobile }}</p>
            @endif
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function switchToChitTab(paneId) {
            var chitTabBtn = document.getElementById('tab-chits-btn');
            if (chitTabBtn) {
                var tab = bootstrap.Tab.getOrCreateInstance(chitTabBtn);
                tab.show();
            }
            var subBtn = document.getElementById('btn-' + paneId);
            if (subBtn) {
                var subTab = bootstrap.Tab.getOrCreateInstance(subBtn);
                subTab.show();
            } else {
                var targetPane = document.getElementById(paneId);
                if (targetPane) {
                    var container = targetPane.closest('.tab-content');
                    if (container) {
                        container.querySelectorAll(':scope > .tab-pane').forEach(function(el) {
                            el.classList.remove('show', 'active');
                        });
                    }
                    targetPane.classList.add('show', 'active');
                }
            }
        }

        function switchToLoanTab(paneId) {
            var loanTabBtn = document.getElementById('tab-loans-btn');
            if (loanTabBtn) {
                var tab = bootstrap.Tab.getOrCreateInstance(loanTabBtn);
                tab.show();
            }
            var subBtn = document.getElementById('btn-' + paneId);
            if (subBtn) {
                var subTab = bootstrap.Tab.getOrCreateInstance(subBtn);
                subTab.show();
            } else {
                var targetPane = document.getElementById(paneId);
                if (targetPane) {
                    var container = targetPane.closest('.tab-content');
                    if (container) {
                        container.querySelectorAll(':scope > .tab-pane').forEach(function(el) {
                            el.classList.remove('show', 'active');
                        });
                    }
                    targetPane.classList.add('show', 'active');
                }
            }
        }
    </script>
</body>
</html>
