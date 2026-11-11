<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\User;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminStaffController extends Controller
{
    public function index()
    {
        $users = User::where('is_admin', false)->get();

        return view('admin.staff-list', compact('users'));
    }

    public function attendance($id)
    {
        $validated = request()->validate([
            'date' => ['nullable', 'date_format:Y-m'],
        ]);

        $user = User::where('is_admin', false)->findOrFail($id);

        $date = isset($validated['date'])
            ? Carbon::createFromFormat('!Y-m', $validated['date'])
            : Carbon::now();

        $date->startOfMonth();

        $startDate = $date->copy()->startOfMonth();
        $endDate = $date->copy()->endOfMonth();

        $records = $user->attendanceRecords()
            ->with('breaks')
            ->whereBetween('date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->get()
            ->keyBy(fn($record) => $record->date->toDateString());

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
                    ? sprintf('%02d:%02d:00',
                        intdiv($breakSeconds, 3600),
                        intdiv($breakSeconds % 3600, 60))
                    : '',
                'total_time' => $workSeconds !== null
                    ? sprintf('%02d:%02d:00',
                        intdiv($workSeconds, 3600),
                        intdiv($workSeconds % 3600, 60))
                    : '',
            ];
        }

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        return view('admin.staff-attendance-list', compact(
            'user',
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }

    public function show($id)
    {
        $record = AttendanceRecord::with(['user', 'breaks'])
            ->whereHas('user', function ($query) {
                $query->where('is_admin', false);
            })
            ->findOrFail($id);

        $breaks = $record->breaks->map(function ($break) {
            return [
                'break_in' => $break->break_start
                    ? Carbon::parse($break->break_start)->format('H:i')
                    : '',
                'break_out' => $break->break_end
                    ? Carbon::parse($break->break_end)->format('H:i')
                    : '',
            ];
        })->toArray();

        $attendanceRecord = [
            'id' => $record->id,
            'year' => Carbon::parse($record->date)->format('Y年'),
            'date' => Carbon::parse($record->date)->isoFormat('M月D日(ddd)'),
            'clock_in' => $record->clock_in
                ? Carbon::parse($record->clock_in)->format('H:i')
                : '',
            'clock_out' => $record->clock_out
                ? Carbon::parse($record->clock_out)->format('H:i')
                : '',
            'breaks' => $breaks,
            'comment' => $record->comment ?? '',
        ];

        $user = $record->user;

        return view('admin.admin-detail', compact(
            'user',
            'attendanceRecord'
        ));
    }

    public function update(AttendanceCorrectionRequest $request, $id)
    {
        $validated = $request->validated();

        $record = AttendanceRecord::whereHas('user', function ($query) {
            $query->where('is_admin', false);
        })->findOrFail($id);

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
            $record,
            $validated,
            $breakIns,
            $breakOuts,
            $breakCount
        ) {
            $record->update([
                'clock_in' => $validated['new_clock_in'],
                'clock_out' => $validated['new_clock_out'] ?: null,
                'comment' => $validated['comment'],
            ]);

            $record->breaks()->delete();

            for ($i = 0; $i < $breakCount; $i++) {
                $breakIn = $breakIns[$i] ?? null;
                $breakOut = $breakOuts[$i] ?? null;

                if (filled($breakIn) && filled($breakOut)) {
                    $record->breaks()->create([
                        'break_start' => $breakIn,
                        'break_end' => $breakOut,
                    ]);
                }
            }
        });

        return redirect()->route('admin.staff.attendance', [
            'id' => $record->user_id,
            'date' => Carbon::parse($record->date)->format('Y-m'),
        ])->with('success', '勤怠を修正しました');
    }
}
