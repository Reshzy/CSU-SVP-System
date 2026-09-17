<?php

use App\Http\Controllers\Api\BudgetCheckController;
use App\Http\Controllers\AppConsolidationController;
use App\Http\Controllers\AppItemController;
use App\Http\Controllers\Auth\DepartmentRequestController;
use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\Bac\MeetingController as BacMeetingController;
use App\Http\Controllers\Bac\QuotationController as BacQuotationController;
use App\Http\Controllers\Bac\SignatoryController as BacSignatoryController;
use App\Http\Controllers\Budget\DepartmentBudgetController;
use App\Http\Controllers\Budget\EarmarkController;
use App\Http\Controllers\Ceo\DepartmentController as CeoDepartmentController;
use App\Http\Controllers\Ceo\DepartmentRequestController as CeoDepartmentRequestController;
use App\Http\Controllers\Ceo\PurchaseRequestController as CeoPurchaseRequestController;
use App\Http\Controllers\Ceo\UserIdProofController;
use App\Http\Controllers\Ceo\UserManagementController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PpmpController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\PurchaseRequestReplacementController;
use App\Http\Controllers\Supply\PurchaseRequestController as SupplyPurchaseRequestController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('health', HealthController::class)->name('health');

Route::middleware('guest')->group(function () {
    Route::inertia('register/pending', 'auth/registration-pending')->name('register.pending');

    Route::get('register/request-department', [DepartmentRequestController::class, 'create'])
        ->name('register.request-department');
    Route::post('register/request-department', [DepartmentRequestController::class, 'store'])
        ->name('register.request-department.store');

    Route::post('dev/login/{user}', [DevLoginController::class, 'store'])
        ->name('dev.login');
});

Route::middleware(['auth', 'verified', 'approved'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::inertia('ui-kit', 'ui-kit')->name('ui-kit');

    Route::prefix('purchase-requests')->name('purchase-requests.')->group(function () {
        Route::get('/', [PurchaseRequestController::class, 'index'])->name('index');
        Route::get('create', [PurchaseRequestController::class, 'create'])->name('create');
        Route::post('/', [PurchaseRequestController::class, 'store'])->name('store');
        Route::get('{purchaseRequest}', [PurchaseRequestController::class, 'show'])->name('show');
        Route::get('{purchaseRequest}/replacement/create', [PurchaseRequestReplacementController::class, 'create'])
            ->name('replacement.create');
        Route::post('{purchaseRequest}/replacement', [PurchaseRequestReplacementController::class, 'store'])
            ->name('replacement.store');
    });

    Route::middleware('can:edit-purchase-request')->prefix('supply')->name('supply.')->group(function () {
        Route::get('purchase-requests', [SupplyPurchaseRequestController::class, 'index'])
            ->name('purchase-requests.index');
        Route::get('purchase-requests/{purchaseRequest}', [SupplyPurchaseRequestController::class, 'show'])
            ->name('purchase-requests.show');
        Route::post('purchase-requests/{purchaseRequest}/status', [SupplyPurchaseRequestController::class, 'updateStatus'])
            ->name('purchase-requests.status');
        Route::post('purchase-requests/{purchaseRequest}/lots', [SupplyPurchaseRequestController::class, 'storeLot'])
            ->name('purchase-requests.lots.store');
        Route::put('purchase-requests/{purchaseRequest}/lots/{lot}', [SupplyPurchaseRequestController::class, 'updateLot'])
            ->name('purchase-requests.lots.update');
        Route::delete('purchase-requests/{purchaseRequest}/lots/{lot}', [SupplyPurchaseRequestController::class, 'destroyLot'])
            ->name('purchase-requests.lots.destroy');
        Route::get('purchase-requests/{purchaseRequest}/export', [SupplyPurchaseRequestController::class, 'export'])
            ->name('purchase-requests.export');
    });

    Route::get('api/budget/check', [BudgetCheckController::class, 'check'])->name('api.budget.check');
    Route::post('api/budget/validate', [BudgetCheckController::class, 'validateAmount'])->name('api.budget.validate');

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

    Route::middleware('role:BAC Chair|BAC Members|BAC Secretariat')->prefix('bac')->name('bac.')->group(function () {
        Route::get('quotations', [BacQuotationController::class, 'index'])->name('quotations.index');
        Route::get('quotations/{purchaseRequest}/manage', [BacQuotationController::class, 'manage'])->name('quotations.manage');
        Route::post('quotations/{purchaseRequest}', [BacQuotationController::class, 'store'])->name('quotations.store');
        Route::post('quotations/{quotation}/evaluate', [BacQuotationController::class, 'evaluate'])->name('quotations.evaluate');
        Route::post('quotations/{purchaseRequest}/finalize', [BacQuotationController::class, 'finalize'])->name('quotations.finalize');

        Route::get('quotations/{purchaseRequest}/resolution/download', [BacQuotationController::class, 'downloadResolution'])->name('quotations.resolution.download');
        Route::post('quotations/{purchaseRequest}/resolution/regenerate', [BacQuotationController::class, 'regenerateResolution'])->name('quotations.resolution.regenerate');

        Route::post('quotations/{purchaseRequest}/rfq/generate', [BacQuotationController::class, 'generateRfq'])->name('quotations.rfq.generate');
        Route::get('quotations/{purchaseRequest}/rfq/download', [BacQuotationController::class, 'downloadRfq'])->name('quotations.rfq.download');
        Route::post('quotations/{purchaseRequest}/rfq/regenerate', [BacQuotationController::class, 'regenerateRfq'])->name('quotations.rfq.regenerate');

        Route::get('quotations/{purchaseRequest}/aoq', [BacQuotationController::class, 'aoq'])->name('quotations.aoq');
        Route::post('quotations/{purchaseRequest}/aoq/generate', [BacQuotationController::class, 'generateAoq'])->name('quotations.aoq.generate');
        Route::get('quotations/{purchaseRequest}/aoq/download', [BacQuotationController::class, 'downloadAoq'])->name('quotations.aoq.download');
        Route::post('quotations/{purchaseRequest}/aoq/resolve-tie', [BacQuotationController::class, 'resolveTie'])->name('quotations.aoq.resolve-tie');
        Route::post('quotations/{purchaseRequest}/aoq/bac-override', [BacQuotationController::class, 'bacOverride'])->name('quotations.aoq.bac-override');

        Route::get('meetings', [BacMeetingController::class, 'index'])->name('meetings.index');
        Route::get('meetings/create', [BacMeetingController::class, 'create'])->name('meetings.create');
        Route::post('meetings', [BacMeetingController::class, 'store'])->name('meetings.store');
        Route::get('meetings/{meeting}', [BacMeetingController::class, 'show'])->name('meetings.show');
    });

    Route::middleware('role:System Admin|BAC Chair')->prefix('bac')->name('bac.')->group(function () {
        Route::get('signatories', [BacSignatoryController::class, 'index'])->name('signatories.index');
        Route::get('signatories/create', [BacSignatoryController::class, 'create'])->name('signatories.create');
        Route::post('signatories', [BacSignatoryController::class, 'store'])->name('signatories.store');
        Route::get('signatories/{signatory}/edit', [BacSignatoryController::class, 'edit'])->name('signatories.edit');
        Route::put('signatories/{signatory}', [BacSignatoryController::class, 'update'])->name('signatories.update');
        Route::delete('signatories/{signatory}', [BacSignatoryController::class, 'destroy'])->name('signatories.destroy');
    });

    Route::middleware('role:Budget Office')->prefix('budget')->name('budget.')->group(function () {
        Route::get('departments', [DepartmentBudgetController::class, 'index'])->name('index');
        Route::post('departments', [DepartmentBudgetController::class, 'store'])->name('store');
        Route::put('departments/{departmentBudget}', [DepartmentBudgetController::class, 'update'])->name('update');

        Route::get('purchase-requests', [EarmarkController::class, 'index'])->name('purchase-requests.index');
        Route::get('purchase-requests/{purchaseRequest}/edit', [EarmarkController::class, 'edit'])->name('purchase-requests.edit');
        Route::put('purchase-requests/{purchaseRequest}', [EarmarkController::class, 'update'])->name('purchase-requests.update');
        Route::post('purchase-requests/{purchaseRequest}/reject', [EarmarkController::class, 'reject'])->name('purchase-requests.reject');
        Route::patch('purchase-requests/{purchaseRequest}/amend-earmark', [EarmarkController::class, 'amend'])->name('purchase-requests.amend-earmark');
        Route::get('purchase-requests/{purchaseRequest}/export-earmark', [EarmarkController::class, 'export'])->name('purchase-requests.export-earmark');
    });

    Route::middleware('role:Executive Officer')->prefix('ceo')->name('ceo.')->group(function () {
        Route::get('purchase-requests', [CeoPurchaseRequestController::class, 'index'])->name('purchase-requests.index');
        Route::get('purchase-requests/{purchaseRequest}', [CeoPurchaseRequestController::class, 'show'])->name('purchase-requests.show');
        Route::post('purchase-requests/{purchaseRequest}', [CeoPurchaseRequestController::class, 'update'])->name('purchase-requests.update');

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
