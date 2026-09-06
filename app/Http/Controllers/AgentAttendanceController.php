<?php

namespace App\Http\Controllers;

use App\Models\AgentDailyLog;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AgentAttendanceController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        // Get total number of agents
        $totalAgents = Agent::count();
        
        // Get distinct agents who checked in today
        $presentToday = AgentDailyLog::whereDate('check_in_at', $today)
            ->distinct('agent_id')
            ->count('agent_id');
        
        // Calculate absent today (total agents - present today)
        $absentToday = $totalAgents - $presentToday;

        return view('admin.agents.agent-attendance.agent-attendance', compact(
            'presentToday',
            'absentToday',
            'totalAgents'
        ));
    }

    public function list(Request $request)
    {
        if (!$request->ajax()) {
            return response()->json(['error' => 'Invalid request'], 400);
        }

        $columns = [
            1 => 'id',
            2 => 'agent_id',
            3 => 'check_in_at',
            4 => 'check_out_at',
        ];

        $totalData = AgentDailyLog::count();
        $totalFiltered = $totalData;

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')] ?? 'check_in_at';
        $dir = $request->input('order.0.dir') ?? 'desc';

        $query = AgentDailyLog::with('agent');

        // Date filtering
        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('check_in_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('check_in_at', '<=', $request->end_date);
        }

        // Search handling
        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');

            $query->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%{$search}%")
                  ->orWhereHas('agent', function ($q) use ($search) {
                      $q->where('agent_name', 'LIKE', "%{$search}%");
                  });
            });

            $totalFiltered = $query->count();
        }

        $logs = $query->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();

        $data = [];

        foreach ($logs as $log) {
            $totalHours = 'In Progress';
            if ($log->check_out_at) {
                $totalHours = $log->total_hours ?? '00:00';
            }

            $data[] = [
                'id' => $log->id,
                'agent_name' => $log->agent ? $log->agent->agent_name : 'N/A',
                'check_in_at' => $log->check_in_at ? $log->check_in_at->format('Y-m-d H:i:s') : 'N/A',
                'check_out_at' => $log->check_out_at ? $log->check_out_at->format('Y-m-d H:i:s') : 'Still Working',
                'total_hours' => $totalHours,
                'status' => $log->status,
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

    public function show($id)
    {
        $log = AgentDailyLog::with('agent')->findOrFail($id);
        
        return view('admin.agents.agent-attendance.view-attendance', compact('log'));
    }

    public function getDetails($id)
    {
        $log = AgentDailyLog::with('agent')->findOrFail($id);
        
        $totalHours = 'In Progress';
        if ($log->check_out_at) {
            $totalHours = $log->total_hours ?? '00:00';
        }

        return response()->json([
            'id' => $log->id,
            'agent_name' => $log->agent ? $log->agent->agent_name : 'N/A',
            'check_in_at' => $log->check_in_at ? $log->check_in_at->format('d-m-Y h:i A') : 'N/A',
            'check_out_at' => $log->check_out_at ? $log->check_out_at->format('d-m-Y h:i A') : 'Still Working',
            'check_in_location' => $log->check_in_lat && $log->check_in_long 
                ? "Lat: {$log->check_in_lat}, Long: {$log->check_in_long}" 
                : 'N/A',
            'check_out_location' => $log->check_out_lat && $log->check_out_long 
                ? "Lat: {$log->check_out_lat}, Long: {$log->check_out_long}" 
                : 'N/A',
            'total_hours' => $totalHours,
            'notes' => $log->notes ?? 'No notes',
            'status' => ucfirst(str_replace('_', ' ', $log->status)),
        ]);
    }
}
