<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_record_id',
        'clock_in',
        'clock_out',
        'comment',
        'status',
    ];

    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function breaks()
    {
        return $this->hasMany(ApplicationBreak::class);
    }
}

