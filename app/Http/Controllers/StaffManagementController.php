<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffExpense;
use App\Models\StaffAdvance;
use App\Models\Branch;
use App\Models\Holiday;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;

class StaffManagementController extends Controller
{
    public function index(Request $request)
    {
        $allStaffs = Staff::with(['branch', 'user.roles'])
            ->where(function ($q) {
                $q->whereHas('user', function ($query) {
                    $query->whereDoesntHave('roles', function ($qr) {
                        $qr->where('name', 'Client');
                    });
                })->orWhereDoesntHave('user');
            })
            ->orderBy('id', 'asc')
            ->get();

        $staffs = $allStaffs->filter(function ($s) {
            return !($s->user && $s->user->hasRole('Agent'));
        });

        $agents = $allStaffs->filter(function ($s) {
            return $s->user && $s->user->hasRole('Agent');
        });

        $branches = Branch::all();
        $roles = Role::whereNotIn('name', ['Client'])->get();
        $holidays = Holiday::orderBy('date', 'desc')->get();

        // Attendance data for the "Daily Marking" sub-tab
        $date = $request->get('date', date('Y-m-d'));
        $dailyStaffs = Staff::where('status', 'active')
            ->whereHas('user', function ($query) {
                $query->whereDoesntHave('roles', function ($q) {
                    $q->where('name', 'Client');
                });
            })
            ->get();
        $dailyAttendances = StaffAttendance::where('date', $date)->get()->keyBy('staff_id');

        // Attendance Report data for the "Monthly Grid" sub-tab
        $month = $request->get('month', date('m'));
        $year = $request->get('year', date('Y'));
        $repDate = Carbon::createFromDate($year, $month, 1);
        $daysInMonth = $repDate->daysInMonth;
        $monthlyAttendances = StaffAttendance::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get()
            ->groupBy('staff_id');

        // For Roles summary
        $rolesSummary = Role::withCount('users')->get();

        return view('admin.staff-management.index', compact(
            'staffs', 'agents', 'branches', 'roles', 'holidays', 'rolesSummary', 'month', 'year',
            'date', 'dailyStaffs', 'dailyAttendances', 'daysInMonth', 'monthlyAttendances'
        ));
    }

    public function store(Request $request)
    {
        if ($request->branch_id == '0') {
            $request->merge(['branch_id' => null]);
        }
        if ($request->role == '0') {
            $request->merge(['role' => null]);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s]+$/'],
            'email' => 'nullable|email|unique:staffs,email',
            'phone' => 'required|string|digits:10|unique:staffs,phone',
            'salary_amount' => 'required|numeric|min:0',
            'branch_id' => 'nullable|exists:branches,id',
            'role' => 'nullable|exists:roles,name',
            'profile_photo' => 'nullable|image|max:2048',
            'password' => 'nullable|string|min:8|confirmed',
        ], [
            'name.regex' => 'Name must contain only alphabets and spaces.',
            'phone.digits' => 'Mobile number must be exactly 10 digits.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Passwords do not match.',
        ]);

        $data = $request->only(['name', 'email', 'phone', 'salary_amount', 'branch_id']);
        $data['salary_details'] = [
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'ifsc_code' => $request->ifsc_code,
        ];

        if ($request->hasFile('profile_photo')) {
            $data['profile_photo'] = $request->file('profile_photo')->store('staff/profiles', 'public');
        }

        // Create User account if Role is selected
        if ($request->role) {
            $userEmail = $request->email ?: ($request->phone . '');
            $user = User::firstOrNew(['phone' => $request->phone]);
            $user->name = $request->name;
            if (!$user->exists || !$user->email) {
                $user->email = $userEmail;
            }
            if (!$user->exists) {
                $user->password = Hash::make($request->password ?: $request->phone); // Default password is phone if empty
            }
            $user->status = 'active';
            $user->save();

            $user->syncRoles([$request->role]);
            $data['user_id'] = $user->id;
        }

        Staff::create($data);

        return back()->with('success', 'Staff created successfully');
    }

    public function update(Request $request, $id)
    {
        $staff = Staff::findOrFail($id);
        
        if ($request->branch_id == '0' || $request->branch_id === '') {
            $request->merge(['branch_id' => null]);
        }
        if ($request->role == '0' || $request->role === '') {
            $request->merge(['role' => null]);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s]+$/'],
            'email' => 'nullable|email|unique:staffs,email,' . $id,
            'phone' => 'required|string|digits:10|unique:staffs,phone,' . $id,
            'salary_amount' => 'required|numeric|min:0',
            'branch_id' => 'nullable|exists:branches,id',
            'role' => 'nullable|exists:roles,name',
            'status' => 'required|in:active,inactive',
        ], [
            'name.regex' => 'Name must contain only alphabets and spaces.',
            'phone.digits' => 'Mobile number must be exactly 10 digits.',
        ]);

        $data = $request->only(['name', 'email', 'phone', 'salary_amount', 'status', 'branch_id']);
        // NOTE: 'role' is intentionally excluded — it is not a column on the staffs table.
        // Role is managed via Spatie syncRoles() on the associated users table.
        $data['salary_details'] = [
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'ifsc_code' => $request->ifsc_code,
        ];

        if ($request->hasFile('profile_photo')) {
            if ($staff->profile_photo) Storage::disk('public')->delete($staff->profile_photo);
            $data['profile_photo'] = $request->file('profile_photo')->store('staff/profiles', 'public');
        }

        // Update/Create User account
        if ($request->role) {
            $userEmail = $request->email ?: ($request->phone . '@shanmugafinance.local');
            $user = User::firstOrNew(['phone' => $request->phone]);
            $user->name = $request->name;
            if (!$user->exists || !$user->email) {
                $user->email = $userEmail;
            }
            if (!$user->exists) {
                $user->password = Hash::make($request->phone);
            }
            $user->status = $request->status == 'active' ? 'active' : 'inactive';
            $user->save();

            $user->syncRoles([$request->role]);
            $data['user_id'] = $user->id;
        } else if ($staff->user_id) {
            // Remove role if they had one but now it's "No Login Rights"
            $user = User::find($staff->user_id);
            if ($user) {
                $user->roles()->detach();
                $user->status = 'inactive';
                $user->save();
            }
        }

        $staff->update($data);

        return back()->with('success', 'Staff updated successfully');
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            $staff = Staff::findOrFail($id);
            
            // Handle associated user account
            if ($staff->user_id) {
                $user = User::find($staff->user_id);
                if ($user) {
                    $user->status = 'inactive';
                    $user->save();
                    // Optionally: $user->delete(); if you want total removal
                }
            }

            // Delete profile photo
            if ($staff->profile_photo) {
                Storage::disk('public')->delete($staff->profile_photo);
            }

            $staff->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Staff record deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete staff: ' . $e->getMessage()
            ], 500);
        }
    }

    public function attendance(Request $request)
    {
        $date = $request->get('date', date('Y-m-d'));
        $perPage = $request->get('per_page', 20);
        $staffs = Staff::where('status', 'active')->paginate($perPage);
        $attendances = StaffAttendance::where('date', $date)->get()->keyBy('staff_id');
        
        return view('admin.staff-management.attendance', compact('staffs', 'attendances', 'date'));
    }

    public function markAttendance(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|exists:staffs,id',
            'date' => 'required|date',
            'status' => 'required|in:present,absent,half_day',
        ]);

        $today = Carbon::today()->format('Y-m-d');
        if ($request->date < $today) {
            return response()->json(['success' => false, 'message' => 'Previous days are locked and cannot be edited.'], 403);
        }

        $att = StaffAttendance::where('staff_id', $request->staff_id)->where('date', $request->date)->first();
        if ($att && $att->edit_count >= 2) {
            return response()->json(['success' => false, 'message' => 'Attendance edit limit reached (2 times per day).'], 422);
        }

        StaffAttendance::updateOrCreate(
            ['staff_id' => $request->staff_id, 'date' => $request->date],
            [
                'status' => $request->status, 
                'remarks' => $request->remarks,
                'edit_count' => DB::raw('edit_count + 1')
            ]
        );

        return response()->json(['success' => true, 'message' => 'Attendance status updated successfully']);
    }

    public function payroll(Request $request)
    {
        $month = $request->get('month', date('m'));
        $year = $request->get('year', date('Y'));
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $daysInMonth = $startDate->daysInMonth;

        $staffs = Staff::where('status', 'active')->get();
        $holidays = Holiday::whereMonth('date', $month)->whereYear('date', $year)->get()->map(function($h) {
            return $h->date ? (is_string($h->date) ? $h->date : $h->date->format('Y-m-d')) : null;
        })->filter()->toArray();

        $payrollData = [];

        foreach ($staffs as $staff) {
            $attendances = StaffAttendance::where('staff_id', $staff->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->get();
            
            $absents = $attendances->where('status', 'absent')->count();
            $halfDays = $attendances->where('status', 'half_day')->count();
            
            // Per day salary
            $perDay = $staff->salary_amount / ($daysInMonth ?: 30);
            
            // Standard deduction (Abstents + 0.5 * HalfDays)
            $actualDeductibleAbsents = 0;
            foreach($attendances as $att) {
                $attDate = $att->date ? (is_string($att->date) ? $att->date : $att->date->format('Y-m-d')) : null;
                if ($attDate && !in_array($attDate, $holidays)) {
                    if ($att->status == 'absent') $actualDeductibleAbsents += 1;
                    if ($att->status == 'half_day') $actualDeductibleAbsents += 0.5;
                }
            }
            
            $deduction = $actualDeductibleAbsents * $perDay;
            
            $advanceList = StaffAdvance::where('staff_id', $staff->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->get()
                ->map(function($a) {
                    return [
                        'amount' => $a->amount,
                        'description' => $a->description,
                        'date' => $a->date ? (is_string($a->date) ? Carbon::parse($a->date)->format('d-m-Y') : $a->date->format('d-m-Y')) : 'N/A'
                    ];
                });

            $expenseList = StaffExpense::where('staff_id', $staff->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->get()
                ->map(function($e) {
                    return [
                        'amount' => $e->amount,
                        'category' => $e->category,
                        'description' => $e->description,
                        'date' => $e->date ? (is_string($e->date) ? Carbon::parse($e->date)->format('d-m-Y') : $e->date->format('d-m-Y')) : 'N/A'
                    ];
                });
            
            $advances = StaffAdvance::where('staff_id', $staff->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->sum('amount');
            
            $expenses = StaffExpense::where('staff_id', $staff->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->sum('amount');

            $netSalary = $staff->salary_amount - $deduction - $advances + $expenses;

            $payrollData[] = [
                'staff' => $staff,
                'base_salary' => (float) $staff->salary_amount,
                'absents' => (int) $absents,
                'half_days' => (int) $halfDays,
                'deduction' => (float) $deduction,
                'advances' => (float) $advances,
                'expenses' => (float) $expenses,
                'advance_list' => $advanceList,
                'expense_list' => $expenseList,
                'travel_expenses' => (float) StaffExpense::where('staff_id', $staff->id)->where('category', 'travel')->whereMonth('date', $month)->whereYear('date', $year)->sum('amount'),
                'petrol_expenses' => (float) StaffExpense::where('staff_id', $staff->id)->where('category', 'petrol')->whereMonth('date', $month)->whereYear('date', $year)->sum('amount'),
                'other_expenses' => (float) StaffExpense::where('staff_id', $staff->id)->where('category', 'other')->whereMonth('date', $month)->whereYear('date', $year)->sum('amount'),
                'net_salary' => (float) $netSalary,
            ];
        }

        return view('admin.staff-management.payroll', compact('payrollData', 'month', 'year'));
    }

    public function addExpense(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|exists:staffs,id',
            'amount' => 'required|numeric|min:0',
            'category' => 'required|in:travel,petrol,other',
            'date' => 'required|date',
            'description' => 'required|string',
        ]);

        StaffExpense::create($request->all());
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Expense added successfully']);
        }
        return back()->with('success', 'Expense added');
    }

    public function addAdvance(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|exists:staffs,id',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'description' => 'required|string',
        ]);

        StaffAdvance::create($request->all());
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Salary advance recorded successfully']);
        }
        return back()->with('success', 'Advance added');
    }

    public function bulkMarkAttendance(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'attendances' => 'required|array|min:1',
            'attendances.*.status' => 'required|in:present,absent,half_day',
            'attendances.*.staff_id' => 'required|exists:staffs,id',
        ]);

        $today = Carbon::today()->format('Y-m-d');
        if ($request->date < $today) {
            return response()->json(['success' => false, 'message' => 'Previous days are locked.'], 403);
        }

        $savedCount = 0;
        foreach ($request->attendances as $att) {
            $record = StaffAttendance::where('staff_id', $att['staff_id'])->where('date', $request->date)->first();
            if ($record && $record->edit_count >= 2) {
                continue;
            }

            StaffAttendance::updateOrCreate(
                ['staff_id' => $att['staff_id'], 'date' => $request->date],
                [
                    'status' => $att['status'], 
                    'remarks' => $att['remarks'] ?? null,
                    'edit_count' => DB::raw('edit_count + 1')
                ]
            );
            $savedCount++;
        }

        return response()->json(['success' => true, 'message' => "Attendance saved for $savedCount staff members. Some may have been skipped due to edit limits."]);
    }

    public function exportAttendance(Request $request)
    {
        $month = $request->get('month', date('m'));
        $year = $request->get('year', date('Y'));
        
        try {
            $repDate = Carbon::createFromDate($year, $month, 1);
        } catch (\Exception $e) {
            $repDate = Carbon::now()->startOfMonth();
        }
        
        $daysInMonth = $repDate->daysInMonth;
        $staffs = Staff::where('status', 'active')->orderBy('name', 'asc')->get();
        $attendances = StaffAttendance::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get()
            ->groupBy('staff_id');

        $fileName = "attendance_report_{$month}_{$year}.csv";
        
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = array('Staff Member');
        for($d=1; $d<=$daysInMonth; $d++) { $columns[] = $d; }
        $columns[] = 'Present';
        $columns[] = 'Absent';
        $columns[] = 'Half Day';

        $callback = function() use($staffs, $attendances, $columns, $year, $month, $daysInMonth) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($staffs as $s) {
                $staffAtt = $attendances->get($s->id) ?? collect();
                
                // Index by date string for performance and reliability
                $indexedAtt = $staffAtt->keyBy(function($att) {
                    $d = is_string($att->date) ? Carbon::parse($att->date) : $att->date;
                    return $d ? $d->format('Y-m-d') : '';
                });

                $row = [$s->name];
                $pCount = 0; $aCount = 0; $hCount = 0;
                
                for($d=1; $d<=$daysInMonth; $d++) {
                    $dateStr = sprintf('%d-%02d-%02d', $year, $month, $d);
                    $statRecord = $indexedAtt->get($dateStr);
                    $stat = $statRecord ? $statRecord->status : '-';
                    
                    $row[] = $stat != '-' ? strtoupper(substr($stat, 0, 1)) : '-';
                    
                    if($stat == 'present') $pCount++;
                    elseif($stat == 'absent') $aCount++;
                    elseif($stat == 'half_day') $hCount++;
                }
                
                $row[] = $pCount;
                $row[] = $aCount;
                $row[] = $hCount;

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
    public function holidayIndex()
    {
        $holidays = Holiday::orderBy('date', 'desc')->get();
        return view('admin.staff-management.holidays', compact('holidays'));
    }

    public function storeHoliday(Request $request)
    {
        $request->validate([
            'date' => 'required|date|unique:holidays,date',
            'name' => 'required|string',
        ]);

        Holiday::create($request->all());
        return back()->with('success', 'Holiday added');
    }

    public function updateHoliday(Request $request, $id)
    {
        $holiday = Holiday::findOrFail($id);
        $request->validate([
            'date' => 'required|date|unique:holidays,date,' . $id,
            'name' => 'required|string',
        ]);
        $holiday->update($request->all());
        return back()->with('success', 'Holiday updated');
    }

    public function deleteHoliday($id)
    {
        Holiday::destroy($id);
        if (request()->expectsJson() || request()->ajax()) {
            return response()->json(['success' => true, 'message' => 'Holiday deleted successfully']);
        }
        return back()->with('success', 'Holiday deleted');
    }

    public function attendanceReport(Request $request)
    {
        $month = $request->get('month', date('m'));
        $year = $request->get('year', date('Y'));
        $date = Carbon::createFromDate($year, $month, 1);
        $daysInMonth = $date->daysInMonth;
        
        $staffs = Staff::where('status', 'active')->get();
        $attendances = StaffAttendance::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get()
            ->groupBy('staff_id');

        return view('admin.staff-management.attendance-report', compact('staffs', 'attendances', 'month', 'year', 'daysInMonth'));
    }

    public function storeBranch(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:branches,name']);
        Branch::create($request->all());
        return back()->with('success', 'Branch added successfully');
    }

    public function updateBranch(Request $request, $id)
    {
        $branch = Branch::findOrFail($id);
        $branch->update($request->all());
        return back()->with('success', 'Branch updated');
    }

    public function deleteBranch($id)
    {
        Branch::destroy($id);
        if (request()->expectsJson() || request()->ajax()) {
            return response()->json(['success' => true, 'message' => 'Branch deleted successfully']);
        }
        return back()->with('success', 'Branch deleted');
    }
    public function exportAttendancePDF(Request $request)
    {
        $month = $request->get('month', date('m'));
        $year = $request->get('year', date('Y'));
        
        $date = Carbon::createFromDate($year, $month, 1);
        $daysInMonth = $date->daysInMonth;
        
        $staffs = Staff::where('status', 'active')->orderBy('name', 'asc')->get();
        $attendances = StaffAttendance::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get()
            ->groupBy('staff_id');

        $logoData = $this->resolveLogoData();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.dynamic_document', [
            'header' => '',
            'footer' => '',
            'body' => view('admin.staff-management.pdf-attendance', compact('staffs', 'attendances', 'month', 'year', 'daysInMonth'))->render(),
            'logo' => $logoData['logo'] ?? null,
            'is_base64' => $logoData['is_base64'] ?? false,
            'title' => "Staff Attendance Report - " . $date->format('F Y'),
            'company' => [
                'name' => \App\Helpers\AppearanceHelper::get('title', 'Loan App'),
                'subtitle' => \App\Helpers\AppearanceHelper::get('subtitle', '')
            ],
            'companyName' => \App\Helpers\AppearanceHelper::get('title', 'Loan App'),
            'applicationNumber' => 'N/A',
            'clientName' => 'Admin',
            'consentTimestamp' => now()->format('d-m-Y H:i:s'),
            'registeredMobile' => 'N/A',
            'clientIp' => request()->ip()
        ])->setPaper('A4', 'landscape')
          ->setOptions([
              'isHtml5ParserEnabled' => true,
              'isRemoteEnabled' => true,
              'defaultFont' => 'sans-serif',
              'tempDir' => storage_path('app/public'),
              'chroot'  => [
                  base_path(),
                  public_path(),
                  storage_path('app/public')
              ],
          ]);

        return $pdf->download("staff_attendance_{$month}_{$year}.pdf");
    }

    private function resolveLogoData(): array
    {
        $appearance = \App\Models\Appearance::where('type', 'web')->first();
        
        if (!$appearance || !$appearance->logo) {
            return ['logo' => null, 'is_base64' => false];
        }

        $logoPath = $appearance->logo;
        $candidatePaths = [
            storage_path('app/public/' . $logoPath),
            public_path('storage/' . $logoPath),
            public_path($logoPath)
        ];

        foreach ($candidatePaths as $path) {
            if ($path && file_exists($path) && is_file($path)) {
                try {
                    $type = pathinfo($path, PATHINFO_EXTENSION);
                    $data = file_get_contents($path);
                    $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
                    return [
                        'logo' => $base64,
                        'is_base64' => true
                    ];
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to base64 encode logo: ' . $e->getMessage());
                }
            }
        }

        return ['logo' => asset('storage/' . $logoPath), 'is_base64' => false];
    }
}
