<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\Request;
use App\Models\AttendanceRecord;
use App\Models\AttendanceBreak;
use App\Models\AttendanceApplication;
use App\Models\ApplicationBreak;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\AttendanceCorrectionRequest;

class AttendanceController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $now = Carbon::now();

        $formattedDate = $now->isoFormat('YYYY年MM月DD日(ddd)');
        $formattedTime = $now->format('H:i');

        return view('user.attendance-register', compact(
            'user',
            'formattedDate',
            'formattedTime'
        ));
    }

    public function store(Request $request)
    {
        $action = $request->input('action');

        $user = Auth::user();

        $todayRecord = $user->attendanceRecords()
            ->whereDate('date', today())
            ->first();

        if ($action === 'clock_in') {
            if (!$todayRecord) {
                AttendanceRecord::create([
                    'user_id' => $user->id,
                    'date' => now()->toDateString(),
                    'clock_in' => now(),
                ]);
            }
        }

        if ($action === 'break_in') {
            $isOnBreak = $todayRecord->breaks()
                ->whereNull('break_end')
                ->exists();

            if (!$isOnBreak) {
                AttendanceBreak::create([
                    'attendance_record_id' => $todayRecord->id,
                    'break_start' => now(),
                ]);
            }
        }

        if ($action === 'break_out') {
            $break = $todayRecord->breaks()
                ->whereNull('break_end')
                ->first();

            if ($break) {
                $break->break_end = now();
                $break->save();
            }
        }

        if ($action === 'clock_out') {
            if ($todayRecord) {
                $todayRecord->clock_out = now();
                $todayRecord->save();
            }
        }
        return redirect('/attendance');
    }

    public function list(): View
    {

        $validated = request()->validate([
            'date' => ['nullable', 'date_format:Y-m'],
        ]);

        $date = isset($validated['date'])
            ? Carbon::createFromFormat('!Y-m', $validated['date'])
            : Carbon::now();

        $date->startOfMonth();

        $startDate = $date->copy()->startOfMonth();
        $endDate = $date->copy()->endOfMonth();

        $user = Auth::user();

        $records = $user->attendanceRecords()
            ->with('breaks')
            ->whereBetween('date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->get()
            ->keyBy(function ($record) {
                return $record->date->toDateString();
            });

        $formattedAttendanceRecords = [];

        for (
            $day = $startDate->copy();
            $day->lte($endDate);
            $day->addDay()
        ) {
            $record = $records->get($day->toDateString());

            $breakSeconds = 0;

            if ($record) {
                foreach ($record->breaks as $break) {
                    if ($break->break_start && $break->break_end) {
                        $breakSeconds += Carbon::parse($break->break_start)
                            ->diffInSeconds(Carbon::parse($break->break_end));
                    }
                }
            }

            $workSeconds = null;

            if ($record && $record->clock_in && $record->clock_out) {
                $workSeconds = max(
                    0,
                    Carbon::parse($record->clock_in)
                        ->diffInSeconds(Carbon::parse($record->clock_out))
                    - $breakSeconds
                );
            }

            $formattedAttendanceRecords[] = [
                'id' => $record?->id,
                'date' => $day->isoFormat('MM/DD(ddd)'),
                'clock_in' => $record && $record->clock_in
                    ? Carbon::parse($record->clock_in)->format('H:i')
                    : '',
                'clock_out' => $record && $record->clock_out
                    ? Carbon::parse($record->clock_out)->format('H:i')
                    : '',
                'total_break_time' => $record
                    ? sprintf(
                        '%02d:%02d:00',
                        intdiv($breakSeconds, 3600),
                        intdiv($breakSeconds % 3600, 60)
                    )
                    : '',
                'total_time' => $workSeconds !== null
                    ? sprintf(
                        '%02d:%02d:00',
                        intdiv($workSeconds, 3600),
                        intdiv($workSeconds % 3600, 60)
                    )
                    : '',
            ];
        }

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');


        return view('user.user-attendance-list', compact(
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }
    public function show($id): View
    {
        $user = Auth::user();

        $attendanceRecord = $user->attendanceRecords()
            ->with(['breaks', 'applications'])
            ->findOrFail($id);

        $breaks = $attendanceRecord->breaks->map(function ($break) {
            return [
                'break_in' => $break->break_start
                    ? Carbon::parse($break->break_start)->format('H:i')
                    : '',
                'break_out' => $break->break_end
                    ? Carbon::parse($break->break_end)->format('H:i')
                    : '',
            ];
        })->toArray();

        $application = $attendanceRecord->applications
            ->where('status', 'pending')
            ->first();

        $data = [
            'id' => $attendanceRecord->id,
            'year' => Carbon::parse($attendanceRecord->date)->format('Y年'),
            'date' => Carbon::parse($attendanceRecord->date)->isoFormat('M月D日(ddd)'),
            'clock_in' => $attendanceRecord->clock_in
                ? Carbon::parse($attendanceRecord->clock_in)->format('H:i')
                : '',
            'clock_out' => $attendanceRecord->clock_out
                ? Carbon::parse($attendanceRecord->clock_out)->format('H:i')
                : '',
            'breaks' => $breaks,
            'comment' => $attendanceRecord->comment ?? '',
            'application' => $application,
        ];

        return view('user.user-detail', compact('user', 'data'));
    }
    public function update(AttendanceCorrectionRequest $request, $id)
    {
        $validated = $request->validated();

        $user = Auth::user();

        $attendanceRecord = $user->attendanceRecords()
            ->findOrFail($id);

        $hasPendingApplication = $attendanceRecord->applications()
            ->where('status', 'pending')
            ->exists();

        if ($hasPendingApplication) {
            return back()->withErrors([
                'application' => '承認待ちのため修正できません',
            ])->withInput();
        }

        $breakIns = $validated['new_break_in'] ?? [];
        $breakOuts = $validated['new_break_out'] ?? [];

        $breakCount = max(count($breakIns), count($breakOuts));

        for ($i = 0; $i < $breakCount; $i++) {
            $breakIn = $breakIns[$i] ?? null;
            $breakOut = $breakOuts[$i] ?? null;

            if (filled($breakIn) && !filled($breakOut)) {
                return back()->withErrors([
                    "new_break_out.$i" => '休憩終了時間を入力してください',
                ])->withInput();
            }

            if (!filled($breakIn) && filled($breakOut)) {
                return back()->withErrors([
                    "new_break_in.$i" => '休憩開始時間を入力してください',
                ])->withInput();
            }
        }

        DB::transaction(function () use (
            $attendanceRecord,
            $validated,
            $breakIns,
            $breakOuts,
            $breakCount
        ) {
            $application = $attendanceRecord->applications()->create([
                'clock_in' => $validated['new_clock_in'],
                'clock_out' => $validated['new_clock_out'] ?: null,
                'comment' => $validated['comment'],
                'status' => 'pending',
            ]);

            for ($i = 0; $i < $breakCount; $i++) {
                $breakIn = $breakIns[$i] ?? null;
                $breakOut = $breakOuts[$i] ?? null;

                if (filled($breakIn) && filled($breakOut)) {
                    $application->breaks()->create([
                        'break_start' => $breakIn,
                        'break_end' => $breakOut,
                    ]);
                }
            }
        });

        return redirect()
            ->route('attendance.show', ['id' => $id])
            ->with('success', '修正申請を送信しました');
    }
    public function applicationList()
    {
        $user = Auth::user();

        $applications = $user->attendanceRecords()
            ->with('applications')
            ->get()
            ->flatMap(function ($record) {
                return $record->applications->map(function ($application) use ($record) {
                    return [
                        'id' => $application->id,
                        'approval_status' => $application->status === 'pending'
                            ? '承認待ち'
                            : '承認済み',
                        'date' => Carbon::parse($record->date)->format('Y/m/d'),
                        'comment' => $application->comment,
                        'application_date' => $application->created_at->format('Y/m/d'),
                    ];
                });
            });

        $formattedApplications = $applications;

        return view('user.user-application-list', compact(
            'user',
            'formattedApplications'
        ));
    }

    public function applicationDetail($id)
    {
        $user = Auth::user();

        $application = AttendanceApplication::with([
            'attendanceRecord',
            'breaks'
        ])
            ->whereHas('attendanceRecord', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->findOrFail($id);

        $attendanceRecord = $application->attendanceRecord;

        $breaks = $application->breaks->map(function ($break) {
            return [
                'break_in' => $break->break_start
                    ? Carbon::parse($break->break_start)->format('H:i')
                    : '',
                'break_out' => $break->break_end
                    ? Carbon::parse($break->break_end)->format('H:i')
                    : '',
            ];
        })->toArray();

        $data = [
            'id' => $attendanceRecord->id,
            'year' => Carbon::parse($attendanceRecord->date)->format('Y年'),
            'date' => Carbon::parse($attendanceRecord->date)->isoFormat('M月D日(ddd)'),
            'clock_in' => $application->clock_in
                ? Carbon::parse($application->clock_in)->format('H:i')
                : '',
            'clock_out' => $application->clock_out
                ? Carbon::parse($application->clock_out)->format('H:i')
                : '',
            'breaks' => $breaks,
            'comment' => $application->comment ?? '',
            'application' => $application,
        ];

        return view('user.user-detail', compact('user', 'data'));
    }
}


