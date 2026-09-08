<?php

use App\Enums\ApprovalStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());

    Storage::fake();

    $this->department = Department::factory()->create();
    $this->position = Position::factory()->employee()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationPayload(array $overrides = []): array
{
    return [
        'name' => 'Test User',
        'email' => 'test@cagsu.edu.ph',
        'password' => 'password',
        'password_confirmation' => 'password',
        'department_id' => test()->department->id,
        'position_id' => test()->position->id,
        'id_proofs' => [UploadedFile::fake()->image('id.png')],
        ...$overrides,
    ];
}

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('registration screen offers selectable departments and positions', function () {
    $archived = Department::factory()->archived()->create();

    $this->get(route('register'))
        ->assertInertia(fn ($page) => $page
            ->component('auth/register')
            ->where('departments.0.id', $this->department->id)
            ->where('positions.0.id', $this->position->id)
            ->count('departments', 1)
        );

    expect($archived->fresh()->is_archived)->toBeTrue();
});

test('new users register as pending and are not signed in', function () {
    $response = $this->post(route('register.store'), registrationPayload());

    $response->assertRedirect(route('register.pending'));
    $this->assertGuest();

    $user = User::where('email', 'test@cagsu.edu.ph')->sole();

    expect($user->approval_status)->toBe(ApprovalStatus::Pending)
        ->and($user->is_active)->toBeFalse()
        ->and($user->department_id)->toBe($this->department->id)
        ->and($user->position_id)->toBe($this->position->id)
        ->and($user->roles)->toBeEmpty();
});

test('registration stores each id proof as a user document', function () {
    $this->post(route('register.store'), registrationPayload([
        'id_proofs' => [
            UploadedFile::fake()->image('front.png'),
            UploadedFile::fake()->create('back.pdf', 100, 'application/pdf'),
        ],
    ]));

    $user = User::where('email', 'test@cagsu.edu.ph')->sole();
    $proofs = $user->idProofs()->get();

    expect($proofs)->toHaveCount(2)
        ->and($proofs->pluck('file_name')->all())->toEqual(['front.png', 'back.pdf'])
        ->and($proofs->pluck('documentable_type')->unique()->all())->toEqual([User::class]);

    $proofs->each(function (Document $proof) {
        expect($proof->file_path)->toStartWith('user-id-proofs/'.now()->format('Y/m').'/');
        Storage::assertExists($proof->file_path);
    });
});

test('registration requires an id proof', function () {
    $payload = registrationPayload();
    unset($payload['id_proofs']);

    $this->post(route('register.store'), $payload)
        ->assertSessionHasErrors('id_proofs');

    expect(User::where('email', 'test@cagsu.edu.ph')->exists())->toBeFalse();
});

test('registration rejects an id proof of a disallowed type', function () {
    $this->post(route('register.store'), registrationPayload([
        'id_proofs' => [UploadedFile::fake()->create('id.exe', 10, 'application/octet-stream')],
    ]))->assertSessionHasErrors('id_proofs.0');

    expect(User::where('email', 'test@cagsu.edu.ph')->exists())->toBeFalse();
});

test('registration rejects an id proof larger than 10 MB', function () {
    $this->post(route('register.store'), registrationPayload([
        'id_proofs' => [UploadedFile::fake()->create('id.pdf', 10241, 'application/pdf')],
    ]))->assertSessionHasErrors('id_proofs.0');

    expect(User::where('email', 'test@cagsu.edu.ph')->exists())->toBeFalse();
});

test('registration requires a department and a position', function () {
    $payload = registrationPayload();
    unset($payload['department_id'], $payload['position_id']);

    $this->post(route('register.store'), $payload)
        ->assertSessionHasErrors(['department_id', 'position_id']);
});

test('registration rejects an archived department', function () {
    $archived = Department::factory()->archived()->create();

    $this->post(route('register.store'), registrationPayload([
        'department_id' => $archived->id,
    ]))->assertSessionHasErrors('department_id');
});
