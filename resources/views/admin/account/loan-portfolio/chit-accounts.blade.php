@extends('layouts/layoutMaster')

@section('title', __('Chit accounts — Accounting'))

@section('content')
<div class="account-module">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">{{ __('Chit accounts') }}</h4>
      <p class="text-muted mb-0">{{ __('Active / completed / defaulted memberships from chit groups') }}</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <a href="{{ route('chit.accounts.index') }}" class="btn btn-sm btn-outline-primary">{{ __('Full chit accounts') }}</a>
      <a href="{{ route('account.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Account dashboard') }}</a>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <form method="get" action="{{ route('account.chit-accounts.index') }}" class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">{{ __('Search') }}</label>
          <input type="text" name="search" value="{{ request('search') }}" class="form-control"
            placeholder="{{ __('Group code, member #, client…') }}">
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ __('Status') }}</label>
          <select name="status" class="form-select no-search">
            <option value="">{{ __('All') }}</option>
            @foreach (['active', 'completed', 'defaulted'] as $st)
              <option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Per page') }}</label>
          <select name="per_page" class="form-select no-search">
            @foreach ([10, 15, 20, 25, 50] as $n)
              <option value="{{ $n }}" @selected((int) request('per_page', 20) === $n)>{{ $n }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-primary me-2">{{ __('Filter') }}</button>
          <a href="{{ route('account.chit-accounts.index') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive text-nowrap">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>{{ __('Member #') }}</th>
            <th>{{ __('Client') }}</th>
            <th>{{ __('Group') }}</th>
            <th>{{ __('Scheme') }}</th>
            <th class="text-end">{{ __('Share %') }}</th>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Joined') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($chitAccounts as $member)
            <tr>
              <td><code>{{ $member->display_member_number ?? $member->member_number }}</code></td>
              <td>
                {{ $member->is_shared ? ($member->owners_display ?? '—') : ($member->client?->client_name ?? '—') }}
                @if($member->client?->client_phone)
                  <small class="text-muted d-block">{{ $member->client->client_phone }}</small>
                @endif
              </td>
              <td>
                @if($member->group)
                  <a href="{{ route('chit.groups.show', $member->group) }}">{{ $member->group->group_code }}</a>
                @else
                  —
                @endif
              </td>
              <td>{{ $member->group?->scheme?->name ?? '—' }}</td>
              <td class="text-end">{{ rtrim(rtrim(number_format((float) ($member->effective_share_percentage ?? 100), 2), '0'), '.') }}%</td>
              <td><span class="badge bg-label-secondary">{{ ucfirst($member->status ?? '—') }}</span></td>
              <td>{{ $member->joined_date ? $member->joined_date->format('Y-m-d') : '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-5">{{ __('No chit accounts found.') }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($chitAccounts->hasPages())
      <div class="card-footer">{{ $chitAccounts->links() }}</div>
    @endif
  </div>
</div>
@endsection
