<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\AttendanceRecord;
use Illuminate\Database\Seeder;
use Carbon\CarbonPeriod;

class AttendanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $demoUsers = [
            [
                'name' => 'ユーザー1',
                'email' => 'user1@example.com',
                'is_admin' => false,
            ],
            [
                'name' => 'ユーザー2',
                'email' => 'user2@example.com',
                'is_admin' => false,
            ],
            [
                'name' => 'ユーザー3',
                'email' => 'user3@example.com',
                'is_admin' => true,
            ],
        ];

        foreach ($demoUsers as $demoUser) {
            User::firstOrCreate(
                ['email' => $demoUser['email']],
                [
                    'name' => $demoUser['name'],
                    'password' => 'password',
                    'is_admin' => $demoUser['is_admin'],
                    'email_verified_at' => now(),
                ]
            );
        }

        $users = User::all();

        $period = CarbonPeriod::create(
            '2026-10-01',
            '2026-11-11'
        );

        foreach ($users as $user) {
            foreach ($period as $date) {
                if ($date->isWeekend()) {
                    continue;
                }

                $day = $date->format('Y-m-d');

                $attendance = AttendanceRecord::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'date' => $day,
                    ],
                    [
                        'clock_in' => '09:00:00',
                        'clock_out' => '18:00:00',
                    ]
                );

                if ($attendance->breaks()->exists()) {
                    continue;
                }

                if (
                    $attendance->clock_out &&
                    $attendance->clock_in <= '12:00:00' &&
                    $attendance->clock_out >= '13:00:00'
                ) {
                    $attendance->breaks()->create([
                        'break_start' => '12:00:00',
                        'break_end' => '13:00:00',
                    ]);
                }
            }
        }
    }
}