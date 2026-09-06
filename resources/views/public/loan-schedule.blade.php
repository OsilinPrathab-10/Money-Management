<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Helpers\SettingsHelper::get('admin_title', config('app.name', 'Shanmuga Finance')) }} - Repayment Schedule</title>
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

        /* Glassmorphism Background Effect */
        .bg-decorations {
            position: absolute;
            width: 100%;
            height: 380px;
            top: 0;
            left: 0;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
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

        /* Top Brand Header */
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

        /* Premium Cards */
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
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Hero / Overview Card */
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-top: 1.75rem;
            padding-top: 1.75rem;
            border-top: 1px solid var(--border);
        }

        .stat-item {
            display: flex;
            flex-direction: column;
        }

        .stat-item .stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--slate);
            text-transform: uppercase;
            letter-spacing: 0.75px;
            margin-bottom: 0.35rem;
        }

        .stat-item .stat-value {
            font-size: 1.35rem;
            font-weight: 750;
            color: var(--dark);
            font-family: 'Outfit', sans-serif;
        }

        /* Progress Bar styles */
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
            background: linear-gradient(90deg, var(--primary) 0%, #818cf8 100%);
            transition: width 1s ease-in-out;
        }

        /* Interactive Filter Tabs */
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
        }

        .filter-btn:hover {
            color: var(--primary);
            border-color: var(--primary);
        }

        .filter-btn.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
        }

        /* Schedule Details Table */
        .table-responsive {
            margin: 0;
            padding: 0;
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
            padding: 1.1rem 1.75rem;
        }

        .table-custom td {
            padding: 1.1rem 1.75rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
            font-size: 0.92rem;
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

        /* Custom Badges */
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

        .badge-paid {
            background-color: var(--success-light);
            color: #065f46;
        }

        .badge-pending {
            background-color: var(--warning-light);
            color: #92400e;
        }

        .badge-overdue {
            background-color: var(--danger-light);
            color: #991b1b;
        }

        .badge-unverified {
            background-color: var(--info-light);
            color: #155e75;
        }

        /* Action Buttons */
        .action-btn-group {
            display: flex;
            gap: 10px;
        }

        .btn-premium {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-premium:hover {
            background-color: var(--primary-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        }

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
        }

        .btn-premium-outline:hover {
            color: var(--dark);
            border-color: var(--slate);
            background: #f8fafc;
        }

        /* Footer styling */
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

        /* Responsive Mobile Layout */
        @media (max-width: 768px) {
            .hero-overview {
                padding: 1.5rem;
            }
            .brand-header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
            .secure-badge {
                align-self: flex-start;
            }
            .stat-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.25rem;
                padding-top: 1.25rem;
                margin-top: 1.25rem;
            }
            .filter-tabs {
                padding: 1rem;
            }
            .table-custom th {
                padding: 0.9rem 1rem;
            }
            .table-custom td {
                padding: 0.9rem 1rem;
            }
        }

        /* Print Override */
        @media print {
            body {
                background: white;
                color: black;
                padding: 0;
            }
            .bg-decorations, .bg-circle-1, .bg-circle-2, .filter-tabs, .btn-premium, .btn-premium-outline, .secure-badge {
                display: none !important;
            }
            .container {
                max-width: 100%;
                margin-top: 0;
            }
            .brand-logo {
                color: black !important;
            }
            .brand-logo i {
                color: black !important;
                background: none !important;
                border: none !important;
            }
            .premium-card {
                border: none !important;
                box-shadow: none !important;
                margin-bottom: 1rem !important;
            }
            .table-custom th {
                background: #f1f5f9 !important;
                color: black !important;
                border-bottom: 2px solid black !important;
            }
            .table-custom td {
                border-bottom: 1px solid #ddd !important;
            }
        }
    </style>
</head>
<body>
    <!-- Decorative Gradients -->
    <div class="bg-decorations">
        <div class="bg-circle-1"></div>
        <div class="bg-circle-2"></div>
    </div>

    @php
        $isKandhuvatti = ($loan->loan_mode ?? 'emi') === 'interest_only';
        
        // Calculations for progress
        $totalEmis = $loan->emis->count();
        $paidEmis = $loan->emis->where('status', 'paid')->count();
        $progressPercent = $totalEmis > 0 ? round(($paidEmis / $totalEmis) * 100) : 0;
        
        $totalPaidAmount = $loan->emis->where('status', 'paid')->sum('total_amount');
        
        // Setup status mapping
        $statusColors = [
            'active' => 'badge-paid',
            'closed' => 'badge-paid',
            'pending' => 'badge-pending',
            'overdue' => 'badge-overdue'
        ];
        $loanStatusClass = $statusColors[strtolower($loan->status)] ?? 'badge-pending';
    @endphp

    <div class="container">
        <!-- Top Bar -->
        <header class="brand-header">
            <a href="#" class="brand-logo align-items-center gap-1">
                <i class="ri-hand-coin-line m-0"></i>
                <span class="ms-2" style="font-family: 'Outfit', sans-serif; font-size: 1.35rem; font-weight: 750; color: white; letter-spacing: -0.25px;">{{ \App\Helpers\SettingsHelper::get('admin_subtitle', 'Finance Made Simple') }}</span>
            </a>
            <div class="secure-badge">
                <i class="ri-shield-check-fill"></i>
                <span>SECURE PAYMENT PORTAL</span>
            </div>
        </header>

        <!-- Loan Overview Section -->
        <section class="premium-card hero-overview">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="client-avatar">
                        {{ strtoupper(substr($loan->client->client_name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h3 class="mb-0">{{ $loan->client->client_name }}</h3>
                            <span class="custom-badge {{ $loanStatusClass }}">{{ $loan->status }}</span>
                        </div>
                        <p class="text-muted mb-0 mt-1">
                            <i class="ri-wallet-3-line me-1"></i>
                            {{ $loan->loanApplication->product->loan_name ?? 'Personal Loan' }} &bull; Account: <strong>{{ $loan->account_number }}</strong>
                        </p>
                    </div>
                </div>
                
                <div class="action-btn-group">
                    <button onclick="window.print()" class="btn-premium-outline">
                        <i class="ri-printer-line"></i>
                        <span>Print</span>
                    </button>
                </div>
            </div>

            <!-- Progress Tracker -->
            <div class="progress-container">
                <div class="progress-label-wrap">
                    <span class="text-muted">Repayment Progress</span>
                    <span class="text-primary">{{ $progressPercent }}% Completed ({{ $paidEmis }}/{{ $totalEmis }} Installments)</span>
                </div>
                <div class="progress-custom">
                    <div class="progress-bar-custom" style="width: {{ $progressPercent }}%"></div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stat-grid">
                <div class="stat-item">
                    <span class="stat-label">Paid Amount</span>
                    <span class="stat-value text-success">₹{{ number_format($totalPaidAmount, 2) }}</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Outstanding Due</span>
                    <span class="stat-value text-danger">₹{{ number_format($loan->outstanding_amount, 2) }}</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Repayment Mode</span>
                    <span class="stat-value text-capitalize">{{ $isKandhuvatti ? 'Interest Only' : 'Regular EMI' }}</span>
                </div>
            </div>
        </section>

        <!-- Repayment Schedule List -->
        <section class="premium-card">
            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <button class="filter-btn active" data-filter="all">
                    <i class="ri-list-check"></i> All ({{ $totalEmis }})
                </button>
                <button class="filter-btn" data-filter="paid">
                    <i class="ri-checkbox-circle-line"></i> Paid ({{ $paidEmis }})
                </button>
                <button class="filter-btn" data-filter="pending">
                    <i class="ri-time-line"></i> Pending ({{ $totalEmis - $paidEmis }})
                </button>
            </div>

            <div class="table-responsive">
                <table class="table-custom" id="scheduleTable">
                    <thead>
                        <tr>
                            <th>{{ $isKandhuvatti ? 'Cycle' : 'Inst. No' }}</th>
                            <th>Due Date</th>
                            <th class="text-end">{{ $isKandhuvatti ? 'Total Due' : 'EMI Amount' }}</th>
                            <th class="text-center" style="width: 150px;">Status</th>
                            <th>Paid On</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($loan->emis->sortBy('instalment_number') as $emi)
                            @php
                                $emiStatus = strtolower($emi->status);
                                $hasInProgress = $emi->collections->where('status', 'in_progress')->isNotEmpty();
                                $finalStatus = ($hasInProgress && $emiStatus != 'paid') ? 'unverified' : $emiStatus;
                                
                                $badgeMap = [
                                    'paid' => 'badge-paid',
                                    'pending' => 'badge-pending',
                                    'overdue' => 'badge-overdue',
                                    'unverified' => 'badge-unverified'
                                ];
                                
                                $iconMap = [
                                    'paid' => 'ri-checkbox-circle-fill',
                                    'pending' => 'ri-time-fill',
                                    'overdue' => 'ri-error-warning-fill',
                                    'unverified' => 'ri-loader-4-fill'
                                ];
                                
                                $labelMap = [
                                    'paid' => 'Paid',
                                    'pending' => 'Pending',
                                    'overdue' => 'Overdue',
                                    'unverified' => 'Verifying'
                                ];
                                
                                $badgeClass = $badgeMap[$finalStatus] ?? 'badge-pending';
                                $statusIcon = $iconMap[$finalStatus] ?? 'ri-time-fill';
                                $statusLabel = $labelMap[$finalStatus] ?? ucfirst($emi->status);
                            @endphp
                            <tr data-status="{{ $emiStatus == 'paid' ? 'paid' : 'pending' }}">
                                <td class="fw-bold text-dark">#{{ $emi->instalment_number }}</td>
                                <td>
                                    <span class="d-inline-flex align-items-center gap-1.5">
                                        <i class="ri-calendar-line text-slate"></i>
                                        {{ $emi->due_date->format('d-m-Y') }}
                                    </span>
                                </td>
                                <td class="fw-bold text-end text-dark">₹{{ number_format($emi->total_amount, 2) }}</td>
                                <td class="text-center">
                                    <span class="custom-badge {{ $badgeClass }}">
                                        <i class="{{ $statusIcon }}"></i>
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="text-slate">
                                    @if($emi->paid_date)
                                        <span class="d-inline-flex align-items-center gap-1">
                                            <i class="ri-checkbox-circle-line text-success"></i>
                                            {{ $emi->paid_date->format('d-m-Y') }}
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Footer -->
        <footer class="footer-custom">
            <div class="footer-logo">
                <i class="ri-hand-coin-line text-primary"></i>
                <span>{{ \App\Helpers\SettingsHelper::get('admin_subtitle', 'Finance Made Simple') }}</span>
            </div>
            <p class="mb-1">&copy; {{ date('Y') }} {{ \App\Helpers\SettingsHelper::get('admin_title', config('app.name', 'Shanmuga Finance')) }}. All rights reserved.</p>
            <p class="text-slate-400">For inquiries or support, please contact your authorized agent or reach out to our service office.</p>
            <p class="text-slate-400">{{ get_setting('company_name', config('app.name')) }} <a href="{{ get_setting('company_website', url('/')) }}" target="_blank" class="text-primary">{{ get_setting('company_website', url('/')) }}</a></p>
        </footer>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Interactive Filtering
            const filterButtons = document.querySelectorAll('.filter-btn');
            const tableRows = document.querySelectorAll('#scheduleTable tbody tr');

            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Toggle active button class
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    const filterValue = this.getAttribute('data-filter');

                    tableRows.forEach(row => {
                        const rowStatus = row.getAttribute('data-status');
                        
                        if (filterValue === 'all') {
                            row.style.display = '';
                        } else if (filterValue === 'paid' && rowStatus === 'paid') {
                            row.style.display = '';
                        } else if (filterValue === 'pending' && rowStatus === 'pending') {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>
