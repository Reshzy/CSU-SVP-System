<?php

use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\CollegeSeeder;
use Database\Seeders\ComprehensiveUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PositionSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('the demo seed creates the positions, colleges and 22 users', function () {
    expect(Position::count())->toBe(count(PositionSeeder::POSITIONS))
        ->and(User::count())->toBe(22)
        ->and(Department::count())->toBe(count(CollegeSeeder::COLLEGES) + 1);
});

test('every demo user is approved, active and verified', function () {
    User::each(function (User $user) {
        expect($user->isApproved())->toBeTrue()
            ->and($user->email_verified_at)->not->toBeNull();
    });
});

test('every demo user holds exactly one role', function () {
    User::each(function (User $user) {
        expect($user->getRoleNames())->toHaveCount(1);
    });
});

test('demo users can log in with the seeded password', function (string $email, string $role) {
    $this->post(route('login.store'), [
        'email' => $email,
        'password' => ComprehensiveUserSeeder::DEMO_PASSWORD,
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('dashboard'))->assertOk();

    expect(auth()->user()->getPrimarySVPRole())->toBe($role);
})->with([
    ['supply@cagsu.edu.ph', 'Supply Officer'],
    ['executive@cagsu.edu.ph', 'Executive Officer'],
    ['sysadmin@cagsu.edu.ph', 'System Admin'],
    ['budget@cagsu.edu.ph', 'Budget Office'],
    ['accounting@cagsu.edu.ph', 'Accounting Office'],
    ['maycmartinez03@csu.edu.ph', 'Dean'],
]);

test('the seeded executive officer can reach the CEO queues', function () {
    $this->actingAs(User::where('email', 'executive@cagsu.edu.ph')->sole());

    $this->get(route('ceo.users.index'))->assertOk();
    $this->get(route('ceo.departments.index'))->assertOk();
    $this->get(route('ceo.department-requests.index'))->assertOk();
});

test('the seeded supply officer cannot reach the CEO queues', function () {
    $this->actingAs(User::where('email', 'supply@cagsu.edu.ph')->sole())
        ->get(route('ceo.users.index'))
        ->assertForbidden();
});

/**
 * ComprehensiveUserSeeder rewrites three CollegeSeeder codes. Only the codes
 * below survive a full seed, so fixtures must never assume `COA`, `CTE` or
 * `GRADSCH` exist.
 */
test('ComprehensiveUserSeeder wins the college code drift', function () {
    expect(Department::pluck('code')->sort()->values()->all())->toEqual([
        'ADMIN',
        'CA',
        'CALEXT',
        'CBEA',
        'CCJE',
        'CHM',
        'CICS',
        'CIT',
        'COE',
        'CTED',
        'GS',
    ]);
});

test('the drifted codes do not coexist with the CollegeSeeder originals', function (string $code, string $name) {
    expect(Department::where('code', $code)->exists())->toBeFalse()
        ->and(Department::where('name', $name)->count())->toBe(1);
})->with([
    ['COA', 'College of Agriculture'],
    ['CTE', 'College of Teacher Education'],
    ['GRADSCH', 'Graduate School'],
]);

test('each dean is attached to their own college', function () {
    foreach (ComprehensiveUserSeeder::DEANS as $dean) {
        $user = User::where('email', $dean['email'])->sole();

        expect($user->department->code)->toBe($dean['code'])
            ->and($user->department->name)->toBe($dean['department'])
            ->and($user->getRoleNames()->all())->toEqual(['Dean']);
    }
});

test('reseeding is idempotent', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(22)
        ->and(Department::count())->toBe(count(CollegeSeeder::COLLEGES) + 1);
});
