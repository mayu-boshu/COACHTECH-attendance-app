<?php

use App\Http\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminAttendanceController;
use App\Http\Controllers\Admin\AdminApplicationController;
use App\Http\Controllers\Admin\AdminStaffController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/attendance', [AttendanceController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('attendance');

Route::post('/attendance', [AttendanceController::class, 'store'])
    ->middleware(['auth', 'verified']);

Route::get('/admin/login', [AdminAuthController::class, 'showLoginForm']);
Route::post('/admin/login', [AdminAuthController::class, 'login']);

Route::post('/admin/logout', [AdminAuthController::class, 'logout'])
    ->middleware('auth');

Route::get('/admin/attendance', [AdminAttendanceController::class, 'index'])
    ->middleware('admin')
    ->name('admin.attendance');

Route::get('/admin/stamp_correction_request/list', [AdminApplicationController::class, 'index'])
    ->middleware('admin')
    ->name('admin.application.list');

Route::get('/stamp_correction_request/approve/{id}', [AdminApplicationController::class, 'show'])
    ->middleware('admin')
    ->name('admin.application.show');

Route::post('/stamp_correction_request/approve/{id}', [AdminApplicationController::class, 'approve'])
    ->middleware('admin')
    ->name('admin.application.approve');

Route::get('/attendance/list', [AttendanceController::class, 'list'])
    ->middleware(['auth', 'verified'])
    ->name('attendance.list');

Route::get('/attendance/{id}', [AttendanceController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('attendance.show');

Route::post('/attendance/{id}', [AttendanceController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('attendance.update');

Route::get('/stamp_correction_request/list', [AttendanceController::class, 'applicationList'])
    ->middleware(['auth', 'verified'])
    ->name('application.list');

Route::get('/application/{id}', [AttendanceController::class, 'applicationDetail'])
    ->middleware(['auth', 'verified'])
    ->name('application.detail');

Route::get('/admin/staff/list', [AdminStaffController::class, 'index'])
    ->middleware('admin')
    ->name('admin.staff.list');

Route::get('/admin/attendance/staff/{id}', [AdminStaffController::class, 'attendance'])
    ->middleware('admin')
    ->name('admin.staff.attendance');

Route::get('/admin/attendance/detail/{id}', [AdminStaffController::class, 'show'])
    ->middleware('admin')
    ->name('admin.attendance.detail');

Route::post('/admin/attendance/detail/{id}', [AdminStaffController::class, 'update'])
    ->middleware('admin')
    ->name('admin.attendance.update');