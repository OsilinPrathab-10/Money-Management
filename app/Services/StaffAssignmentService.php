<?php

namespace App\Services;

use App\Models\User;
use App\Models\LoanAssignmentTracker;
use App\Enums\LoanStatus;
use Illuminate\Support\Facades\DB;

class StaffAssignmentService
{
    public static function assign()
    {
        return DB::transaction(function () {

            // Lock tracker row to prevent race conditions
            $tracker = LoanAssignmentTracker::lockForUpdate()
                ->firstOrCreate(['id' => 1]);

            // Get staff using Spatie role
            $staffs = User::role('staff')
                ->where('is_active', 1)
                ->withCount(['loans as active_loans_count' => function ($q) {
                    $q->whereIn('status', LoanStatus::ACTIVE);
                }])
                ->orderBy('active_loans_count')
                ->get();

            if ($staffs->isEmpty()) {
                return null;
            }

            // Minimum workload
            $minLoad = $staffs->first()->active_loans_count;

            // Staffs with same minimum workload
            $eligibleStaffs = $staffs
                ->where('active_loans_count', $minLoad)
                ->values();

            // Select staff
            if ($eligibleStaffs->count() === 1) {
                $selected = $eligibleStaffs->first();
            } else {
                $selected = self::roundRobin(
                    $eligibleStaffs,
                    $tracker->last_assigned_staff_id
                );
            }

            // Update tracker
            $tracker->update([
                'last_assigned_staff_id' => $selected->id
            ]);

            return $selected;
        });
    }

    private static function roundRobin($staffs, $lastAssignedId)
    {
        if (!$lastAssignedId) {
            return $staffs->first();
        }

        foreach ($staffs as $staff) {
            if ($staff->id > $lastAssignedId) {
                return $staff;
            }
        }

        return $staffs->first();
    }
}
