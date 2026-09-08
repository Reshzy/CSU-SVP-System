<?php

use App\Http\Controllers\Api\BudgetCheckController;
use App\Http\Controllers\AppConsolidationController;
use App\Http\Controllers\AppItemController;
use App\Http\Controllers\Auth\DepartmentRequestController;
use App\Http\Controllers\Ceo\DepartmentController as CeoDepartmentController;
use App\Http\Controllers\Ceo\DepartmentRequestController as CeoDepartmentRequestController;
use App\Http\Controllers\Ceo\UserIdProofController;
use App\Http\Controllers\Ceo\UserManagementController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PpmpController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\Supply\PurchaseRequestController as SupplyPurchaseRequestController;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
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

    // Planning is open to every approved user; PpmpPolicy scopes each plan to
    // the department that owns it.
    Route::prefix('ppmp')->name('ppmp.')->group(function () {
        Route::get('/', [PpmpController::class, 'index'])->name('index');
        Route::get('import', [PpmpController::class, 'import'])->name('import');
        Route::post('import', [PpmpController::class, 'processImport'])->name('import.process');
        Route::get('create', [PpmpController::class, 'create'])->name('create');
        Route::post('/', [PpmpController::class, 'store'])->name('store');
        Route::get('{ppmp}/edit', [PpmpController::class, 'edit'])->name('edit');
        Route::put('{ppmp}', [PpmpController::class, 'update'])->name('update');
        Route::post('{ppmp}/validate', [PpmpController::class, 'validate'])->name('validate');
        Route::get('{ppmp}/summary', [PpmpController::class, 'summary'])->name('summary');
    });

    Route::middleware('can:manage-ps-dbms')->prefix('reference/ps-dbms')->name('ps-dbms.')->group(function () {
        Route::get('/', [AppItemController::class, 'index'])->name('index');
        Route::get('import', [AppItemController::class, 'import'])->name('import');
        Route::post('import', [AppItemController::class, 'processImport'])->name('process');
    });

    Route::get('bac/app', AppConsolidationController::class)
        ->middleware('can:view-consolidated-app')
        ->name('bac.app.index');

    Route::resource('purchase-requests', PurchaseRequestController::class)
        ->only(['index', 'create', 'show']);
    Route::post('purchase-requests', [PurchaseRequestController::class, 'store'])
        ->middleware(HandlePrecognitiveRequests::class)
        ->name('purchase-requests.store');

    Route::get('api/budget/check', [BudgetCheckController::class, 'check'])
        ->name('api.budget.check');
    Route::post('api/budget/validate', [BudgetCheckController::class, 'validateAmount'])
        ->name('api.budget.validate');

    Route::get('purchase-requests/{originalPr}/replacement/create', [PurchaseRequestController::class, 'createReplacement'])
        ->name('purchase-requests.replacement.create');
    Route::post('purchase-requests/{originalPr}/replacement', [PurchaseRequestController::class, 'storeReplacement'])
        ->middleware(HandlePrecognitiveRequests::class)
        ->name('purchase-requests.replacement.store');

    Route::middleware('can:edit-purchase-request')->prefix('supply')->name('supply.')->group(function () {
        Route::get('purchase-requests', [SupplyPurchaseRequestController::class, 'index'])
            ->name('purchase-requests.index');
        Route::get('purchase-requests/{purchase_request}', [SupplyPurchaseRequestController::class, 'show'])
            ->name('purchase-requests.show');
        Route::post('purchase-requests/{purchase_request}/status', [SupplyPurchaseRequestController::class, 'updateStatus'])
            ->name('purchase-requests.status');
        Route::post('purchase-requests/{purchase_request}/lots', [SupplyPurchaseRequestController::class, 'storeLot'])
            ->name('purchase-requests.lots.store');
        Route::put('purchase-requests/{purchase_request}/lots/{lot}', [SupplyPurchaseRequestController::class, 'updateLot'])
            ->name('purchase-requests.lots.update');
        Route::delete('purchase-requests/{purchase_request}/lots/{lot}', [SupplyPurchaseRequestController::class, 'destroyLot'])
            ->name('purchase-requests.lots.destroy');
    });

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
