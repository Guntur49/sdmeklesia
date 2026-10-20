<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\PayrollSlipController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AttendanceController::class, 'login'])->name('login');
Route::post('/login', [AttendanceController::class, 'authenticate'])->name('login.authenticate');
Route::post('/logout', [AttendanceController::class, 'logout'])->name('logout');
Route::middleware('auth')->group(function () {
    Route::middleware('staff')->group(function () {
        Route::get('/staff', [AttendanceController::class, 'staff'])->name('staff');
        Route::post('/staff/attendance', [AttendanceController::class, 'attendance'])->name('staff.attendance');
        Route::post('/staff/leave', [AttendanceController::class, 'leave'])->name('staff.leave');
    });
    Route::middleware('admin')->group(function () {
        Route::get('/admin', [AttendanceController::class, 'admin'])->name('admin');
        Route::get('/admin/staff', [AttendanceController::class, 'staffDetails'])->name('admin.staff');
        Route::post('/admin/staff', [AttendanceController::class, 'storeStaff'])->name('admin.staff.store');
        Route::patch('/admin/staff/{staff}', [AttendanceController::class, 'updateStaff'])->name('admin.staff.update');
        Route::delete('/admin/staff/{staff}', [AttendanceController::class, 'destroyStaff'])->name('admin.staff.destroy');
        Route::post('/admin/location', [AttendanceController::class, 'saveLocation'])->name('admin.location.save');
        Route::post('/admin/dispensation', [AttendanceController::class, 'storeDispensation'])->name('admin.dispensation.store');
        Route::get('/admin/report', [AttendanceController::class, 'report'])->name('admin.report');
        Route::get('/admin/report/pdf', [AttendanceController::class, 'reportPdf'])->name('admin.report.pdf');
        Route::get('/admin/report/download', [AttendanceController::class, 'reportDownload'])->name('admin.report.download');
        Route::get('/admin/payroll-slips', [PayrollSlipController::class, 'index'])->name('admin.payroll-slips.index');
        Route::post('/admin/payroll-slips', [PayrollSlipController::class, 'store'])->name('admin.payroll-slips.store');
        Route::get('/admin/payroll-slips/{payrollSlip}/download', [PayrollSlipController::class, 'download'])->name('admin.payroll-slips.download');
    });
});
