<p>{{ __('Hello,') }}</p>
<p>{{ __('Please find attached the credit / CIBIL report for :name.', ['name' => $row->applicant_name]) }}</p>
<p>
  <strong>{{ __('Score') }}:</strong> {{ $row->score ?? '—' }}<br>
  <strong>{{ __('Rating') }}:</strong> {{ $row->rating ?? '—' }}
</p>
<p class="text-muted small">{{ __('Generated') }}: {{ $row->created_at->toDateTimeString() }}</p>
