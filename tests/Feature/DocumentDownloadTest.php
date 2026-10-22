<?php

use App\Models\Department;
use App\Models\Document;
use App\Models\PurchaseRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
    Storage::fake('public');

    $this->department = Department::factory()->create();
});

test('a supply officer can download a local bac document on a purchase request', function () {
    $officer = User::factory()->create(['department_id' => $this->department->id]);
    $officer->assignRole('Supply Officer');
    $purchaseRequest = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'status' => 'bac_evaluation',
    ]);
    Storage::disk('local')->put('rfq/test-rfq.docx', 'fake rfq content');
    $document = Document::factory()->forPurchaseRequest($purchaseRequest)->create([
        'file_path' => 'rfq/test-rfq.docx',
        'file_name' => 'test-rfq.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'is_public' => false,
        'uploaded_by' => $officer->id,
        'status' => 'approved',
    ]);

    $this->actingAs($officer)
        ->get(route('files.show', $document))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
});

test('a bac chair can download a local resolution document', function () {
    $chair = User::factory()->create(['department_id' => $this->department->id]);
    $chair->assignRole('BAC Chair');
    $purchaseRequest = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'status' => 'bac_evaluation',
    ]);
    Storage::disk('local')->put('resolutions/test-resolution.docx', 'fake resolution content');
    $document = Document::factory()->forPurchaseRequest($purchaseRequest)->create([
        'document_type' => 'bac_resolution',
        'file_path' => 'resolutions/test-resolution.docx',
        'file_name' => 'test-resolution.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'is_public' => false,
        'status' => 'approved',
    ]);

    $this->actingAs($chair)
        ->get(route('files.show', $document))
        ->assertOk();
});

test('a public disk document can be downloaded', function () {
    $officer = User::factory()->create(['department_id' => $this->department->id]);
    $officer->assignRole('Supply Officer');
    $purchaseRequest = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
    ]);
    Storage::disk('public')->put('quotations/test-quotation.pdf', 'fake quotation content');
    $document = Document::factory()->forPurchaseRequest($purchaseRequest)->create([
        'document_type' => 'quotation_file',
        'file_path' => 'quotations/test-quotation.pdf',
        'file_name' => 'test-quotation.pdf',
        'mime_type' => 'application/pdf',
        'is_public' => false,
        'uploaded_by' => $officer->id,
        'status' => 'approved',
    ]);

    $this->actingAs($officer)
        ->get(route('files.show', $document))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('an end user from another department cannot download a private pr document', function () {
    $otherDepartment = Department::factory()->create();
    $owner = User::factory()->create(['department_id' => $this->department->id]);
    $owner->assignRole('End User');
    $stranger = User::factory()->create(['department_id' => $otherDepartment->id]);
    $stranger->assignRole('End User');
    $purchaseRequest = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $owner->id,
    ]);
    Storage::disk('local')->put('test/test-doc.pdf', 'fake content');
    $document = Document::factory()->forPurchaseRequest($purchaseRequest)->create([
        'document_type' => 'other',
        'file_path' => 'test/test-doc.pdf',
        'file_name' => 'test-doc.pdf',
        'mime_type' => 'application/pdf',
        'is_public' => false,
        'uploaded_by' => $owner->id,
        'status' => 'approved',
    ]);

    $this->actingAs($stranger)
        ->get(route('files.show', $document))
        ->assertForbidden();
});

test('guests are redirected to login', function () {
    $purchaseRequest = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
    ]);
    Storage::disk('local')->put('test/test-doc.pdf', 'fake content');
    $document = Document::factory()->forPurchaseRequest($purchaseRequest)->create([
        'file_path' => 'test/test-doc.pdf',
        'file_name' => 'test-doc.pdf',
        'is_public' => false,
    ]);

    $this->get(route('files.show', $document))
        ->assertRedirect(route('login'));
});

test('a missing file returns 404', function () {
    $admin = User::factory()->create(['department_id' => $this->department->id]);
    $admin->assignRole('System Admin');
    $purchaseRequest = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
    ]);
    $document = Document::factory()->forPurchaseRequest($purchaseRequest)->create([
        'file_path' => 'nonexistent/missing.pdf',
        'file_name' => 'missing.pdf',
        'uploaded_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('files.show', $document))
        ->assertNotFound();
});

test('a public document is visible to any authenticated user', function () {
    $endUser = User::factory()->create(['department_id' => $this->department->id]);
    $endUser->assignRole('End User');
    Storage::disk('public')->put('public/notice.pdf', 'public notice');
    $document = Document::factory()->create([
        'is_public' => true,
        'file_path' => 'public/notice.pdf',
        'file_name' => 'notice.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $this->actingAs($endUser)
        ->get(route('files.show', $document))
        ->assertOk();
});

test('visible_to_roles allows the listed role on a user-morph document', function () {
    $budgetOfficer = User::factory()->create();
    $budgetOfficer->assignRole('Budget Office');
    $dean = User::factory()->create(['department_id' => $this->department->id]);
    $dean->assignRole('Dean');
    Storage::disk('local')->put('restricted/note.pdf', 'restricted');
    $document = Document::factory()->create([
        'is_public' => false,
        'visible_to_roles' => ['Budget Office'],
        'file_path' => 'restricted/note.pdf',
        'file_name' => 'note.pdf',
        'uploaded_by' => null,
    ]);

    $this->actingAs($budgetOfficer)
        ->get(route('files.show', $document))
        ->assertOk();

    $this->actingAs($dean)
        ->get(route('files.show', $document))
        ->assertForbidden();
});
