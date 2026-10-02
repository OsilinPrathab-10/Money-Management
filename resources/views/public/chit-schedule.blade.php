<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Helpers\SettingsHelper::get('admin_title', \App\Models\CompanyDetail::first()->company_name ?? config('app.name', 'Codepluse Gen PVT Ltd')) }} - Chit Schedule</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #7c3aed;
            --primary-light: #ede9fe;
            --primary-dark: #5b21b6;
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
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
            background: linear-gradient(135deg, #7c3aed 0%, #a855f7 50%, #6366f1 100%);
            z-index: -2;
            border-bottom-left-radius: 40px;
            border-bottom-right-radius: 40px;
        }

        .bg-circle-1 {
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            top: -50px;
            right: -50px;
            z-index: -1;
        }

        .bg-circle-2 {
            position: absolute;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            top: 180px;
            left: -30px;
            z-index: -1;
        }

        .container {
            max-width: 960px;
            margin-top: 30px;
        }

        .brand-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
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
            letter-spacing: -0.5px;
        }

        .brand-logo i {
            margin-right: 0.6rem;
            font-size: 2rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 6px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }

        .secure-badge {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            color: white;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .secure-badge i {
            font-size: 0.95rem;
            color: #34d399;
        }

        .premium-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.75rem;
            overflow: hidden;
            transition: var(--transition);
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .hero-overview {
            padding: 2.25rem;
            background: white;
            position: relative;
        }

        .client-avatar {
            width: 52px;
            height: 52px;
            background-color: var(--primary-light);
            color: var(--primary);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1.25rem;
            margin-top: 1.75rem;
            padding-top: 1.75rem;
            border-top: 1px solid var(--border);
        }

        .stat-item {
            display: flex;
            flex-direction: column;
        }

        .stat-item .stat-label {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--slate);
            text-transform: uppercase;
            letter-spacing: 0.75px;
            margin-bottom: 0.35rem;
        }

        .stat-item .stat-value {
            font-size: 1.2rem;
            font-weight: 750;
            color: var(--dark);
            font-family: 'Outfit', sans-serif;
        }

        .progress-container {
            margin-top: 1.5rem;
        }

        .progress-label-wrap {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .progress-custom {
            height: 8px;
            border-radius: 10px;
            background-color: #f1f5f9;
            overflow: hidden;
        }

        .progress-bar-custom {
            height: 100%;
            border-radius: 10px;
            background: linear-gradient(90deg, var(--primary) 0%, #a855f7 100%);
            transition: width 1s ease-in-out;
        }

        .group-card {
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }

        .group-card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
        }

        .group-header {
            padding: 1.25rem 1.75rem;
            background: linear-gradient(135deg, #faf5ff 0%, #f5f3ff 100%);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .group-header .group-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .group-header .group-meta {
            font-size: 0.8rem;
            color: var(--slate);
        }

        .filter-tabs {
            display: flex;
            gap: 8px;
            padding: 1.25rem 2rem;
            background: #fafafc;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
        }

        .filter-btn {
            border: none;
            background: white;
            color: var(--slate);
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }

        .filter-btn:hover {
            color: var(--primary);
            border-color: var(--primary);
        }

        .filter-btn.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 10px rgba(124, 58, 237, 0.2);
        }

        .table-custom {
            width: 100%;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-custom th {
            background-color: #fcfcfd;
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
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
            color: #334155;
            transition: var(--transition);
        }

        .table-custom tbody tr {
            transition: var(--transition);
        }

        .table-custom tbody tr:hover {
            background-color: #f8fafc;
        }

        .table-custom tbody tr:last-child td {
            border-bottom: none;
        }

        .custom-badge {
            padding: 5px 12px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.72rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge-paid { background-color: var(--success-light); color: #065f46; }
        .badge-pending { background-color: var(--warning-light); color: #92400e; }
        .badge-overdue { background-color: var(--danger-light); color: #991b1b; }
        .badge-partial { background-color: var(--info-light); color: #155e75; }
        .badge-active { background-color: var(--success-light); color: #065f46; }
        .badge-forming { background-color: var(--primary-light); color: #5b21b6; }

        .btn-premium-outline {
            background: white;
            color: var(--slate);
            border: 1px solid var(--border);
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .btn-premium-outline:hover {
            color: var(--dark);
            border-color: var(--slate);
            background: #f8fafc;
        }

        .footer-custom {
            text-align: center;
            margin-top: 3.5rem;
            color: var(--slate);
            font-size: 0.85rem;
        }

        .footer-logo {
            font-family: 'Outfit', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--slate);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-bottom: 0.75rem;
        }

        @media (max-width: 768px) {
            .hero-overview { padding: 1.5rem; }
            .brand-header { flex-direction: column; gap: 12px; align-items: flex-start; }
            .secure-badge { align-self: flex-start; }
            .stat-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; }
            .filter-tabs { padding: 1rem; }
            .table-custom th { padding: 0.8rem 1rem; }
            .table-custom td { padding: 0.8rem 1rem; font-size: 0.82rem; }
            .group-header { padding: 1rem 1.25rem; }
        }

        @media print {
            body { background: white; color: black; padding: 0; }
            .bg-decorations, .bg-circle-1, .bg-circle-2, .filter-tabs, .btn-premium-outline, .secure-badge { display: none !important; }
            .container { max-width: 100%; margin-top: 0; }
            .brand-logo { color: black !important; }
            .brand-logo i { color: black !important; background: none !important; }
            .premium-card { border: none !important; box-shadow: none !important; margin-bottom: 1rem !important; }
            .group-card { border: 1px solid #ddd !important; box-shadow: none !important; }
            .table-custom th { background: #f1f5f9 !important; color: black !important; border-bottom: 2px solid black !important; }
            .table-custom td { border-bottom: 1px solid #ddd !important; }
        }
    </style>
</head>
<body>
    <div class="bg-decorations">
        <div class="bg-circle-1"></div>
        <div class="bg-circle-2"></div>
    </div>

    <div class="container">
        <header class="brand-header">
            @php
                $adminLogo = \App\Helpers\SettingsHelper::get('admin_logo');
                $company = \App\Models\CompanyDetail::first();
                $companyName = \App\Helpers\SettingsHelper::get('admin_title', $company->company_name ?? config('app.name', 'Codepluse Gen PVT Ltd'));
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
                    <i class="ri-group-2-line me-2"></i>
                @endif
                <div class="d-flex flex-column">
                    <span class="fs-4 fw-bold text-white lh-1">{{ $companyName }}</span>
                    @if(!empty($companySlogan))
                        <small class="text-white-50 mt-1" style="font-size: 0.8rem; font-weight: 500; letter-spacing: 0.3px;">{{ $companySlogan }}</small>
                    @endif
                </div>
            </a>
            <div class="secure-badge">
                <i class="ri-shield-check-fill"></i>
                <span>CHIT FUND PORTAL</span>
            </div>
        </header>

        {{-- Client Overview --}}
        <section class="premium-card hero-overview">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="client-avatar">
                        {{ strtoupper(substr($client->client_name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h3 class="mb-0">{{ $client->client_name }}</h3>
                            <span class="custom-badge badge-active">
                                <i class="ri-group-2-fill"></i>
                                {{ $summary['total_groups'] }} {{ \Illuminate\Support\Str::plural('Group', $summary['total_groups']) }}
                            </span>
                        </div>
                        <p class="text-muted mb-0 mt-1">
                            <i class="ri-phone-line me-1"></i>{{ $client->client_phone ?? '—' }}
                            @if(optional($client->location)->name)
                                &bull; <i class="ri-map-pin-line me-1"></i>{{ $client->location->name }}
                            @endif
                        </p>
                    </div>
                </div>
                <div>
                    <button onclick="window.print()" class="btn-premium-outline">
                        <i class="ri-printer-line"></i>
                        <span>Print</span>
                    </button>
                </div>
            </div>

            <div class="progress-container">
                <div class="progress-label-wrap">
                    <span class="text-muted">Installment Progress</span>
                    <span style="color: var(--primary);">{{ $progressPercent }}% Completed ({{ $summary['paid'] }}/{{ $summary['total_installments'] }})</span>
                </div>
                <div class="progress-custom">
                    <div class="progress-bar-custom" style="width: {{ $progressPercent }}%"></div>
                </div>
            </div>

            <div class="stat-grid">
                <div class="stat-item">
                    <span class="stat-label">Paid Amount</span>
                    <span class="stat-value" style="color: var(--success);">₹{{ number_format($summary['total_paid_amount'], 2) }}</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Outstanding Due</span>
                    <span class="stat-value" style="color: var(--danger);">₹{{ number_format($summary['total_balance'], 2) }}</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Chit Seats</span>
                    <span class="stat-value">{{ $summary['total_memberships'] }}</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Overdue</span>
                    <span class="stat-value" style="color: var(--danger);">{{ $summary['overdue'] }}</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Pending</span>
                    <span class="stat-value" style="color: var(--warning);">{{ $summary['pending'] }}</span>
                </div>
            </div>
        </section>

        {{-- Chit Groups List --}}
        <section class="premium-card">
            <div class="filter-tabs">
                <button class="filter-btn active" data-filter="all">
                    <i class="ri-list-check"></i> All ({{ $summary['total_installments'] }})
                </button>
                <button class="filter-btn" data-filter="paid">
                    <i class="ri-checkbox-circle-line"></i> Paid ({{ $summary['paid'] }})
                </button>
                <button class="filter-btn" data-filter="pending">
                    <i class="ri-time-line"></i> Pending ({{ $summary['pending'] }})
                </button>
                <button class="filter-btn" data-filter="overdue">
                    <i class="ri-alarm-warning-line"></i> Overdue ({{ $summary['overdue'] }})
                </button>
                @if($summary['partial'] > 0)
                <button class="filter-btn" data-filter="partial">
                    <i class="ri-pie-chart-line"></i> Partial ({{ $summary['partial'] }})
                </button>
                @endif
            </div>

            <div class="p-3 p-md-4">
                @forelse($byMember as $memberId => $memberInstallments)
                    @php
                        $group = $memberInstallments->first()->group;
                        $membership = $memberships->firstWhere('id', $memberId);
                        $seatLetter = $seatLetterMap[$memberId] ?? null;
                        $displayName = $client->client_name . ($seatLetter ? ' ' . $seatLetter : '');
                        $groupPaid = $memberInstallments->where('display_status', 'paid')->count();
                        $groupTotal = $memberInstallments->count();
                        $groupBalance = $memberInstallments->sum(fn($i) => max(0, (float)$i->balance));

                        $wonPayout = $membership->won_payout ?? null;
                        $pendingPayout = $membership->pending_payout ?? null;
                        $wonAuction = $membership->won_auction ?? null;
                        $payoutsByMonth = $membership->payouts_by_month ?? collect();
                        $auctionsByMonth = $membership->auctions_by_month ?? collect();
                    @endphp
                    @php
                        $mSharePctChit = (float) ($membership->ownership_percentage ?? $membership->effective_share_percentage ?? 100);
                    @endphp
                    <div class="group-card" data-group-id="{{ $memberId }}">
                        <div class="group-header">
                            <div>
                                <div class="group-title d-flex align-items-center gap-2 flex-wrap">
                                    <i class="ri-group-line" style="color: var(--primary);"></i>
                                    {{ $group->group_code ?? 'Group' }}
                                    @if($seatLetter)
                                        <span class="custom-badge badge-pending">{{ $displayName }}</span>
                                    @endif
                                    @if($mSharePctChit < 100)
                                        <span class="custom-badge badge-pending" style="background: #e0f2fe; color: #0369a1; font-weight: 700;">
                                            <i class="ri-pie-chart-line me-1"></i>{{ rtrim(rtrim(number_format($mSharePctChit, 2), '0'), '.') }}% Share
                                        </span>
                                    @endif
                                    @if($group->scheme)
                                        <span class="custom-badge badge-forming">{{ $group->scheme->name }}</span>
                                    @endif

                                    @if($wonPayout)
                                        <span class="custom-badge badge-paid" style="background: #dcfce7; color: #166534; font-weight: 700;">
                                            <i class="ri-trophy-fill text-warning"></i> Won (Month #{{ $wonPayout->month_number }}) - Settled
                                        </span>
                                    @elseif($pendingPayout)
                                        <span class="custom-badge badge-pending" style="background: #fef3c7; color: #92400e; font-weight: 700;">
                                            <i class="ri-history-line"></i> Settlement Requested (Month #{{ $pendingPayout->month_number }})
                                        </span>
                                    @elseif($wonAuction)
                                        <span class="custom-badge badge-paid" style="background: #dcfce7; color: #166534; font-weight: 700;">
                                            <i class="ri-trophy-line text-warning"></i> Won Auction (Month #{{ $wonAuction->month_number }})
                                        </span>
                                    @endif
                                </div>
                                <div class="group-meta mt-1">
                                    Member #{{ $membership->member_number ?? '—' }}
                                    &bull; {{ $groupPaid }}/{{ $groupTotal }} paid
                                    @if($mSharePctChit < 100)
                                        &bull; Share of Chit: <strong>₹{{ number_format($membership->client_chit_value ?? round(($group->chit_value ?? 0) * ($mSharePctChit / 100), 2), 2) }}</strong> ({{ rtrim(rtrim(number_format($mSharePctChit, 2), '0'), '.') }}% of ₹{{ number_format($group->chit_value ?? 0, 2) }})
                                    @endif
                                    @if($groupBalance > 0)
                                        &bull; <span style="color: var(--danger); font-weight: 600;">₹{{ number_format($groupBalance, 2) }} due</span>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <span class="custom-badge badge-{{ $group->status === 'active' ? 'active' : 'forming' }}">
                                    <i class="ri-{{ $group->status === 'active' ? 'checkbox-circle-fill' : 'loader-4-fill' }}"></i>
                                    {{ ucfirst($group->status) }}
                                </span>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table-custom">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Due Date</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">Penalty</th>
                                        <th class="text-end">Paid</th>
                                        <th class="text-end">Dividend</th>
                                        <th class="text-end">Payout Amount</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($memberInstallments as $inst)
                                        @php
                                            $status = $inst->display_status;
                                            $badgeClass = match($status) {
                                                'paid' => 'badge-paid',
                                                'overdue' => 'badge-overdue',
                                                'partial' => 'badge-partial',
                                                default => 'badge-pending',
                                            };
                                            $statusIcon = match($status) {
                                                'paid' => 'ri-checkbox-circle-fill',
                                                'overdue' => 'ri-error-warning-fill',
                                                'partial' => 'ri-pie-chart-fill',
                                                default => 'ri-time-fill',
                                            };

                                            $monthPayout = $payoutsByMonth->get($inst->month_number);
                                            $monthAuction = $auctionsByMonth->get($inst->month_number);
                                            $dispInstAmtChit = isset($inst->client_share_amount) ? (float)$inst->client_share_amount : (float)$inst->amount;
                                            $dispInstPaidChit = isset($inst->client_share_paid) ? (float)$inst->client_share_paid : (float)$inst->paid_amount;
                                            $instSharePctChit = (float) ($inst->ownership_percentage ?? $membership?->effective_share_percentage ?? 100);
                                            $instDivAmtChit = ($group && $membership)
                                                ? $group->getMonthlyDividendAmount((int)$inst->month_number, $membership, (int)$client->id)
                                                : 0;
                                        @endphp
                                        <tr data-status="{{ $status }}">
                                            <td class="fw-bold">
                                                Month {{ $inst->month_number }}
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
                                            <td>
                                                <span class="d-inline-flex align-items-center gap-1">
                                                    <i class="ri-calendar-line" style="color: var(--slate);"></i>
                                                    {{ $inst->due_date ? $inst->due_date->format('d-m-Y') : '—' }}
                                                </span>
                                            </td>
                                            <td class="text-end fw-semibold">
                                                ₹{{ number_format($dispInstAmtChit, 2) }}
                                                @if($instSharePctChit < 100)
                                                    <small class="d-block text-muted" style="font-size: 0.7rem; font-weight: normal;">({{ rtrim(rtrim(number_format($instSharePctChit, 2), '0'), '.') }}% share)</small>
                                                @endif
                                            </td>
                                            <td class="text-end {{ $inst->penalty_amount > 0 ? '' : 'text-muted' }}" style="{{ $inst->penalty_amount > 0 ? 'color: var(--danger);' : '' }}">
                                                {{ $inst->penalty_amount > 0 ? '₹'.number_format($inst->penalty_amount, 2) : '—' }}
                                            </td>
                                            <td class="text-end {{ $dispInstPaidChit > 0 ? '' : 'text-muted' }}" style="{{ $dispInstPaidChit > 0 ? 'color: var(--success); font-weight: 600;' : '' }}">
                                                {{ $dispInstPaidChit > 0 ? '₹'.number_format($dispInstPaidChit, 2) : '—' }}
                                            </td>
                                            <td class="text-end fw-semibold text-warning">
                                                {{ $instDivAmtChit > 0 ? '₹' . number_format($instDivAmtChit, 2) : '—' }}
                                                @if($instDivAmtChit > 0 && $instSharePctChit < 100)
                                                    <small class="d-block text-muted" style="font-size: 0.7rem; font-weight: normal;">({{ rtrim(rtrim(number_format($instSharePctChit, 2), '0'), '.') }}% share)</small>
                                                @endif
                                            </td>
                                            @php
                                                // If real payout/auction record exists, use it directly (already correct for this member).
                                                // Only apply amountForClient() scaling for estimated scheduled amounts.
                                                if ($monthPayout?->payout_amount) {
                                                    $rowPayoutAmtChit = (float) $monthPayout->payout_amount;
                                                } elseif ($monthAuction?->payout_amount) {
                                                    $rowPayoutAmtChit = (float) $monthAuction->payout_amount;
                                                } else {
                                                    $scheduledAmtChit = $group->getScheduledPayoutAmountForMonth($inst->month_number) ?? (float)$group->chit_value;
                                                    $rowPayoutAmtChit = $membership ? $membership->amountForClient((float)$scheduledAmtChit, (int)$client->id) : (float)$scheduledAmtChit;
                                                }
                                            @endphp
                                            <td class="text-end fw-semibold text-primary">
                                                {{ $rowPayoutAmtChit ? '₹' . number_format($rowPayoutAmtChit, 2) : '—' }}
                                                @if($instSharePctChit < 100)
                                                    <small class="d-block text-muted" style="font-size: 0.7rem; font-weight: normal;">({{ rtrim(rtrim(number_format($instSharePctChit, 2), '0'), '.') }}% share)</small>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="custom-badge {{ $badgeClass }}">
                                                    <i class="{{ $statusIcon }}"></i>
                                                    {{ ucfirst($status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5 text-muted">
                        <i class="ri-group-line" style="font-size: 3rem; display: block; margin-bottom: 1rem; color: var(--border);"></i>
                        <p class="mb-0">No chit group enrollments found.</p>
                    </div>
                @endforelse
            </div>
        </section>

        @php
            $chitCompany = \App\Models\CompanyDetail::first();
            $chitCompanyMobile = $chitCompany->company_mobile ?? '';
        @endphp
        <footer class="footer-custom">
            <div class="footer-logo">
                @if($chitLogoUrl)
                    <img src="{{ $chitLogoUrl }}" alt="Logo" style="height: 28px; border-radius: 6px;">
                @else
                    <i class="ri-hand-coin-line" style="color: var(--primary);"></i>
                @endif
                <span>{{ \App\Helpers\SettingsHelper::get('admin_subtitle', 'Finance Made Simple') }}</span>
            </div>
            <p class="mb-1">&copy; {{ date('Y') }} {{ \App\Helpers\SettingsHelper::get('admin_title', $chitCompany->company_name ?? config('app.name', 'Codepluse Gen PVT Ltd')) }}. All rights reserved.</p>
            @if($chitCompanyMobile)
                <p class="mb-1"><i class="ri-phone-line me-1"></i>Contact: <a href="tel:{{ $chitCompanyMobile }}" style="color: var(--primary); font-weight: 600;">{{ $chitCompanyMobile }}</a></p>
            @endif
            <p>For inquiries or support, please contact your authorized agent or reach out to our service office.</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const tableRows = document.querySelectorAll('.table-custom tbody tr');
            const groupCards = document.querySelectorAll('.group-card');

            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    const filterValue = this.getAttribute('data-filter');

                    tableRows.forEach(row => {
                        const rowStatus = row.getAttribute('data-status');
                        if (filterValue === 'all') {
                            row.style.display = '';
                        } else if (rowStatus === filterValue) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });

                    groupCards.forEach(card => {
                        const visibleRows = card.querySelectorAll('tbody tr:not([style*="display: none"])');
                        card.style.display = visibleRows.length > 0 ? '' : 'none';
                    });
                });
            });
        });
    </script>
</body>
</html>
