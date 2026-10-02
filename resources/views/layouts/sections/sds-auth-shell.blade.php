<div @if(!empty($isLockOverlay)) id="sds-session-lock" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="sds-lock-title" @endif class="authentication-wrapper{{ !empty($isLockOverlay) ? ' sds-lock-overlay' : '' }}">

  <span class="sds-blob sds-blob--a" aria-hidden="true"></span>
  <span class="sds-blob sds-blob--b" aria-hidden="true"></span>

  {{-- dark navy panel + curved edge --}}
  <div class="sds-dark" aria-hidden="true">
    <span class="sds-dark-glow"></span>
    <span class="sds-dark-circle"></span>
    <span class="sds-dark-dots"></span>
  </div>
  <div class="sds-dark-curve" aria-hidden="true">
    <svg viewBox="0 0 100 1000" preserveAspectRatio="none">
      <path d="M100,0 C50,130 2,215 2,360 C2,500 72,520 95,620 C100,660 99,850 100,1000 Z" fill="#142d4c"></path>
      <path d="M100,0 C62,145 20,230 20,370 C20,505 82,530 98,630 C100,670 100,850 100,1000 Z" fill="#10243f"></path>
    </svg>
  </div>

  {{-- phone standing on the marble plinth --}}
  <div class="sds-hero" aria-hidden="true">
    <span class="sds-hero-scene"></span>

    <div class="sds-phone">
      <div class="sds-phone-frame">
        <div class="sds-phone-screen">
          <div class="sds-app-status">
            <span>9:41</span>
            <span><i class="ri-signal-wifi-fill"></i> <i class="ri-battery-fill"></i></span>
          </div>

          <div class="sds-app-stage">
            {{-- 1. Home --}}
            <div class="sds-app-view is-active" data-view="home">
              <div class="sds-app-top">
                <div class="sds-app-hello">Good morning<strong>Kannan</strong></div>
                <i class="ri-notification-3-line"></i>
              </div>

              <div class="sds-balance">
                <div class="sds-balance-label">Total Balance</div>
                <div class="sds-balance-amount">₹ 1,25,480</div>
                <div class="sds-balance-meta">
                  <span><i class="ri-arrow-up-line"></i> 12.4%</span>
                  <span>₹ 12,815</span>
                </div>
              </div>

              <div class="sds-app-actions">
                <span class="sds-app-action" data-app-action="chit"><i class="ri-group-line"></i>Chit</span>
                <span class="sds-app-action"><i class="ri-bank-line"></i>Loan</span>
                <span class="sds-app-action"><i class="ri-safe-2-line"></i>FD</span>
                <span class="sds-app-action"><i class="ri-bank-card-line"></i>Cards</span>
              </div>

              <div class="sds-app-section">Recent Activity <span>See all</span></div>
              <div class="sds-activity">
                <div class="sds-activity-row">
                  <i class="ri-hand-coin-line t-orange"></i>
                  <div>
                    <div class="sds-activity-name">Chit Due · M8</div>
                    <div class="sds-activity-date">Today</div>
                  </div>
                  <span class="sds-activity-amt wait">₹5,000</span>
                </div>
                <div class="sds-activity-row">
                  <i class="ri-bank-line t-green"></i>
                  <div>
                    <div class="sds-activity-name">Loan Disbursal</div>
                    <div class="sds-activity-date">12 Sep</div>
                  </div>
                  <span class="sds-activity-amt up">+ ₹50,000</span>
                </div>
                <div class="sds-activity-row">
                  <i class="ri-hand-coin-line t-blue"></i>
                  <div>
                    <div class="sds-activity-name">EMI Received</div>
                    <div class="sds-activity-date">10 Sep</div>
                  </div>
                  <span class="sds-activity-amt up">+ ₹12,500</span>
                </div>
              </div>
            </div>

            {{-- 2. Pay chit due --}}
            <div class="sds-app-view" data-view="pay">
              <div class="sds-app-top">
                <i class="ri-arrow-left-s-line"></i>
                <span style="font-size: calc(var(--u) * 0.48); font-weight: 700; color: var(--ink);">Pay Due</span>
                <i class="ri-more-2-fill"></i>
              </div>

              <div class="sds-due-card">
                <div class="sds-due-kicker">Chit collection</div>
                <div class="sds-due-title">Group GR-2026-04</div>
                <div class="sds-due-meta">Installment #8 · Due today</div>
                <div class="sds-due-amt">₹ 5,000</div>
                <div class="sds-due-progress" aria-hidden="true"><span></span></div>
                <div class="sds-due-meta">3 of 5 months paid</div>
              </div>

              <div class="sds-activity">
                <div class="sds-activity-row">
                  <i class="ri-calendar-check-line t-blue"></i>
                  <div>
                    <div class="sds-activity-name">Weekly split</div>
                    <div class="sds-activity-date">Week 2 of 4</div>
                  </div>
                  <span class="sds-activity-amt wait">₹1,250</span>
                </div>
              </div>

              <div class="sds-pay-btn"><i class="ri-secure-payment-line" style="margin-right: 4px;"></i> Pay now</div>
            </div>

            {{-- 3. Success --}}
            <div class="sds-app-view" data-view="done">
              <div class="sds-success">
                <span class="sds-success-mark"><i class="ri-check-line"></i></span>
                <h4>Payment sent</h4>
                <strong>₹ 5,000</strong>
                <p>Chit Inst #8 collected.<br>Status: In progress</p>
              </div>
              <div class="sds-activity">
                <div class="sds-activity-row">
                  <i class="ri-checkbox-circle-line t-green"></i>
                  <div>
                    <div class="sds-activity-name">Awaiting verify</div>
                    <div class="sds-activity-date">Just now</div>
                  </div>
                  <span class="sds-activity-amt wait">Agent</span>
                </div>
              </div>
            </div>
          </div>

          <div class="sds-app-tabs">
            <span class="sds-app-tab is-active"><i class="ri-home-5-fill"></i>Home</span>
            <span class="sds-app-tab"><i class="ri-secure-payment-line"></i>Pay</span>
            <span class="sds-app-tab"><i class="ri-user-3-line"></i>Profile</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="sds-shell">

    {{-- ══════════ LEFT ══════════ --}}
    <div class="sds-left">

      <div class="sds-brand">
        <span class="sds-brand-mark">
          @include('_partials.macros', ['width' => 40, 'height' => 40])
        </span>
        <span>
          <span class="sds-brand-name">{{ $adminTitle }}</span>
          <span class="sds-brand-sub">{{ $adminSubtitle ?: 'Fintech Portal' }}</span>
        </span>
      </div>

      <div class="sds-left-body">
        <div class="sds-copy">
          <div class="sds-kicker">Smart Fintech Solutions</div>

          <h1 class="sds-headline">
            Your Financial<br>
            <span class="accent">Growth</span> Partner
          </h1>

          <p class="sds-sub">
            Secure, simple and reliable fintech solutions to help you manage your
            finances, grow your business and achieve your goals.
          </p>

          <div class="sds-features">
            <div class="sds-feature">
              <span class="sds-feature-icon is-blue"><i class="ri-shield-check-line"></i></span>
              <div>
                <div class="sds-feature-title">Secure Access</div>
                <p class="sds-feature-text">Your data is always protected.</p>
              </div>
            </div>
            <div class="sds-feature">
              <span class="sds-feature-icon is-green"><i class="ri-line-chart-line"></i></span>
              <div>
                <div class="sds-feature-title">Trusted Platform</div>
                <p class="sds-feature-text">Built for your financial growth.</p>
              </div>
            </div>
            <div class="sds-feature">
              <span class="sds-feature-icon is-purple"><i class="ri-flashlight-line"></i></span>
              <div>
                <div class="sds-feature-title">Fast Transactions</div>
                <p class="sds-feature-text">Quick and hassle-free service.</p>
              </div>
            </div>
            <div class="sds-feature">
              <span class="sds-feature-icon is-orange"><i class="ri-customer-service-2-line"></i></span>
              <div>
                <div class="sds-feature-title">24/7 Support</div>
                <p class="sds-feature-text">We're always here for you.</p>
              </div>
            </div>
          </div>

          <span class="sds-script">
            Smarter Finance<br>Brighter Future
            <svg class="sds-script-underline" viewBox="0 0 122 8" aria-hidden="true">
              <path d="M2 6C22 2.5 62 1.5 120 4"></path>
            </svg>
          </span>
        </div>
      </div>

      <p class="sds-copyright">&copy; {{ date('Y') }} {{ $adminTitle }}. All rights reserved.</p>
    </div>

    {{-- ══════════ RIGHT ══════════ --}}
    <div class="sds-right">
      <div class="sds-card">

        <div class="sds-card-head">
          <div class="sds-card-brand">
            <span class="sds-brand-mark">
              @include('_partials.macros', ['width' => 34, 'height' => 34])
            </span>
            <span>
              <span class="sds-brand-name">{{ $adminTitle }}</span>
              <span class="sds-brand-sub">{{ $adminSubtitle ?: 'Fintech Portal' }}</span>
            </span>
          </div>
          <span class="sds-secure-pill"><i class="ri-shield-check-fill"></i> Secure Portal</span>
        </div>

        @if(!empty($isLockOverlay))
          @include('layouts.sections.session-lock-form')
        @else
          @yield('auth-card')
        @endif

        <p class="sds-card-foot"><i class="ri-lock-2-line"></i> Your security is our priority</p>
      </div>
    </div>
  </div>

  @unless(!empty($isLockOverlay))
  @include('_partials._modals.modal-credit-check')
  @endunless

  <div class="sds-policy">
    <a href="{{ route('public.privacy-policy') }}">Privacy Policy</a>
    <span class="sep">|</span>
    <a href="{{ route('public.terms-and-conditions') }}">Terms &amp; Conditions</a>
    <span class="sep">|</span>
    <a href="{{ route('public.account-deletion') }}">Account Deletion</a>
  </div>

</div>
