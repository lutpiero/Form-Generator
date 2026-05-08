<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FormController as AdminFormController;
use App\Http\Controllers\Admin\FormFieldController;
use App\Http\Controllers\Admin\SubmissionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\FormController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

// Auth routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Public form routes
Route::get('/forms/{form}', [FormController::class, 'show'])->name('forms.show');
Route::post('/forms/{form}', [FormController::class, 'submit'])->name('forms.submit');
Route::get('/forms/{form}/success', [FormController::class, 'success'])->name('forms.success');

// Admin routes (protected)
Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Forms
    Route::resource('forms', AdminFormController::class);

    // Form fields
    Route::get('forms/{form}/fields/create', [FormFieldController::class, 'create'])->name('forms.fields.create');
    Route::post('forms/{form}/fields', [FormFieldController::class, 'store'])->name('forms.fields.store');
    Route::get('forms/{form}/fields/{field}/edit', [FormFieldController::class, 'edit'])->name('forms.fields.edit');
    Route::put('forms/{form}/fields/{field}', [FormFieldController::class, 'update'])->name('forms.fields.update');
    Route::delete('forms/{form}/fields/{field}', [FormFieldController::class, 'destroy'])->name('forms.fields.destroy');
    Route::post('forms/{form}/fields/reorder', [FormFieldController::class, 'reorder'])->name('forms.fields.reorder');

    // Submissions
    Route::get('forms/{form}/submissions', [SubmissionController::class, 'index'])->name('forms.submissions.index');
    Route::get('forms/{form}/submissions/export', [SubmissionController::class, 'export'])->name('forms.submissions.export');
    Route::get('forms/{form}/submissions/export-excel', [SubmissionController::class, 'exportExcel'])->name('forms.submissions.export-excel');
    Route::get('forms/{form}/submissions/{submission}', [SubmissionController::class, 'show'])->name('forms.submissions.show');
    Route::delete('forms/{form}/submissions/{submission}', [SubmissionController::class, 'destroy'])->name('forms.submissions.destroy');

    // Users (admin only)
    Route::resource('users', UserController::class)->middleware('admin');
});
