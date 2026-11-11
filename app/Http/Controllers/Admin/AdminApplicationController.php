<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceApplication;
use Illuminate\Support\Facades\DB;

class AdminApplicationController extends Controller
{
    public function index()
    {
        $applications = AttendanceApplication::with([
            'attendanceRecord.user',
            'breaks',
        ])->latest()->get();

        foreach ($applications as $application) {
            $application->setAttribute(
                'approval_status',
                $application->status === 'pending' ? '承認待ち' : '承認済み'
            );

            $application->setRelation(
                'user',
                $application->attendanceRecord->user
            );
        }

        return view('admin.admin-application-list', compact('applications'));
    }

    public function show($id)
    {
        $application = AttendanceApplication::with([
            'attendanceRecord.user',
            'breaks',
        ])->findOrFail($id);

        $user = $application->attendanceRecord->user;

        $application->setAttribute(
            'new_date',
            $application->attendanceRecord->date
        );

        $application->setAttribute('new_clock_in', $application->clock_in);
        $application->setAttribute('new_clock_out', $application->clock_out);

        $application->setAttribute(
            'approval_status',
            $application->status === 'pending' ? '承認待ち' : '承認済み'
        );

        $application->setRelation('proposalBreaks', $application->breaks);

        return view('admin.admin-application-detail', compact(
            'application',
            'user'
        ));
    }

    public function approve($id)
    {
        $application = AttendanceApplication::with([
            'attendanceRecord',
            'breaks',
        ])->findOrFail($id);

        if ($application->status !== 'pending') {
            return redirect('/stamp_correction_request/approve/' . $id);
        }

        DB::transaction(function () use ($application) {
            $attendanceRecord = $application->attendanceRecord;

            $attendanceRecord->update([
                'clock_in' => $application->clock_in,
                'clock_out' => $application->clock_out,
            ]);

            $attendanceRecord->breaks()->delete();

            foreach ($application->breaks as $break) {
                $attendanceRecord->breaks()->create([
                    'break_start' => $break->break_start,
                    'break_end' => $break->break_end,
                ]);
            }

            $application->update([
                'status' => 'approved',
            ]);
        });

        return redirect('/stamp_correction_request/approve/' . $id);
    }
}
