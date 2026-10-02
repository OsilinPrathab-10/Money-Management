@php
    $flyerId       = $flyerId       ?? 'flyerContainer';
    $dynamic       = $dynamic       ?? false;
    $chitValue     = $chitValue     ?? 0;
    $durationMonths= $durationMonths?? 0;
    $totalMembers  = $totalMembers  ?? 0;
    $payoutSchedule= $payoutSchedule?? [];
    $fullPage      = $fullPage      ?? false;
    $rowCount      = $rowCount      ?? max(count($payoutSchedule), 1);
    $schemeName    = $schemeName    ?? null;
    $installmentAmount    = $installmentAmount    ?? 0;
    $commissionPct        = $commissionPct        ?? 0;
    $installmentFrequency = $installmentFrequency ?? 'monthly';

    $adminLogo    = \App\Helpers\SettingsHelper::get('admin_logo');
    $adminTitle   = \App\Helpers\SettingsHelper::get('admin_title');
    $adminSubtitle= \App\Helpers\SettingsHelper::get('admin_subtitle');
    $brandLogo    = $adminLogo ? asset('storage/' . $adminLogo) : null;
    $brandTitle   = trim($adminTitle   ?? config('variables.templateName', 'FINTRONIX CHIT FUNDS'));
    $brandSubtitle= trim($adminSubtitle?? config('variables.templateSuffix', 'Chit Funds & Finance Pvt. Ltd.'));
    $govtApproved = get_setting('company_tagline', 'Government Approved Company');

    $contactLocation = get_setting('company_address', 'Coimbatore, Tamil Nadu');
    $contactEmail    = get_setting('company_email',   get_setting('support_email',   'info@fintronix.com'));
    $contactPhone    = get_setting('company_phone',   get_setting('support_mobile',  '98765 43210'));
    $referralText    = get_setting('referral_bonus_text', 'Refer a member and earn exciting referral bonuses every month!');

    $companySlogan = '';
    if (class_exists(\App\Models\CompanyDetail::class)) {
        $companySlogan = trim((string) (\App\Models\CompanyDetail::first()->company_slogan ?? ''));
    }

    // Show the installment amount saved on the scheme. It is entered manually and must
    // never be re-derived from chit value / duration.
    $instAmt = (float) $installmentAmount;
    $foremanCommissionMonth = (int) ($foremanCommissionMonth ?? 1);
    $clientWiseForemanAmount = (float) ($clientWiseForemanAmount ?? 0);
    $clientWiseForemanCollectionMonth = (int) ($clientWiseForemanCollectionMonth ?? 0);

    $installmentStatLabel = match ($installmentFrequency) {
        'weekly' => 'Weekly Installment',
        'daily'  => 'Daily Installment',
        default  => 'Monthly Installment',
    };
    $periodLabel = match ($installmentFrequency) {
        'weekly' => 'Week',
        'daily'  => 'Day',
        default  => 'Month',
    };
    $periodLabelPlural = match ($installmentFrequency) {
        'weekly' => 'Weeks',
        'daily'  => 'Days',
        default  => 'Months',
    };
@endphp

<div id="{{ $flyerId }}" class="sf-wrap{{ $fullPage ? ' sf-wrap--fullpage' : '' }}">

    {{-- ─── HEADER BAND ─── --}}
    <header class="sf-header">
        <div class="sf-header__top-rule"></div>
        <div class="sf-header__inner">
            <div class="sf-header__brand">
                @if($brandLogo)
                    <img src="{{ $brandLogo }}" alt="{{ $brandTitle }}" class="sf-header__logo">
                @else
                    <div class="sf-header__logo sf-header__logo--text">{{ strtoupper(substr($brandTitle, 0, 2)) }}</div>
                @endif
                <div class="sf-header__names">
                    <div class="sf-header__company">{{ $brandTitle }}</div>
                    @if($companySlogan)
                        <div class="sf-header__slogan">{{ $companySlogan }}</div>
                    @else
                        <div class="sf-header__slogan">{{ $brandSubtitle }}</div>
                    @endif
                    <div class="sf-header__tag">✦ {{ $govtApproved }} ✦</div>
                </div>
            </div>
            <div class="sf-header__right">
                <div class="sf-header__badge">
                    <div class="sf-header__badge-label">CHIT FUND</div>
                    <div class="sf-header__badge-label">SCHEME</div>
                </div>
            </div>
        </div>
        @if($schemeName)
            <div class="sf-header__scheme-name">{{ $schemeName }}</div>
        @endif
    </header>

    {{-- ─── STATS CARDS STRIP ─── --}}
    <div class="sf-stats">
        <div class="sf-stats__card sf-stats__card--primary">
            <div class="sf-stats__icon">₹</div>
            <div class="sf-stats__body">
                <div class="sf-stats__value" @if($dynamic) id="flyerSummaryChitValue" @endif>
                    {{ number_format($chitValue) }}
                </div>
                <div class="sf-stats__label">Chit Value</div>
            </div>
        </div>
        <div class="sf-stats__card sf-stats__card--green">
            <div class="sf-stats__icon">↻</div>
            <div class="sf-stats__body">
                <div class="sf-stats__value" @if($dynamic) id="flyerSummaryDuration" @endif>
                    {{ $durationMonths }}
                </div>
                <div class="sf-stats__label">{{ $periodLabelPlural }} Duration</div>
            </div>
        </div>
        <div class="sf-stats__card sf-stats__card--teal">
            <div class="sf-stats__icon">👥</div>
            <div class="sf-stats__body">
                <div class="sf-stats__value" @if($dynamic) id="flyerSummaryMembers" @endif>
                    {{ $totalMembers }}
                </div>
                <div class="sf-stats__label">Total Members</div>
            </div>
        </div>
        <div class="sf-stats__card sf-stats__card--amber">
            <div class="sf-stats__icon">📅</div>
            <div class="sf-stats__body">
                <div class="sf-stats__value">₹{{ number_format($instAmt, 0) }}</div>
                <div class="sf-stats__label">{{ $installmentStatLabel }}</div>
            </div>
        </div>
    </div>
    @if($clientWiseForemanAmount > 0 && $clientWiseForemanCollectionMonth > 0)
        <div class="sf-stats" style="padding-top:0;">
            <div class="text-center w-100 small" style="opacity:.85;">
                Client-wise foreman commission ₹{{ number_format($clientWiseForemanAmount, 2) }} collected once in month {{ $clientWiseForemanCollectionMonth }} (in addition to the regular installment).
            </div>
        </div>
    @endif

    {{-- ─── BODY: PAYOUT TABLE ─── --}}
    <div class="sf-body">
        <div class="sf-section-title">
            <div class="sf-section-title__line"></div>
            <span>Payout Schedule</span>
            <div class="sf-section-title__line"></div>
        </div>

        <div class="sf-table-wrap">
            <table class="sf-table">
                <colgroup>
                    <col class="sf-col--no">
                    <col class="sf-col--inst">
                    <col class="sf-col--payout">
                </colgroup>
                <thead>
                    <tr>
                        <th>No. <span style="font-weight:400;opacity:.85">({{ $periodLabel }})</span></th>
                        <th>Installment Amount</th>
                        <th>Payout / Disbursement</th>
                    </tr>
                </thead>
                <tbody @if($dynamic) id="flyerScheduleBody" @endif>
                    @if(!$dynamic)
                        @foreach($payoutSchedule as $row)
                            @php
                                $iAmt = $row['installment_amount'] ?? '';
                                $pAmt = $row['payout_amount']      ?? '';
                                $even = $loop->index % 2 === 0;
                                $monthNo = (int) ($row['installment_no'] ?? $loop->iteration);
                                $isForeman = (int) ($foremanCommissionMonth ?? 0) > 0 && $monthNo === (int) $foremanCommissionMonth;
                                $displayInstAmt = is_numeric($iAmt) && (float)$iAmt > 0 ? (float)$iAmt : ($instAmt > 0 ? $instAmt : 0);

                                // Older schedules stored the whole chit value on the foreman row.
                                // Clients pay the normal installment that month, so show that.
                                if ($isForeman && $instAmt > 0 && $chitValue > 0 && $displayInstAmt > $chitValue * 0.9) {
                                    $displayInstAmt = $instAmt;
                                }
                            @endphp
                            <tr class="{{ $even ? 'sf-row--even' : 'sf-row--odd' }}">
                                <td class="sf-td--center sf-td--no">{{ $monthNo }}</td>
                                <td class="sf-td--center">
                                    @if($displayInstAmt > 0)
                                        ₹{{ number_format((float) $displayInstAmt) }}
                                    @elseif(is_numeric($iAmt))
                                        ₹{{ number_format((float) $iAmt) }}
                                    @else
                                        {{ $iAmt }}
                                    @endif
                                </td>
                                <td class="sf-td--center sf-td--payout">
                                    @if($isForeman)
                                        {{ \App\Models\ChitScheme::FOREMAN_COMMISSION_LABEL }}
                                    @elseif(is_numeric($pAmt))
                                        ₹{{ number_format((float) $pAmt) }}
                                    @elseif(!empty($pAmt) && !str_contains($pAmt, 'Foreman Commission'))
                                        {{ $pAmt }}
                                    @else
                                        @php
                                            $membersCount = (int) ($totalMembers ?? 0);
                                            $chitValAmt = (float) ($chitValue ?? 0);
                                            $commPctAmt = (float) ($commissionPct ?? 0);
                                            $calcPayout = max(0, ($displayInstAmt * $membersCount) - ($chitValAmt * $commPctAmt / 100));
                                        @endphp
                                        ₹{{ number_format((float) $calcPayout) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        {{-- Referral highlight --}}
        <div class="sf-referral">
            <span class="sf-referral__icon">🎁</span>
            <span class="sf-referral__text">{{ $referralText }}</span>
        </div>
    </div>

    {{-- ─── FOOTER CONTACT BAR ─── --}}
    <footer class="sf-footer">
        <div class="sf-footer__inner">
            <span class="sf-footer__item">
                <svg class="sf-footer__ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                {{ $contactLocation }}
            </span>
            <span class="sf-footer__dot"></span>
            <span class="sf-footer__item">
                <svg class="sf-footer__ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                {{ $contactEmail }}
            </span>
            <span class="sf-footer__dot"></span>
            <span class="sf-footer__item">
                <svg class="sf-footer__ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 10.8 19.79 19.79 0 012 2.18 2 2 0 014 0h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 14h-.08z"/></svg>
                {{ $contactPhone }}
            </span>
        </div>
        <div class="sf-footer__rule"></div>
    </footer>
</div>

@once
<style id="sf-styles">
/* ─────────────────────────────────────────────────
   SCHEME FLYER  –  Modern Premium Template
───────────────────────────────────────────────── */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700&display=swap');

:root {
    --sf-navy:      #1e2f5c;
    --sf-navy-dk:   #141f3d;
    --sf-blue:      #2563eb;
    --sf-blue-lt:   #dbeafe;
    --sf-green:     #059669;
    --sf-green-lt:  #d1fae5;
    --sf-teal:      #0d9488;
    --sf-teal-lt:   #ccfbf1;
    --sf-amber:     #d97706;
    --sf-amber-lt:  #fef3c7;
    --sf-gold:      #f59e0b;
    --sf-bg:        #f8fafc;
    --sf-surface:   #ffffff;
    --sf-border:    #e2e8f0;
    --sf-text:      #1e293b;
    --sf-muted:     #64748b;
}

.sf-wrap {
    max-width: 680px;
    margin: 0 auto;
    background: var(--sf-bg);
    font-family: 'Inter', 'Segoe UI', sans-serif;
    display: flex;
    flex-direction: column;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 8px 40px rgba(0,0,0,0.13);
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    border: 1px solid var(--sf-border);
}

/* ── HEADER ── */
.sf-header {
    background: linear-gradient(135deg, var(--sf-navy-dk) 0%, var(--sf-navy) 60%, #2a3f7a 100%);
    color: #fff;
    flex-shrink: 0;
    position: relative;
    overflow: hidden;
}

.sf-header::before {
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 200px; height: 200px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(37,99,235,0.25) 0%, transparent 70%);
    pointer-events: none;
}

.sf-header__top-rule {
    height: 4px;
    background: linear-gradient(90deg, var(--sf-gold) 0%, #fbbf24 50%, var(--sf-gold) 100%);
    flex-shrink: 0;
}

.sf-header__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 18px 22px 14px;
}

.sf-header__brand {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    flex: 1;
}

.sf-header__logo {
    width: 62px; height: 62px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255,255,255,0.3);
    background: #fff;
    flex-shrink: 0;
    box-shadow: 0 4px 16px rgba(0,0,0,0.25);
}

.sf-header__logo--text {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--sf-navy);
    background: #fff;
}

.sf-header__names { min-width: 0; }

.sf-header__company {
    font-size: 1.15rem;
    font-weight: 800;
    letter-spacing: 0.5px;
    line-height: 1.2;
    text-transform: uppercase;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sf-header__slogan {
    font-size: 0.72rem;
    font-weight: 500;
    color: rgba(255,255,255,0.8);
    margin-top: 3px;
    font-style: italic;
}

.sf-header__tag {
    font-size: 0.62rem;
    font-weight: 600;
    color: var(--sf-gold);
    margin-top: 4px;
    letter-spacing: 0.4px;
    text-transform: uppercase;
}

.sf-header__right { flex-shrink: 0; }

.sf-header__badge {
    width: 68px; height: 68px;
    border-radius: 50%;
    border: 2px dashed rgba(255,255,255,0.35);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: radial-gradient(circle at 35% 35%, rgba(255,255,255,0.15), rgba(255,255,255,0.05));
    box-shadow: 0 0 0 4px rgba(245,158,11,0.2), inset 0 0 10px rgba(255,255,255,0.05);
}

.sf-header__badge-label {
    font-size: 0.5rem;
    font-weight: 800;
    letter-spacing: 0.8px;
    text-align: center;
    line-height: 1.3;
    color: var(--sf-gold);
    text-transform: uppercase;
}

.sf-header__scheme-name {
    background: rgba(0,0,0,0.25);
    text-align: center;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 1px;
    padding: 7px 22px;
    color: rgba(255,255,255,0.95);
    border-top: 1px solid rgba(255,255,255,0.1);
    text-transform: uppercase;
}

/* ── STATS STRIP ── */
.sf-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0;
    flex-shrink: 0;
    border-bottom: 1px solid var(--sf-border);
}

.sf-stats__card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 12px;
    position: relative;
}

.sf-stats__card + .sf-stats__card {
    border-left: 1px solid rgba(255,255,255,0.25);
}

.sf-stats__card--primary  { background: linear-gradient(135deg, var(--sf-navy) 0%, #2d4a8c 100%); color: #fff; }
.sf-stats__card--green    { background: linear-gradient(135deg, var(--sf-green) 0%, #065f46 100%); color: #fff; }
.sf-stats__card--teal     { background: linear-gradient(135deg, var(--sf-teal) 0%, #134e4a 100%);  color: #fff; }
.sf-stats__card--amber    { background: linear-gradient(135deg, var(--sf-amber) 0%, #92400e 100%); color: #fff; }

.sf-stats__icon {
    font-size: 1.4rem;
    opacity: 0.85;
    flex-shrink: 0;
    line-height: 1;
}

.sf-stats__value {
    font-size: 1.05rem;
    font-weight: 800;
    line-height: 1.15;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sf-stats__label {
    font-size: 0.6rem;
    font-weight: 600;
    opacity: 0.82;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-top: 2px;
    white-space: nowrap;
}

/* ── BODY ── */
.sf-body {
    flex: 1;
    padding: 16px 20px 12px;
    display: flex;
    flex-direction: column;
    background: var(--sf-surface);
}

/* Section title divider */
.sf-section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--sf-navy);
}

.sf-section-title__line {
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--sf-navy), transparent);
    opacity: 0.25;
}

/* ── TABLE ── */
.sf-table-wrap {
    flex: 1;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--sf-border);
    box-shadow: 0 2px 12px rgba(30,47,92,0.06);
}

.sf-table {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
    font-size: 0.8rem;
}

.sf-col--no     { width: 15%; }
.sf-col--inst   { width: 35%; }
.sf-col--payout { width: 50%; }

.sf-table thead tr {
    background: linear-gradient(135deg, var(--sf-navy) 0%, #2d4a8c 100%);
}

.sf-table thead th {
    color: #fff;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 8px;
    text-align: center;
    border: none;
    border-right: 1px solid rgba(255,255,255,0.12);
}

.sf-table thead th:last-child { border-right: none; }

.sf-table tbody td {
    padding: 6px 8px;
    text-align: center;
    font-weight: 600;
    border-bottom: 1px solid var(--sf-border);
    border-right: 1px solid #f1f5f9;
    color: var(--sf-text);
    line-height: 1.25;
}

.sf-table tbody td:last-child { border-right: none; }
.sf-table tbody tr:last-child td { border-bottom: none; }

.sf-td--no     { color: var(--sf-muted); font-weight: 700; }
.sf-td--payout { color: var(--sf-green); font-weight: 700; }
.sf-td--center { text-align: center; }

.sf-row--even td { background: #ffffff; }
.sf-row--odd  td { background: #f0f7ff; }

/* ── REFERRAL BAR ── */
.sf-referral {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 14px;
    padding: 10px 14px;
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    border: 1px dashed var(--sf-amber);
    border-radius: 8px;
    font-size: 0.76rem;
    font-weight: 600;
    color: #92400e;
    flex-shrink: 0;
}

.sf-referral__icon { font-size: 1rem; flex-shrink: 0; }
.sf-referral__text { line-height: 1.4; }

/* ── FOOTER ── */
.sf-footer {
    flex-shrink: 0;
    background: linear-gradient(135deg, var(--sf-navy-dk) 0%, var(--sf-navy) 100%);
}

.sf-footer__rule {
    height: 3px;
    background: linear-gradient(90deg, var(--sf-gold), #fbbf24, var(--sf-gold));
}

.sf-footer__inner {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 0;
    padding: 12px 20px;
    color: rgba(255,255,255,0.9);
    font-size: 0.68rem;
    font-weight: 500;
}

.sf-footer__item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 0 12px;
    white-space: nowrap;
}

.sf-footer__dot {
    width: 1px;
    height: 12px;
    background: rgba(255,255,255,0.2);
    flex-shrink: 0;
}

.sf-footer__ico {
    width: 12px;
    height: 12px;
    stroke: rgba(255,255,255,0.7);
    flex-shrink: 0;
}

/* ── FULL PAGE (print / PDF) ── */
.sf-wrap--fullpage {
    max-width: none;
    width: 100%;
    min-height: 297mm;
    height: 100%;
    border-radius: 0;
    box-shadow: none;
}

.sf-wrap--fullpage .sf-body {
    flex: 1;
    overflow: hidden;
}

.sf-wrap--fullpage .sf-table-wrap {
    flex: 1;
}

@media print {
    .sf-wrap {
        max-width: 100%;
        border-radius: 0;
        box-shadow: none;
        border: none;
    }
}
</style>
@endonce
