<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;

class AdminAttendanceController extends Controller
{
    public function index()
    {
        $validated = request()->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $date = isset($validated['date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['date'])
            : now();

        $date->startOfDay();

        $previousDay = $date->copy()->subDay();
        $nextDay = $date->copy()->addDay();

        $users = User::where('is_admin', false)->get();

        $attendanceRecords = AttendanceRecord::whereDate(
            'date',
            $date->toDateString()
        )->get();

        return view('admin.admin-attendance-list', compact(
            'date',
            'previousDay',
            'nextDay',
            'users',
            'attendanceRecords'
        ));
    }
}

