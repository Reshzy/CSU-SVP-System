<?php

use App\Http\Controllers\Auth\DepartmentRequestController;
use App\Http\Controllers\Ceo\DepartmentController as CeoDepartmentController;
use App\Http\Controllers\Ceo\DepartmentRequestController as CeoDepartmentRequestController;
use App\Http\Controllers\Ceo\UserIdProofController;
use App\Http\Controllers\Ceo\UserManagementController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('health', HealthController::class)->name('health');

Route::middleware('guest')->group(function () {
    Route::inertia('register/pending', 'auth/registration-pending')->name('register.pending');

    Route::get('register/request-department', [DepartmentRequestController::class, 'create'])
        ->name('register.request-department');
    Route::post('register/request-department', [DepartmentRequestController::class, 'store'])
        ->name('register.request-department.store');
});

Route::middleware(['auth', 'verified', 'approved'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::inertia('ui-kit', 'ui-kit')->name('ui-kit');

    Route::middleware('role:Executive Officer')->prefix('ceo')->name('ceo.')->group(function () {
        Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [UserManagementController::class, 'show'])->name('users.show');
        Route::post('users/{user}/approve', [UserManagementController::class, 'approve'])->name('users.approve');
        Route::post('users/{user}/reject', [UserManagementController::class, 'reject'])->name('users.reject');
        Route::get('users/{user}/id-proofs/{document}', UserIdProofController::class)->name('users.id-proof');

        Route::get('departments', [CeoDepartmentController::class, 'index'])->name('departments.index');
        Route::get('departments/create', [CeoDepartmentController::class, 'create'])->name('departments.create');
        Route::post('departments', [CeoDepartmentController::class, 'store'])->name('departments.store');
        Route::get('departments/{department}/edit', [CeoDepartmentController::class, 'edit'])->name('departments.edit');
        Route::put('departments/{department}', [CeoDepartmentController::class, 'update'])->name('departments.update');

        Route::get('department-requests', [CeoDepartmentRequestController::class, 'index'])
            ->name('department-requests.index');
        Route::get('department-requests/{departmentRequest}', [CeoDepartmentRequestController::class, 'show'])
            ->name('department-requests.show');
        Route::post('department-requests/{departmentRequest}/approve', [CeoDepartmentRequestController::class, 'approve'])
            ->name('department-requests.approve');
        Route::post('department-requests/{departmentRequest}/reject', [CeoDepartmentRequestController::class, 'reject'])
            ->name('department-requests.reject');
    });
});

require __DIR__.'/settings.php';
