@if (!empty($reportBranding))
  <div style="display: table; width: 100%; padding-bottom: 12px; margin-bottom: 16px; border-bottom: 2px solid #666cff;">
    <div style="display: table-cell; width: 100px; vertical-align: middle;">
      @if (!empty($reportBranding['logo']))
        <img src="{{ $reportBranding['logo'] }}" alt="Company Logo"
          style="display: block; max-width: 90px; max-height: 55px;">
      @endif
    </div>
    <div style="display: table-cell; vertical-align: middle; text-align: right;">
      <div style="font-size: 18px; font-weight: bold; color: #2f3349;">
        {{ $reportBranding['name'] ?? config('app.name') }}
      </div>
      @if (!empty($reportBranding['slogan']))
        <div style="margin-top: 2px; font-size: 10px; font-style: italic; color: #666cff;">
          {{ $reportBranding['slogan'] }}
        </div>
      @endif
      @if (!empty($reportBranding['address']))
        <div style="margin-top: 4px; font-size: 10px; line-height: 1.45; color: #6d6f85;">
          {{ $reportBranding['address'] }}
        </div>
      @endif
    </div>
  </div>
@endif
