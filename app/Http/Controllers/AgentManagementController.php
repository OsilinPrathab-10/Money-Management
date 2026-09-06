<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Helpers\GallaboxMessenger;
use App\Models\AgentAttendance;
use App\Models\AgentExpense;
use App\Models\AgentAdvance;
use App\Models\Holiday;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class AgentManagementController extends Controller
{
    public function __construct()
    {
        // Restriction for creating/updating agents
        $this->middleware('permission:agent.update')->only(['store', 'updateAccount']);
        // Restriction for deleting agents
        $this->middleware('permission:agent.delete')->only(['destroy']);
    }

    /**
     * Display the Agent Management directory/list.
     */
    public function AgentManagement(Request $request): View
    {
        $totalAgents = Agent::count();
        $activeAgents = Agent::where('status', 'active')->count();
        $inactiveAgents = Agent::where('status', 'inactive')->count();

        $locations = Location::select('id', 'name', 'city', 'state')
            ->orderBy('name')
            ->get();

        // For Roles summary
        $rolesSummary = Role::all()->map(function($role) {
            if ($role->name === 'Agent') {
                $role->users_count = \App\Models\Agent::where('status', 'active')->count();
            } else {
                $role->users_count = User::role($role->name)->where('status', 'active')->count();
            }
            return $role;
        })->filter(function($role) {
            return $role->users_count > 0 || $role->name === 'Agent';
        });
        $roles = Role::all();
        $holidays = Holiday::orderBy('date', 'desc')->get();

        $date = $request->get('date', date('Y-m-d'));
        $dailyAgents = Agent::where('status', 'active')->get();
        $dailyAttendances = \App\Models\AgentAttendance::where('date', $date)->get()->keyBy('agent_id');

        $month = $request->get('month', date('m'));
        $year = $request->get('year', date('Y'));
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $daysInMonth = $startDate->daysInMonth;
        
        $monthlyAttendances = \App\Models\AgentAttendance::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get()
            ->groupBy('agent_id');

        // Payroll Calculation
        $holidayDates = Holiday::whereMonth('date', $month)->whereYear('date', $year)->get()->map(function($h) {
            return $h->date ? (is_string($h->date) ? $h->date : $h->date->format('Y-m-d')) : null;
        })->filter()->toArray();

        $payrollData = [];
        foreach ($dailyAgents as $agent) {
            $agentAtts = $monthlyAttendances->get($agent->id) ?? collect();
            
            $absents = $agentAtts->where('status', 'absent')->count();
            $halfDays = $agentAtts->where('status', 'half_day')->count();
            
            $salaryAmount = $agent->salary_amount ?? 0;
            $perDay = $salaryAmount / ($daysInMonth ?: 30);
            
            $actualDeductibleAbsents = 0;
            foreach($agentAtts as $att) {
                $attDate = $att->date instanceof \DateTimeInterface ? $att->date->format('Y-m-d') : $att->date;
                if ($attDate && !in_array($attDate, $holidayDates)) {
                    if ($att->status == 'absent') $actualDeductibleAbsents += 1;
                    if ($att->status == 'half_day') $actualDeductibleAbsents += 0.5;
                }
            }
            
            $deduction = $actualDeductibleAbsents * $perDay;
            
            $advances = AgentAdvance::where('agent_id', $agent->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->sum('amount');
            
            $expenses = AgentExpense::where('agent_id', $agent->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->sum('amount');

            $netSalary = $salaryAmount - $deduction - $advances + $expenses;

            $payrollData[] = [
                'agent' => $agent,
                'base_salary' => (float) $salaryAmount,
                'absents' => (int) $absents,
                'half_days' => (int) $halfDays,
                'deduction' => (float) $deduction,
                'advances' => (float) $advances,
                'expenses' => (float) $expenses,
                'net_salary' => (float) $netSalary,
            ];
        }

        $recentExpenses = AgentExpense::with('agent')
            ->orderBy('date', 'desc')
            ->limit(10)
            ->get();

        $recentAdvances = AgentAdvance::with('agent')
            ->orderBy('date', 'desc')
            ->limit(10)
            ->get();

        return view('admin.agents.agent-management.agent-management', compact(
            'totalAgents', 'activeAgents', 'inactiveAgents', 'locations', 'roles', 'holidays', 'rolesSummary',
            'date', 'dailyAgents', 'dailyAttendances', 'month', 'year', 'daysInMonth', 'monthlyAttendances',
            'payrollData', 'recentExpenses', 'recentAdvances'
        ));
    }

    /**
     * Display the Consolidated Agent Attendance page (Daily Marking & Monthly Report).
     */
    public function attendance(Request $request): View
    {
        // Daily Marking Data
        $date = $request->get('date', date('Y-m-d'));
        $dailyAgents = Agent::where('status', 'active')->get();
        $dailyAttendances = AgentAttendance::where('date', $date)->get()->keyBy('agent_id');

        // Monthly Report Data
        $month = $request->get('month', date('m'));
        $year = $request->get('year', date('Y'));
        $repDate = Carbon::createFromDate($year, $month, 1);
        $daysInMonth = $repDate->daysInMonth;
        
        $monthlyAttendances = AgentAttendance::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get()
            ->groupBy('agent_id');

        return view('admin.agents.agent-management.attendance', compact(
            'date', 'dailyAgents', 'dailyAttendances',
            'month', 'year', 'daysInMonth', 'monthlyAttendances'
        ));
    }

    public function markAttendance(Request $request)
    {
        $request->validate([
            'agent_id' => 'required|exists:agents,id',
            'date' => 'required|date',
            'status' => 'required|in:present,absent,half_day',
        ]);

        $today = Carbon::today()->format('Y-m-d');
        if ($request->date < $today) {
            return response()->json(['success' => false, 'message' => 'Previous days are locked.'], 403);
        }

        $att = \App\Models\AgentAttendance::where('agent_id', $agentId = auth()->user()->agent->id)->where('date', $request->date)->first();
        if ($att && $att->edit_count >= 2) {
            return response()->json(['success' => false, 'message' => 'Attendance edit limit reached (2 times per day).'], 422);
        }

        \App\Models\AgentAttendance::updateOrCreate(
            ['agent_id' => $agentId = auth()->user()->agent->id , 'date' => $request->date],
            [
                'status' => $request->status, 
                'remarks' => $request->remarks,
                'edit_count' => \Illuminate\Support\Facades\DB::raw('edit_count + 1')
            ]
        );

        return response()->json(['success' => true, 'message' => 'Attendance status updated successfully']);
    }

    public function bulkMarkAttendance(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'attendances' => 'required|array',
            'attendances.*.status' => 'required|in:present,absent,half_day',
            'attendances.*.agent_id' => 'required|exists:agents,id',
        ]);

        $today = Carbon::today()->format('Y-m-d');
        if ($request->date < $today) {
            return response()->json(['success' => false, 'message' => 'Previous days are locked.'], 403);
        }

        $savedCount = 0;
        foreach ($request->attendances as $att) {
            $record = \App\Models\AgentAttendance::where('agent_id', $att['agent_id'])->where('date', $request->date)->first();
            if ($record && $record->edit_count >= 2) {
                continue;
            }

            \App\Models\AgentAttendance::updateOrCreate(
                ['agent_id' => $att['agent_id'], 'date' => $request->date],
                [
                    'status' => $att['status'], 
                    'remarks' => $att['remarks'] ?? null,
                    'edit_count' => \Illuminate\Support\Facades\DB::raw('edit_count + 1')
                ]
            );
            $savedCount++;
        }

        return response()->json(['success' => true, 'message' => "Attendance saved for $savedCount agents. Some may have been skipped due to edit limits."]);
    }

    public function addExpense(Request $request)
    {
        $request->validate([
            'agent_id' => 'required|exists:agents,id',
            'amount' => 'required|numeric|min:0',
            'category' => 'required|in:travel,petrol,other',
            'date' => 'required|date',
            'description' => 'required|string',
        ]);

        AgentExpense::create($request->all());
        return back()->with('success', 'Expense added successfully');
    }

    public function addAdvance(Request $request)
    {
        $request->validate([
            'agent_id' => 'required|exists:agents,id',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'description' => 'required|string',
        ]);

        AgentAdvance::create($request->all());
        return back()->with('success', 'Advance added successfully');
    }

    public function printAttendanceReport(Request $request)
    {
        $month = $request->get('month', date('m'));
        $year = $request->get('year', date('Y'));
        
        try {
            $repDate = Carbon::createFromDate($year, $month, 1);
        } catch (\Exception $e) {
            $repDate = Carbon::now()->startOfMonth();
        }
        
        $daysInMonth = $repDate->daysInMonth;
        $agents = Agent::where('status', 'active')->orderBy('agent_name', 'asc')->get();
        $attendances = \App\Models\AgentAttendance::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get()
            ->groupBy('agent_id');

        return view('admin.agents.agent-management.print-attendance', compact(
            'month', 'year', 'daysInMonth', 'agents', 'attendances'
        ));
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
        $agents = Agent::where('status', 'active')->orderBy('agent_name', 'asc')->get();
        $attendances = \App\Models\AgentAttendance::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get()
            ->groupBy('agent_id');

        $fileName = "agent_attendance_report_{$month}_{$year}.csv";
        
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = array('Agent Member');
        for($d=1; $d<=$daysInMonth; $d++) { $columns[] = $d; }
        $columns[] = 'Present';
        $columns[] = 'Absent';
        $columns[] = 'Half Day';

        $callback = function() use($agents, $attendances, $columns, $year, $month, $daysInMonth) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($agents as $s) {
                $staffAtt = $attendances->get($s->id) ?? collect();
                
                // Index by date string for reliability
                $indexedAtt = $staffAtt->keyBy(function($att) {
                    $d = is_string($att->date) ? Carbon::parse($att->date) : $att->date;
                    return $d ? $d->format('Y-m-d') : '';
                });

                $row = [$s->agent_name];
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


    /**
     * Provide agent data for DataTables.
     */
    public function data(Request $request): JsonResponse
    {
        $columns = [
            1 => 'id',
            2 => 'agent_name',
            3 => 'agent_email',
            4 => 'agent_phone',
            5 => 'status',
        ];

        $totalData = Agent::count();
        $totalFiltered = $totalData;

        $limit = (int) $request->input('length', 10);
        $start = (int) $request->input('start', 0);
        $orderColumnIndex = (int) $request->input('order.0.column', 1);
        $order = $columns[$orderColumnIndex] ?? 'id';
        $dir = $request->input('order.0.dir', 'desc');

        $query = Agent::query();

        if ($search = $request->input('search.value')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%{$search}%")
                    ->orWhere('agent_name', 'LIKE', "%{$search}%")
                    ->orWhere('agent_email', 'LIKE', "%{$search}%")
                    ->orWhere('agent_phone', 'LIKE', "%{$search}%");
            });

            $totalFiltered = $query->count();
        }

        $agents = $query
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();

        $data = [];
        $rowNumber = $start;

        foreach ($agents as $agent) {
            $data[] = [
                'id' => $agent->getRouteKey(),
                'fake_id' => ++$rowNumber,
                'name' => $agent->agent_name ?? 'N/A',
                'email' => $agent->agent_email ?? 'N/A',
                'mobile' => $agent->agent_phone ?? 'N/A',
                'status' => $agent->status ?? 'inactive',
                'action' => '',
            ];
        }

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
        ]);
    }

    /**
     * Store a new agent.
     */
    public function store(Request $request, GallaboxMessenger $whatsapp): JsonResponse
    {
        Log::info('Agent registration request received', $request->all());

        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s.]+$/'],
                'email' => [
                    'required',
                    'email',
                    'unique:agents,agent_email',
                    'unique:users,email'
                ],
                'phone' => [
                    'required',
                    'regex:/^[0-9]{10}$/',
                    'unique:agents,agent_phone',
                    'unique:users,phone'
                ],
                'address' => 'required|string',
                'city' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s.]+$/'],
                'state' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s.]+$/'],
                'pincode' => 'required|numeric|digits:6',
                'location_id' => 'required|exists:locations,id',
                'password' => 'required|string|min:8|confirmed',
            ], [
                'name.required' => 'Name is required',
                'name.regex' => 'Name must contain only letters, dots and spaces',
                'email.required' => 'Email is required',
                'email.email' => 'Please enter a valid email address',
                'email.unique' => 'This email is already registered',
                'phone.required' => 'Phone is required',
                'phone.regex' => 'Phone number must be exactly 10 digits',
                'phone.unique' => 'This phone number is already registered',
                'address.required' => 'Address is required',
                'city.required' => 'City is required',
                'city.regex' => 'City must contain only letters and spaces (no numbers)',
                'state.required' => 'State is required',
                'state.regex' => 'State must contain only letters and spaces (no numbers)',
                'pincode.required' => 'Pincode is required',
                'pincode.digits' => 'Pincode must be exactly 6 digits',
                'location_id.required' => 'Please select a location',
                'location_id.exists' => 'Selected location does not exist',
                'password.required' => 'Password is required',
                'password.min' => 'Password must be at least 8 characters',
                'password.confirmed' => 'Passwords do not match',
            ]);

            Log::info('Validation passed', $validated);

            // Check if agent phone/email exists in clients table
            $clientExists = Client::where('client_email', $validated['email'])
                ->orWhere('client_phone', $validated['phone'])
                ->exists();

            if ($clientExists) {
                Log::warning('Agent email or phone already exists in clients table', $validated);
                return response()->json([
                    'success' => false,
                    'message' => 'This email or phone is already registered as a client'
                ], 422);
            }

            // Generate agent code
            $lastAgent = Agent::orderBy('id', 'desc')->first();
            $nextNumber = 1;
            if ($lastAgent && $lastAgent->agent_code) {
                $lastNumber = (int) substr($lastAgent->agent_code, 3);
                $nextNumber = $lastNumber + 1;
            }
            $agentCode = 'ALM' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            Log::info('Generated agent code: ' . $agentCode);

            // Create user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => bcrypt($validated['password']),
                'plain_password' => $validated['password'],
                'status' => 'active',
            ]);

            $user->assignRole('Agent');
            Log::info('User created with ID: ' . $user->id);

            // Create agent
            $agent = Agent::create([
                'user_id' => $user->id,
                'agent_name' => $validated['name'],
                'agent_email' => $validated['email'],
                'agent_phone' => $validated['phone'],
                'agent_code' => $agentCode,
                'address' => $validated['address'],
                'city' => $validated['city'],
                'state' => $validated['state'],
                'pincode' => $validated['pincode'],
                'location_id' => $validated['location_id'],
                'status' => 'active',
            ]);

            Log::info('Agent created successfully', ['agent_id' => $agent->id, 'agent_code' => $agentCode]);
            
            try {
                $whatsapp = new GallaboxMessenger();
                $whatsapp->send_welcome_notification($user);
            } catch (\Throwable $e) {
                Log::error('WhatsApp notification failed: ' . $e->getMessage());
                // Don't fail the request if notification fails
            }

            return response()->json([
                'success' => true,
                'message' => 'Agent created successfully',
                'agent' => $agent
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Agent creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an agent.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $realId = \App\Support\HashId::decode((string) $id) ?? $id;
            $agent = Agent::findOrFail($realId);
            
            // Delete associated user if exists
            if ($agent->user) {
                $agent->user->delete();
            }
            
            $agent->delete();

            return response()->json([
                'success' => true,
                'message' => 'Agent and associated user deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete agent'
            ], 500);
        }
    }

    /**
     * Display agent account details.
     */
    public function view($id): View
    {
        try {
            $realId = \App\Support\HashId::decode((string) $id);
            $realId = is_array($realId) ? ($realId[0] ?? $id) : ($realId ?? $id);
            
            $agent = Agent::with(['user', 'clients'])
                ->findOrFail($realId);
        } catch (\Exception $e) {
            abort(404, 'Agent not found or invalid ID');
        }

        $totalClients = $agent->clients()->count();
        $activeClients = $agent->clients()->where('status', 'active')->count();

        $stats = [
            'total_clients' => $totalClients,
            'active_clients' => $activeClients,
        ];

        // Fetch agent's latest live location
        $liveLocation = null;
        if ($agent->user) {
            $liveLocation = \App\Models\UserLiveLocation::where('user_id', $agent->user->id)
                ->latest('recorded_at')
                ->first();
        }

        // Google Maps API key from api_configurations
        $googleMapsKey = null;
        try {
            $googleMapsConfig = \App\Models\ApiConfiguration::where('service', 'google_maps')->first();
            if ($googleMapsConfig && !empty($googleMapsConfig->credentials['api_key'])) {
                $googleMapsKey = $googleMapsConfig->credentials['api_key'];
            }
        } catch (\Exception $e) {
            // Decryption failed (APP_KEY mismatch) — map will show coordinates only
        }

        $locations = \App\Models\Location::orderBy('name')->get();

        return view('admin.agents.agent-management.agent-view-account', [
            'agent' => $agent,
            'stats' => $stats,
            'liveLocation' => $liveLocation,
            'googleMapsKey' => $googleMapsKey,
            'locations' => $locations,
        ]);
    }

    /**
     * Update agent account details.
     */
    public function updateAccount(Request $request, $id): JsonResponse
    {
        $realId = \App\Support\HashId::decode((string) $id) ?? $id;
        $agent = Agent::with(['user'])->findOrFail($realId);

        try {
            $validated = $request->validate([
                'agent_name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s.]+$/'],
                'agent_email' => [
                    'required',
                    'email',
                    'max:255',
                    'unique:agents,agent_email,' . $agent->id
                ],
                'agent_phone' => [
                    'required',
                    'regex:/^[0-9]{10}$/',
                    'unique:agents,agent_phone,' . $agent->id
                ],
                'status' => 'required|in:active,inactive',
                'address' => 'nullable|string',
                'city' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z\s.]+$/'],
                'state' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z\s.]+$/'],
                'pincode' => 'nullable|string|max:10',
                'location_id' => 'nullable|exists:locations,id',
                'password' => 'nullable|string|min:8|confirmed',
            ], [
                'city.regex' => 'The city field must not contain numbers.',
                'state.regex' => 'The state field must not contain numbers.',
                'password.min' => 'Password must be at least 8 characters.',
                'password.confirmed' => 'Passwords do not match.',
            ]);

            $agent->update($validated);

            // Also update the associated user if exists
            if ($agent->user) {
                $userUpdateData = [
                    'name' => $validated['agent_name'],
                    'email' => $validated['agent_email'],
                    'phone' => $validated['agent_phone'],
                ];

                if (!empty($validated['password'])) {
                    $userUpdateData['password'] = bcrypt($validated['password']);
                    $userUpdateData['plain_password'] = $validated['password'];
                }

                $agent->user->update($userUpdateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Agent profile updated successfully.'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display agent work information.
     */
    public function viewWork($id): View
    {
        $realId = \App\Support\HashId::decode((string) $id);
        $realId = is_array($realId) ? ($realId[0] ?? $id) : ($realId ?? $id);
        $agent = Agent::with(['user', 'clients', 'emiAssignments', 'recoveries', 'followups'])
            ->findOrFail($realId);

        $today = now()->startOfDay();

        // Calculate work statistics (count unique loan accounts, not individual EMIs)
        $workStats = [
            // Total assigned cases (unique clients)
            'assigned_cases' => $agent->emiAssignments()
                ->join('emis', 'emi_agent_assignments.emi_id', '=', 'emis.id')
                ->distinct('emis.loan_account_id')
                ->count('emis.loan_account_id'),

            // Unresolved cases (unique clients with assigned or visited status)
            'unresolved_cases' => $agent->emiAssignments()
                ->whereIn('emi_agent_assignments.status', ['assigned', 'visited'])
                ->join('emis', 'emi_agent_assignments.emi_id', '=', 'emis.id')
                ->distinct('emis.loan_account_id')
                ->count('emis.loan_account_id'),

            // Today's visits (unique clients visited today)
            'today_visits' => $agent->emiAssignments()
                ->where('emi_agent_assignments.status', 'visited')
                ->whereDate('emi_agent_assignments.updated_at', $today)
                ->join('emis', 'emi_agent_assignments.emi_id', '=', 'emis.id')
                ->distinct('emis.loan_account_id')
                ->count('emis.loan_account_id'),

            // Total collections amount
            'total_collections' => $agent->recoveries()
                ->sum('amount'),

            // Followups count
            'followups' => $agent->followups()->count(),

            // High risk cases (unique clients with overdue > 30 days)
            'high_risk_cases' => $agent->emiAssignments()
                ->whereHas('emi', function ($query) {
                    $query->where('status', 'overdue')
                        ->whereDate('due_date', '<=', now()->subDays(30));
                })
                ->join('emis', 'emi_agent_assignments.emi_id', '=', 'emis.id')
                ->distinct('emis.loan_account_id')
                ->count('emis.loan_account_id'),
        ];

        return view('admin.agents.agent-management.agent-view-work', [
            'agent' => $agent,
            'workStats' => $workStats,
        ]);
    }





    public function getAssignedClientsData(Request $request, $id): JsonResponse
    {
        $columns = [
            1 => 'id',
            2 => 'client_name',
            3 => 'client_phone',
        ];

        $realId = \App\Support\HashId::decode((string) $id);
        $realId = is_array($realId) ? ($realId[0] ?? $id) : ($realId ?? $id);

        $agent = Agent::findOrFail($realId);

        // Get EMI assignments for this agent with client and EMI data
        $query = \App\Models\EmiAgentAssignment::where('agent_id', $realId)
            ->with(['emi.loanAccount.client']);

        $totalData = $query->count();
        $totalFiltered = $totalData;

        $limit = (int) $request->input('length', 10);
        $start = (int) $request->input('start', 0);
        $dir = $request->input('order.0.dir', 'asc');

        if ($search = $request->input('search.value')) {
            $query->whereHas('emi.loanAccount.client', function ($q) use ($search) {
                $q->where('client_name', 'LIKE', "%{$search}%")
                    ->orWhere('client_phone', 'LIKE', "%{$search}%")
                    ->orWhere('client_email', 'LIKE', "%{$search}%");
            });

            $totalFiltered = $query->count();
        }

        $assignments = $query->get();

        // Group by loan_account_id
        $groupedAssignments = $assignments->groupBy(function ($assignment) {
            return $assignment->emi ? $assignment->emi->loan_account_id : null;
        })->filter(function ($group, $key) {
            return !is_null($key); // Remove null loan_account_id groups
        });

        // Apply pagination to grouped data
        $paginatedGroups = $groupedAssignments->slice($start, $limit);

        $data = [];

        foreach ($paginatedGroups as $loanAccountId => $group) {
            $firstAssignment = $group->first();
            $loanAccount = $firstAssignment->emi ? $firstAssignment->emi->loanAccount : null;
            $client = $loanAccount ? $loanAccount->client : null;

            if (!$client)
                continue;

            // Get all EMIs for this group
            $emis = $group->pluck('emi')->filter();

            // Calculate totals
            $totalLoanAmount = $emis->sum('total_amount');
            $totalOutstanding = $emis->sum('pending_amount');

            // Determine overall status (show worst status)
            $statusPriority = ['overdue' => 3, 'partial' => 2, 'pending' => 1, 'paid' => 0];
            $worstStatus = $emis->sortByDesc(function ($emi) use ($statusPriority) {
                return $statusPriority[$emi->status] ?? 0;
            })->first();

            $data[] = [
                'id' => $client->getRouteKey(),
                'emi_id' => $emis->map(fn($e) => $e->id)->implode(', '),
                'loan_account_id' => $loanAccount ? $loanAccount->getRouteKey() : null,
                'client_name' => $client->client_name ?? 'N/A',
                'mobile' => $client->client_phone ?? 'N/A',
                'loan_amount' => $totalLoanAmount,
                'outstanding' => $totalOutstanding,
                'status' => $worstStatus ? $worstStatus->status : 'N/A',
                'emi_count' => $emis->count(),
                'action' => '',
            ];
        }

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($groupedAssignments->count()), // Count of unique clients
            'recordsFiltered' => intval($groupedAssignments->count()),
            'data' => $data,
        ]);
    }

    /**
     * Get client information for modal (based on EMI data)
     */
    public function getClientInfo($id): JsonResponse
    {
        $realId = \App\Support\HashId::decode((string) $id);
        $realId = is_array($realId) ? ($realId[0] ?? $id) : ($realId ?? $id);
        $client = Client::findOrFail($realId);

        // Get the latest EMI for this client through loan account
        $latestEmi = \App\Models\Emi::whereHas('loanAccount', function ($query) use ($realId) {
            $query->where('client_id', $realId);
        })
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'id' => $client->getRouteKey(),
            'client_name' => $client->client_name ?? 'N/A',
            'mobile' => $client->client_phone ?? 'N/A',
            'email' => $client->client_email ?? 'N/A',
            'address' => $client->address ?? 'N/A',
            'loan_amount' => $latestEmi ? $latestEmi->total_amount : 0,
            'outstanding' => $latestEmi ? $latestEmi->pending_amount : 0,
            'status' => $latestEmi ? $latestEmi->status : 'N/A',
        ]);
    }

    /**
     * Display agent visits.
     */
    public function viewVisits($id): View
    {
        $realId = \App\Support\HashId::decode((string) $id);
        $realId = is_array($realId) ? ($realId[0] ?? $id) : ($realId ?? $id);
        $agent = Agent::with(['user'])->findOrFail($realId);

        $stats = [
            'total_visits' => \App\Models\AgentVisitLog::where('agent_id', $realId)->count(),
            'today_visits' => \App\Models\AgentVisitLog::where('agent_id', $realId)
                ->whereDate('started_at', now())
                ->count(),
        ];

        return view('admin.agents.agent-management.agent-view-visits', [
            'agent' => $agent,
            'stats' => $stats,
        ]);
    }

    public function getVisitData(Request $request, $id): JsonResponse
    {
        $columns = [
            1 => 'id',
            2 => 'date',
            3 => 'client_name',
            4 => 'start_time',
            5 => 'end_time',
            6 => 'duration',
            7 => 'location',
        ];

        $realId = \App\Support\HashId::decode((string) $id);
        $realId = is_array($realId) ? ($realId[0] ?? $id) : ($realId ?? $id);

        $query = \App\Models\AgentVisitLog::where('agent_id', $realId)
            ->with(['emi.loanAccount.client.location']);

        $totalData = $query->count();
        $totalFiltered = $totalData;

        $limit = (int) $request->input('length', 10);
        $start = (int) $request->input('start', 0);
        $orderColumnIndex = (int) $request->input('order.0.column', 2); // Default order by date
        $order = $columns[$orderColumnIndex] ?? 'started_at';
        $dir = $request->input('order.0.dir', 'desc');

        // Adjust ordering for non-DB columns if needed
        if ($order === 'date')
            $order = 'started_at';
        if ($order === 'start_time')
            $order = 'started_at';
        if ($order === 'end_time')
            $order = 'ended_at';
        if ($order === 'client_name') {
            // Complex ordering by relationship, defaulting to started_at for simplicity in this step
            // or perform join if strictly required
            $order = 'started_at';
        }

        if ($search = $request->input('search.value')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('emi.loanAccount.client', function ($cq) use ($search) {
                    $cq->where('client_name', 'LIKE', "%{$search}%")
                        ->orWhere('client_phone', 'LIKE', "%{$search}%");
                });
            });
            $totalFiltered = $query->count();
        }

        $visits = $query
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();

        $data = [];
        $rowNumber = $start;

        foreach ($visits as $visit) {
            $client = $visit->emi->loanAccount->client ?? null;

            // Calculate duration
            $duration = 'N/A';
            if ($visit->started_at && $visit->ended_at) {
                $diff = $visit->started_at->diff($visit->ended_at);
                $duration = $diff->format('%H:%I:%S');
            } elseif ($visit->started_at) {
                $duration = 'Ongoing';
            }

            $data[] = [
                'id' => $visit->getRouteKey(),
                'fake_id' => ++$rowNumber,
                'date' => $visit->started_at ? $visit->started_at->format('d-m-Y') : 'N/A',
                'client_name' => $client ? $client->client_name : 'N/A',
                'start_time' => $visit->started_at ? $visit->started_at->format('h:i A') : 'N/A',
                'end_time' => $visit->ended_at ? $visit->ended_at->format('h:i A') : '-',
                'duration' => $duration,
                'location' => $client ? (optional($client->location)->name ?? $client->city ?? 'N/A') : 'N/A',
                'action' => '',
            ];
        }

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
        ]);
    }

    /**
     * View visit details.
     */
    public function viewVisitDetails($visitId): View
    {
        $realVisitId = \App\Support\HashId::decode((string) $visitId);
        $realVisitId = is_array($realVisitId) ? ($realVisitId[0] ?? $visitId) : ($realVisitId ?? $visitId);
        $visit = \App\Models\AgentVisitLog::with(['agent', 'emi.loanAccount.client'])
            ->findOrFail($realVisitId);

        $googleMapsConfig = \App\Models\ApiConfiguration::where('service', 'google_maps')->first();
        $googleMapsKey = $googleMapsConfig && !empty($googleMapsConfig->credentials['api_key'])
            ? $googleMapsConfig->credentials['api_key']
            : null;

        // Fetch Logs (Activities & Collections) during the visit
        $activities = collect();

        if ($visit->emi) {
            $client = $visit->emi->loanAccount->client;
            // Get all EMIs for this client to check for any activity related to this client
            $clientEmiIds = \App\Models\Emi::whereHas('loanAccount', function ($q) use ($client) {
                $q->where('client_id', $client->id);
            })->pluck('id');

            $visitStart = $visit->started_at;
            $visitEnd = $visit->ended_at ?? now();

            // Expand window by 10 minutes to catch actions done just before/after clicking start/stop
            $startTime = $visitStart->copy()->subMinutes(10);
            $endTime = $visitEnd->copy()->addMinutes(10);

            // 1. Agent Activities (Updates, Calls, Notes)
            $agentActivities = \App\Models\AgentActivity::whereIn('emi_id', $clientEmiIds)
                ->where('agent_id', $visit->agent_id)
                ->whereBetween('action_at', [$startTime, $endTime])
                ->get()
                ->map(function ($activity) {
                    return [
                        'type' => 'activity', // distinguishing type
                        'activity_type' => ucfirst($activity->type),
                        'description' => $activity->description,
                        'timestamp' => $activity->action_at,
                        'icon' => 'ri-file-list-line',
                        'color' => 'info',
                    ];
                });

            // 2. Collections
            $collections = \App\Models\EmiCollection::whereIn('emi_id', $clientEmiIds)
                ->where('agent_id', $visit->agent_id)
                ->whereBetween('collected_at', [$startTime, $endTime])
                ->get()
                ->map(function ($collection) {
                    return [
                        'type' => 'collection',
                        'activity_type' => 'Payment Collected',
                        'description' => 'Collected ₹' . number_format($collection->amount, 2) . ' via ' . ucfirst(str_replace('_', ' ', $collection->payment_method)),
                        'timestamp' => $collection->collected_at,
                        'icon' => 'ri-money-rupee-circle-line',
                        'color' => 'success',
                    ];
                });

            $activities = $agentActivities->merge($collections)->sortByDesc('timestamp');
        }

        return view('admin.agents.agent-management.agent-visit-details', [
            'visit' => $visit,
            'agent' => $visit->agent,
            'googleMapsKey' => $googleMapsKey,
            'activities' => $activities,
        ]);
    }

    /**
     * View detailed assigned client page.
     */
    public function viewAssignedClient($id, $clientId): View
    {
        $realAgentId = \App\Support\HashId::decode((string) $id);
        $realAgentId = is_array($realAgentId) ? ($realAgentId[0] ?? $id) : ($realAgentId ?? $id);
        
        $realClientId = \App\Support\HashId::decode((string) $clientId);
        $realClientId = is_array($realClientId) ? ($realClientId[0] ?? $clientId) : ($realClientId ?? $clientId);
        $agent = Agent::findOrFail($realAgentId);
        $client = \App\Models\Client::with('location')->findOrFail($realClientId);

        // Fetch Assignments for this Agent AND Client
        $assignments = \App\Models\EmiAgentAssignment::where('agent_id', $realAgentId)
            ->whereHas('emi.loanAccount', function ($q) use ($realClientId) {
                $q->where('client_id', $realClientId);
            })
            ->with(['emi.loanAccount.loanApplication.product', 'emi.loanAccount.client', 'emi.collections', 'emi.followups'])
            ->get();

        // Fetch Interaction History
        // 1. Visits
        $visits = \App\Models\AgentVisitLog::where('agent_id', $realAgentId)
            ->whereHas('emi.loanAccount', function ($q) use ($realClientId) {
                $q->where('client_id', $realClientId);
            })
            ->get()
            ->map(function ($visit) {
                return [
                    'type' => 'visit',
                    'title' => 'Visit',
                    'description' => $visit->duration_text ?? 'Duration: ' . ($visit->started_at && $visit->ended_at ? $visit->started_at->diff($visit->ended_at)->format('%H:%I:%S') : 'N/A'),
                    'timestamp' => $visit->started_at,
                    'icon' => 'ri-map-pin-user-line',
                    'color' => 'primary',
                    'link' => route('agent-management.visit-details', $visit->id),
                ];
            });

        // 2. Collections
        $collections = \App\Models\EmiCollection::where('agent_id', $realAgentId)
            ->whereHas('emi.loanAccount', function ($q) use ($realClientId) {
                $q->where('client_id', $realClientId);
            })
            ->get()
            ->map(function ($col) {
                return [
                    'type' => 'collection',
                    'title' => 'Payment Collected',
                    'description' => '₹' . number_format($col->amount, 2) . ' (' . ucfirst($col->payment_method) . ')',
                    'timestamp' => $col->collected_at,
                    'icon' => 'ri-money-rupee-circle-line',
                    'color' => 'success',
                    'link' => null,
                ];
            });

        // 3. Followups (Calls)
        $followups = \App\Models\EmiFollowup::where('agent_id', $realAgentId)
            ->whereHas('emi.loanAccount', function ($q) use ($realClientId) {
                $q->where('client_id', $realClientId);
            })
            ->get()
            ->map(function ($followup) {
                return [
                    'type' => 'followup',
                    'title' => 'Followup (' . ucfirst($followup->type) . ')',
                    'description' => $followup->remarks ?? 'No remarks',
                    'timestamp' => $followup->created_at, // or followup_at
                    'icon' => 'ri-phone-line',
                    'color' => 'warning',
                    'link' => null,
                ];
            });

        $history = $visits->toBase()->merge($collections->toBase())->merge($followups->toBase())->sortByDesc('timestamp');

        return view('admin.agents.agent-management.agent-view-assigned-client', [
            'agent' => $agent,
            'client' => $client,
            'assignments' => $assignments,
            'history' => $history,
        ]);
    }
    public function exportAttendancePDF(Request $request)
    {
        $month = $request->get('month', date('m'));
        $year = $request->get('year', date('Y'));
        
        $date = \Carbon\Carbon::createFromDate($year, $month, 1);
        $daysInMonth = $date->daysInMonth;
        
        $agents = Agent::where('status', 'active')->orderBy('agent_name', 'asc')->get();
        $attendances = \App\Models\AgentAttendance::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get()
            ->groupBy('agent_id');

        $logoData = $this->resolveLogoData();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.dynamic_document', [
            'header' => '',
            'footer' => '',
            'body' => view('admin.agents.agent-management.pdf-attendance', compact('agents', 'attendances', 'month', 'year', 'daysInMonth'))->render(),
            'logo' => $logoData['logo'] ?? null,
            'is_base64' => $logoData['is_base64'] ?? false,
            'title' => "Agent Attendance Report - " . $date->format('F Y'),
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

        return $pdf->download("agent_attendance_{$month}_{$year}.pdf");
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
