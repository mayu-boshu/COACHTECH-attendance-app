<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
    ];
    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function getAttendanceStatusAttribute()
    {
        $todayRecord = $this->attendanceRecords()
            ->whereDate('date', today())
            ->first();

        if (!$todayRecord) {
            return '勤務外';
        }

        if ($todayRecord->clock_out) {
            return '退勤済';
        }

        if (
            $todayRecord->breaks()
                ->whereNull('break_end')
                ->exists()
        ) {

            return '休憩中';
        }

        return '出勤中';

    }
    public function getAdminStatusAttribute()
    {
        return $this->is_admin;
    }

}

