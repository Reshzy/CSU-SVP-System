<?php

use App\Enums\PurchaseRequestStatus;
use App\Enums\WorkflowApprovalStatus;
use App\Enums\WorkflowStepName;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestActivity;
use App\Models\User;
use App\Models\WorkflowApproval;
use App\Notifications\PurchaseRequestActionRequired;
use App\Notifications\PurchaseRequestStatusUpdated;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::factory()->create();
    $this->dean = User::factory()->create(['department_id' => $this->department->id]);
    $this->dean->assignRole('Dean');

    $this->budget = User::factory()->create();
    $this->budget->assignRole('Budget Office');

    $this->ceo = User::factory()->create();
    $this->ceo->assignRole('Executive Officer');

    DepartmentBudget::factory()->for($this->department)->create([
        'fiscal_year' => now()->year,
        'allocated_budget' => 1_000_000,
    ]);
});

/**
 * @return array<string, mixed>
 */
function earmarkPayload(array $overrides = []): array
{
    return [
        'legal_basis' => 'Section 86 of RA 9184',
        'earmark_programs_activities' => 'Instruction',
        'earmark_responsibility_center' => 'College of IT',
        'earmark_date_to' => now()->addMonth()->toDateString(),
        'earmark_object_expenditures' => [
            ['code' => '50203010', 'description' => 'Office supplies', 'amount' => 1500],
        ],
        'fund_cluster_code' => '01',
        'fund_details' => 'GAA',
        'budget_code' => 'BUD-1',
        'current_step_notes' => 'Ready for CEO',
        ...$overrides,
    ];
}

function budgetReviewPr(): PurchaseRequest
{
    return PurchaseRequest::factory()->create([
        'department_id' => test()->department->id,
        'requester_id' => test()->dean->id,
        'status' => PurchaseRequestStatus::BudgetOfficeReview,
        'estimated_total' => 20,
    ]);
}

test('a dean cannot open the budget queue', function () {
    $this->actingAs($this->dean)
        ->get(route('budget.purchase-requests.index'))
        ->assertForbidden();
});

test('approving an earmark moves the request to ceo approval and mails both offices', function () {
    Notification::fake();

    $pr = budgetReviewPr();

    $this->actingAs($this->budget)
        ->put(route('budget.purchase-requests.update', $pr), earmarkPayload())
        ->assertRedirect(route('budget.purchase-requests.edit', $pr));

    $pr->refresh();

    expect($pr->status)->toBe(PurchaseRequestStatus::CeoApproval)
        ->and($pr->earmark_id)->toStartWith('EM-')
        ->and($pr->legal_basis)->toBe('Section 86 of RA 9184')
        ->and($pr->funding_source)->toBe('01 - Regular Agency Fund (GAA)');

    $approval = WorkflowApproval::query()->where('purchase_request_id', $pr->id)->sole();

    expect($approval->step_name)->toBe(WorkflowStepName::CeoInitialApproval)
        ->and($approval->status)->toBe(WorkflowApprovalStatus::Pending)
        ->and($approval->approver_id)->toBe($this->ceo->id);

    Notification::assertSentTo($this->ceo, PurchaseRequestActionRequired::class);
    Notification::assertSentTo($this->dean, PurchaseRequestStatusUpdated::class);
});

test('approving still succeeds when no executive officer exists', function () {
    Notification::fake();
    $this->ceo->delete();

    $pr = budgetReviewPr();

    $this->actingAs($this->budget)
        ->put(route('budget.purchase-requests.update', $pr), earmarkPayload())
        ->assertRedirect();

    expect($pr->refresh()->status)->toBe(PurchaseRequestStatus::CeoApproval)
        ->and(WorkflowApproval::query()->count())->toBe(0);

    Notification::assertSentTo($this->dean, PurchaseRequestStatusUpdated::class);
});

test('rejecting an earmark defers the request and releases reserved budget', function () {
    Notification::fake();

    $pr = budgetReviewPr();
    $envelope = DepartmentBudget::query()
        ->where('department_id', $this->department->id)
        ->where('fiscal_year', now()->year)
        ->firstOrFail();

    expect((float) $envelope->reserved_budget)->toBe(20.0);

    $this->actingAs($this->budget)
        ->post(route('budget.purchase-requests.reject', $pr), [
            'rejection_reason' => 'Insufficient allotment.',
        ])
        ->assertRedirect();

    $pr->refresh();
    $envelope->refresh();

    expect($pr->status)->toBe(PurchaseRequestStatus::Rejected)
        ->and($pr->rejection_reason)->toBe('Insufficient allotment.')
        ->and((float) $envelope->reserved_budget)->toBe(0.0);

    Notification::assertSentTo($this->dean, PurchaseRequestStatusUpdated::class);
});

test('rejecting an earmark requires a reason', function () {
    $pr = budgetReviewPr();

    $this->actingAs($this->budget)
        ->post(route('budget.purchase-requests.reject', $pr))
        ->assertSessionHasErrors('rejection_reason');
});

test('amending an earmark does not change workflow status or mail the requester', function () {
    Notification::fake();

    $pr = budgetReviewPr();

    $this->actingAs($this->budget)
        ->put(route('budget.purchase-requests.update', $pr), earmarkPayload())
        ->assertRedirect();

    $pr->refresh();
    $status = $pr->status;
    $earmarkId = $pr->earmark_id;

    $this->actingAs($this->budget)
        ->patch(route('budget.purchase-requests.amend-earmark', $pr), earmarkPayload([
            'legal_basis' => 'Amended legal basis',
            'earmark_programs_activities' => 'Research',
        ]))
        ->assertRedirect(route('budget.purchase-requests.amend', $pr));

    $pr->refresh();

    expect($pr->status)->toBe($status)
        ->and($pr->earmark_id)->toBe($earmarkId)
        ->and($pr->legal_basis)->toBe('Amended legal basis')
        ->and($pr->earmark_programs_activities)->toBe('Research');

    expect(PurchaseRequestActivity::query()->where('action', 'earmark_amended')->count())->toBe(1);

    Notification::assertSentToTimes($this->dean, PurchaseRequestStatusUpdated::class, 1);
});

test('exporting an earmark mints an id when missing', function () {
    $dir = storage_path('app/templates');

    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    copy(
        base_path('tests/Fixtures/EarmarkTemplate.xlsx'),
        $dir.DIRECTORY_SEPARATOR.'EarmarkTemplate.xlsx',
    );

    $pr = budgetReviewPr();

    expect($pr->earmark_id)->toBeNull();

    $this->actingAs($this->budget)
        ->get(route('budget.purchase-requests.export-earmark', $pr))
        ->assertDownload();

    $pr->refresh();

    expect($pr->earmark_id)->toStartWith('EM-');
});

test('exporting an earmark fails clearly when the template is missing', function () {
    $template = storage_path('app/templates/EarmarkTemplate.xlsx');

    if (is_file($template)) {
        unlink($template);
    }

    $pr = budgetReviewPr();

    $this->actingAs($this->budget)
        ->from(route('budget.purchase-requests.edit', $pr))
        ->get(route('budget.purchase-requests.export-earmark', $pr))
        ->assertRedirect(route('budget.purchase-requests.edit', $pr))
        ->assertSessionHasErrors('template');
});
