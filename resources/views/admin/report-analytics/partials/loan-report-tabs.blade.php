@php
  $activeTab = $activeTab ?? 'clients';
  $tabQuery = request()->except('page');
@endphp
<div class="row mb-2">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h4 class="mb-1 text-primary"><i class="ri-pie-chart-2-line me-2"></i>Loan Reports & Analytics</h4>
        <p class="text-muted mb-0">Tab-wise portfolio insights — clients, applications, loan accounts, and EMI collections.</p>
      </div>
    </div>
  </div>
</div>

<ul class="nav nav-pills mb-4 gap-2 flex-wrap" role="tablist">
  <li class="nav-item">
    <a class="nav-link {{ $activeTab === 'clients' ? 'active' : '' }}"
       href="{{ route('reports-loan', array_merge($tabQuery, ['tab' => 'clients'])) }}">
      <i class="ri-group-line me-1"></i> Clients
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ $activeTab === 'applications' ? 'active' : '' }}"
       href="{{ route('reports-loan', array_merge($tabQuery, ['tab' => 'applications'])) }}">
      <i class="ri-file-list-3-line me-1"></i> Applications
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ $activeTab === 'loans' ? 'active' : '' }}"
       href="{{ route('reports-loan', array_merge($tabQuery, ['tab' => 'loans'])) }}">
      <i class="ri-bank-line me-1"></i> Loans
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ $activeTab === 'emi' ? 'active' : '' }}"
       href="{{ route('reports-loan', array_merge($tabQuery, ['tab' => 'emi'])) }}">
      <i class="ri-calendar-check-line me-1"></i> EMI
    </a>
  </li>
</ul>