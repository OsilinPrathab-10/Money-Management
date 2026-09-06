@extends('layouts/layoutMaster')

@section('title', 'Staff Payroll')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/animate-css/animate.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="card mb-4 no-print">
        <div class="card-body">
            <div class="row align-items-center g-3">
                <div class="col-md-4">
                    <div class="d-flex align-items-center gap-3">
                        <a href="{{ route('staff-management.payroll') }}" class="btn btn-icon btn-outline-primary"><i class="ri-arrow-left-line"></i></a>
                        <h4 class="fw-bold mb-0">Payroll Management</h4>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="d-flex justify-content-md-end align-items-center gap-3">
                        <div class="input-group input-group-merge shadow-sm" style="max-width: 250px;">
                            <span class="input-group-text"><i class="ri-search-line"></i></span>
                            <input type="text" id="staffSearch" class="form-control" placeholder="Search staff name..." onkeyup="searchStaff()">
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <select id="payrollMonth" class="form-select shadow-sm" style="width: 130px;">
                                @foreach(range(1, 12) as $m)
                                    <option value="{{ sprintf('%02d', $m) }}" {{ $month == sprintf('%02d', $m) ? 'selected' : '' }}>
                                        {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                    </option>
                                @endforeach
                            </select>
                            <select id="payrollYear" class="form-select shadow-sm" style="width: 100px;">
                                @foreach(range(date('Y')-1, date('Y')+1) as $y)
                                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endforeach
                            </select>
                             <button class="btn btn-primary shadow-sm" onclick="filterPayroll()">
                                <i class="ri-filter-3-line me-1"></i> Apply Filter
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-bottom">
            <h5 class="card-title mb-0">Monthly Salary Report - {{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</h5>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table table-hover" id="payrollTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">Sl No</th>
                        <th>Staff Name</th>
                        <th>Base Salary</th>
                        <th>Leaves (Deduction)</th>
                        <th>Advances</th>
                        <th>Allowances</th>
                        <th>Net Salary</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payrollData as $data)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><span class="fw-semibold">{{ $data['staff']->name }}</span></td>
                        <td>₹{{ number_format($data['base_salary'], 2) }}</td>
                        <td>
                            <span class="text-danger">
                                {{ $data['absents'] }} Abs / {{ $data['half_days'] }} HD
                                <small class="d-block">(-₹{{ number_format($data['deduction'], 2) }})</small>
                            </span>
                        </td>
                        <td>
                            @if($data['advances'] > 0)
                                <span class="text-danger cursor-pointer" data-bs-toggle="tooltip" data-bs-html="true" title="<div class='text-start'>@foreach($data['advance_list'] as $adv)<strong>{{ $adv['date'] }}:</strong> ₹{{ number_format($adv['amount'],0) }} - {{ $adv['description'] }}<br>@endforeach</div>">
                                    ₹{{ number_format($data['advances'], 2) }}
                                    <i class="ri-information-line small ms-1"></i>
                                </span>
                            @else
                                <span>₹0.00</span>
                            @endif
                        </td>
                        <td>
                            @if($data['expenses'] > 0)
                                <span class="text-success cursor-pointer" data-bs-toggle="tooltip" data-bs-html="true" title="<div class='text-start'>@foreach($data['expense_list'] as $exp)<strong>{{ $exp['date'] }}:</strong> ₹{{ number_format($exp['amount'],0) }} - {{ $exp['description'] }}<br>@endforeach</div>">
                                    +₹{{ number_format($data['expenses'], 2) }}
                                    <i class="ri-information-line small ms-1"></i>
                                    <small class="d-block">
                                        T:₹{{ number_format($data['travel_expenses'], 0) }} | 
                                        P:₹{{ number_format($data['petrol_expenses'], 0) }}
                                    </small>
                                </span>
                            @else
                                <span class="text-muted">₹0.00</span>
                            @endif
                        </td>
                        <td><span class="badge bg-label-success fs-6">₹{{ number_format($data['net_salary'], 2) }}</span></td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <button class="btn btn-sm btn-icon btn-label-info" data-bs-toggle="modal" data-bs-target="#payslipModal" 
                                    data-payslip-info="{{ json_encode($data) }}" title="View Payslip">
                                    <i class="ri-file-list-3-line"></i>
                                </button>
                                <div class="dropdown">
                                    <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                        <i class="ri-more-2-line"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#addAdvanceModal" data-staff-id="{{ $data['staff']->id }}">
                                            <i class="ri-money-dollar-circle-line me-1"></i> Add Advance
                                        </a>
                                        <a class="dropdown-item" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#addExpenseModal" data-staff-id="{{ $data['staff']->id }}">
                                            <i class="ri-hand-coin-line me-1"></i> Add Allowance/Expense
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Advance Modal -->
<div class="modal fade" id="addAdvanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="addAdvanceForm">
                @csrf
                <input type="hidden" name="staff_id" id="advanceStaffId">
                <div class="modal-header">
                    <h5 class="modal-title">Add Salary Advance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-3">
                        <small><i class="ri-information-line me-1"></i> Advances are explicitly deducted from the monthly net salary.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="{{ date('Y-m-d', mktime(0, 0, 0, $month, 1, $year)) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Reason for advance" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Confirm Deduction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="addExpenseForm">
                @csrf
                <input type="hidden" name="staff_id" id="expenseStaffId">
                <div class="modal-header">
                    <h5 class="modal-title">Add Allowance / Reimbursement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category" class="form-select" required>
                            <option value="travel">Travel Allowance</option>
                            <option value="petrol">Petrol Expense</option>
                            <option value="other">Other Allowance</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="{{ date('Y-m-d', mktime(0, 0, 0, $month, 1, $year)) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Expense detail" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success">Add to Earnings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payslip Modal -->
<div class="modal fade" id="payslipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title">Pay Slip - <span id="payslipStaff"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="payslipContent">
                <div class="text-center mb-4">
                    <h4 class="mb-1 fw-bold">SALARY PAY SLIP</h4>
                    <p class="text-muted">{{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</p>
                </div>
                
                <div class="row mb-4">
                    <div class="col-6">
                        <h6 class="fw-bold mb-1">Employee Details:</h6>
                        <p class="mb-0" id="empName"></p>
                        <p class="mb-0 small text-muted" id="empPhone"></p>
                    </div>
                    <div class="col-6 text-end d-none" id="bankInfoSection">
                        <h6 class="fw-bold mb-1">Bank Information:</h6>
                        <p class="mb-0 small" id="bankName"></p>
                        <p class="mb-0 small" id="accNo"></p>
                    </div>
                </div>

                <div class="mb-4 d-none" id="payslipDetailsSection">
                    <h6 class="fw-bold mb-2 small text-primary">Transaction Details:</h6>
                    <div id="payslipDetailsList" class="small">
                        <!-- Individual descriptions will be injected here -->
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-bordered">
                            <thead class="bg-light">
                                <tr><th>Earnings</th><th class="text-end">Amount</th></tr>
                            </thead>
                            <tbody>
                                <tr><td>Basic Salary</td><td class="text-end" id="baseSal"></td></tr>
                                <tr><td>Travel Allowance</td><td class="text-end" id="travelExp"></td></tr>
                                <tr><td>Petrol Expense</td><td class="text-end" id="petrolExp"></td></tr>
                                <tr><td>Other Allowances</td><td class="text-end" id="otherExp"></td></tr>
                                <tr class="fw-bold"><td>Gross Earnings</td><td class="text-end" id="totalEarn"></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-bordered">
                            <thead class="bg-light">
                                <tr><th>Deductions</th><th class="text-end">Amount</th></tr>
                            </thead>
                            <tbody>
                                <tr><td>Leave/Absent Deduction</td><td class="text-end" id="leaveDed"></td></tr>
                                <tr><td>Staff Advances</td><td class="text-end" id="advanceDed"></td></tr>
                                <tr class="fw-bold"><td>Total Deductions</td><td class="text-end" id="totalDed"></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-light rounded d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Net Payable Salary:</h5>
                    <h4 class="mb-0 fw-bold text-primary" id="netPay"></h4>
                </div>
                
                <div class="mt-4 small text-muted text-center border-top pt-3">
                    <p>Computer generated payslip. Signature not required.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printPayslip()">
                    <i class="ri-printer-line me-1"></i> Print Payslip
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('page-script')
<script>
    function filterPayroll() {
        const month = document.getElementById('payrollMonth').value;
        const year = document.getElementById('payrollYear').value;
        window.location.href = `{{ route('admin.staff.payroll') }}?month=${month}&year=${year}`;
    }

    function searchStaff() {
        const input = document.getElementById("staffSearch");
        const filter = input.value.toLowerCase();
        const table = document.getElementById("payrollTable");
        const tr = table.getElementsByTagName("tr");

        for (let i = 1; i < tr.length; i++) {
            const rowText = tr[i].textContent.toLowerCase();
            if (rowText.indexOf(filter) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }

    document.getElementById('addAdvanceModal').addEventListener('show.bs.modal', function(event) {
        document.getElementById('advanceStaffId').value = event.relatedTarget.dataset.staffId;
    });

    document.getElementById('addExpenseModal').addEventListener('show.bs.modal', function(event) {
        document.getElementById('expenseStaffId').value = event.relatedTarget.dataset.staffId;
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });

    document.getElementById('payslipModal').addEventListener('show.bs.modal', function(event) {
        const data = JSON.parse(event.relatedTarget.dataset.payslipInfo);
        const staff = data.staff;
        
        document.getElementById('payslipStaff').innerText = staff.name;
        document.getElementById('empName').innerText = staff.name;
        document.getElementById('empPhone').innerText = staff.phone;
        
        const salDetails = typeof staff.salary_details === 'string' ? JSON.parse(staff.salary_details) : staff.salary_details;
        const bankName = salDetails?.bank_name || '';
        const accNo = salDetails?.account_number || '';
        
        if (bankName || accNo) {
            document.getElementById('bankInfoSection').classList.remove('d-none');
            document.getElementById('bankName').innerText = bankName || 'N/A';
            document.getElementById('accNo').innerText = accNo ? 'Acc No: ' + accNo : '';
        } else {
            document.getElementById('bankInfoSection').classList.add('d-none');
        }

        // Handle Details/Descriptions
        const detailsSection = document.getElementById('payslipDetailsSection');
        const detailsList = document.getElementById('payslipDetailsList');
        detailsList.innerHTML = '';
        
        let hasDetails = false;
        
        if (data.advance_list && data.advance_list.length > 0) {
            hasDetails = true;
            data.advance_list.forEach(adv => {
                detailsList.innerHTML += `<div class="d-flex justify-content-between text-danger mb-1">
                    <span>Advance: ${adv.description || 'No description'} (${adv.date})</span>
                    <span>-₹${parseFloat(adv.amount).toLocaleString()}</span>
                </div>`;
            });
        }
        
        if (data.expense_list && data.expense_list.length > 0) {
            hasDetails = true;
            data.expense_list.forEach(exp => {
                detailsList.innerHTML += `<div class="d-flex justify-content-between text-success mb-1">
                    <span>${exp.category.charAt(0).toUpperCase() + exp.category.slice(1)}: ${exp.description || 'No description'} (${exp.date})</span>
                    <span>+₹${parseFloat(exp.amount).toLocaleString()}</span>
                </div>`;
            });
        }
        
        if (hasDetails) {
            detailsSection.classList.remove('d-none');
        } else {
            detailsSection.classList.add('d-none');
        }

        document.getElementById('baseSal').innerText = '₹' + data.base_salary.toLocaleString();
        document.getElementById('travelExp').innerText = '₹' + data.travel_expenses.toLocaleString();
        document.getElementById('petrolExp').innerText = '₹' + data.petrol_expenses.toLocaleString();
        document.getElementById('otherExp').innerText = '₹' + data.other_expenses.toLocaleString();
        
        const gross = parseFloat(data.base_salary) + parseFloat(data.expenses);
        document.getElementById('totalEarn').innerText = '₹' + gross.toLocaleString();
        
        document.getElementById('leaveDed').innerText = '₹' + data.deduction.toLocaleString();
        document.getElementById('advanceDed').innerText = '₹' + Math.abs(parseFloat(data.advances)).toLocaleString();
        
        const totalDed = parseFloat(data.deduction) + parseFloat(data.advances);
        document.getElementById('totalDed').innerText = '₹' + totalDed.toLocaleString();
        
        document.getElementById('netPay').innerText = '₹' + data.net_salary.toLocaleString();
    });

    // AJAX Form Handling for Advance
    async function handleFormSubmit(formId, route) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

            try {
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());

                const response = await fetch(route, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    // Close modal
                    const modalEl = form.closest('.modal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();

                    // Show success
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: result.message || 'Operation successful',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error', result.message || 'Something went wrong.', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', 'Failed to process request.', 'error');
            }
        });
    }

    handleFormSubmit('addAdvanceForm', "{{ route('admin.staff.addAdvance') }}");
    handleFormSubmit('addExpenseForm', "{{ route('admin.staff.addExpense') }}");

    function printPayslip() {
        window.print();
    }
</script>
@endsection
