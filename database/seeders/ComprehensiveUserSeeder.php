<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The 22 demo accounts: 12 office users plus one dean per college.
 *
 * Every account is seeded approved, active and verified with the password
 * `password123`, so the demo campus is usable straight after `db:seed`.
 *
 * Code drift: the dean roster below matches colleges on *name* and rewrites
 * three codes that {@see CollegeSeeder} seeds differently — `COA` becomes `CA`,
 * `CTE` becomes `CTED`, and `GRADSCH` becomes `GS`. This seeder runs last and
 * wins. See the table on `CollegeSeeder` for the full picture.
 */
class ComprehensiveUserSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'password123';

    /**
     * Office staff, all in the Administrative Office.
     *
     * @var list<array{email: string, name: string, role: string, position: string}>
     */
    public const OFFICE_USERS = [
        ['email' => 'sysadmin@cagsu.edu.ph', 'name' => 'System Administrator', 'role' => 'System Admin', 'position' => 'System Administrator'],
        ['email' => 'supply@cagsu.edu.ph', 'name' => 'Ronnie S. Agcaoili', 'role' => 'Supply Officer', 'position' => 'Supply Officer'],
        ['email' => 'budget@cagsu.edu.ph', 'name' => 'Catalina B. Talosig', 'role' => 'Budget Office', 'position' => 'Budget Officer'],
        ['email' => 'executive@cagsu.edu.ph', 'name' => 'Rodel Francisco T. Alegado', 'role' => 'Executive Officer', 'position' => 'Executive Officer'],
        ['email' => 'bac.chairman@cagsu.edu.ph', 'name' => 'Christopher R. Garingan', 'role' => 'BAC Chair', 'position' => 'BAC Chairman'],
        ['email' => 'bac.vicechairman@cagsu.edu.ph', 'name' => 'Allan O. De La Cruz', 'role' => 'BAC Chair', 'position' => 'BAC Chairman'],
        ['email' => 'bac.member1@cagsu.edu.ph', 'name' => 'Valentin M. Apostol', 'role' => 'BAC Members', 'position' => 'BAC Member'],
        ['email' => 'bac.member2@cagsu.edu.ph', 'name' => 'Chris Ian T. Rodriguez', 'role' => 'BAC Members', 'position' => 'BAC Member'],
        ['email' => 'bac.member3@cagsu.edu.ph', 'name' => 'Melvin S. Atayan', 'role' => 'BAC Members', 'position' => 'BAC Member'],
        ['email' => 'bac.secretary@cagsu.edu.ph', 'name' => 'Chanda T. Aquino', 'role' => 'BAC Secretariat', 'position' => 'BAC Secretary'],
        ['email' => 'accounting@cagsu.edu.ph', 'name' => 'Fely Jane R. Reyes', 'role' => 'Accounting Office', 'position' => 'Accounting Officer'],
        ['email' => 'canvassing@cagsu.edu.ph', 'name' => 'Chito D. Temporal', 'role' => 'Canvassing Unit', 'position' => 'Canvassing Officer'],
    ];

    /**
     * One dean per college, keyed by the code this seeder enforces. Names are
     * printed as-is on the dean's purchase requests.
     *
     * @var list<array{code: string, name: string, department: string, email: string}>
     */
    public const DEANS = [
        ['code' => 'CALEXT', 'name' => 'Dullit, Rex S., MSA', 'department' => 'Calayan Extension', 'email' => 'calayanextension.sanchezmira@csu.edu.ph'],
        ['code' => 'CA', 'name' => 'Ms. May M. Leaño', 'department' => 'College of Agriculture', 'email' => 'maycmartinez03@csu.edu.ph'],
        ['code' => 'CBEA', 'name' => 'Mr. Rey D. Viloria, CPA', 'department' => 'College of Business Entrepreneurship and Accountancy', 'email' => 'cbea.sanchezmira@csu.edu.ph'],
        ['code' => 'CCJE', 'name' => 'Dr. Jose Sheriff O. Panelo', 'department' => 'College of Criminal Justice Education', 'email' => 'ccje.csusm@csu.edu.ph'],
        ['code' => 'COE', 'name' => 'Engr. Marvin D. Adorio', 'department' => 'College of Engineering', 'email' => 'coe.sanchezmira@csu.edu.ph'],
        ['code' => 'CHM', 'name' => 'Ms. Angela B. Tuliao', 'department' => 'College of Hospitality Management', 'email' => 'angelabtuliao@csu.edu.ph'],
        ['code' => 'CIT', 'name' => 'Ms. Jane Gladys A. Monje', 'department' => 'College of Industrial Technology', 'email' => 'cit.sanchezmira@csu.edu.ph'],
        ['code' => 'CICS', 'name' => 'Dr. Manny S. Alipio', 'department' => 'College of Information and Computing Sciences', 'email' => 'cics_csusm@csu.edu.ph'],
        ['code' => 'CTED', 'name' => 'Dr. Verlino D. Baddu', 'department' => 'College of Teacher Education', 'email' => 'ctedcsusm@csu.edu.ph'],
        ['code' => 'GS', 'name' => 'Dr. Melba B. Rosales', 'department' => 'Graduate School', 'email' => 'graduateschool.sanchezmira@csu.edu.ph'],
    ];

    public function run(): void
    {
        $administrativeOffice = Department::firstOrCreate(
            ['code' => 'ADMIN'],
            ['name' => 'Administrative Office'],
        );

        foreach (self::OFFICE_USERS as $office) {
            $user = $this->upsertUser(
                $office['email'],
                $office['name'],
                $administrativeOffice,
                Position::firstOrCreate(['name' => $office['position']]),
            );

            $user->syncRoles([$office['role']]);
        }

        $collegeDean = Position::firstOrCreate(['name' => 'College Dean']);

        foreach (self::DEANS as $dean) {
            $department = $this->upsertDepartment($dean['department'], $dean['code']);

            $user = $this->upsertUser(
                $dean['email'],
                $dean['name'],
                $department,
                $collegeDean,
            );

            $user->syncRoles(['Dean']);
        }
    }

    /**
     * Match on name so the college keeps its identity while this seeder's code
     * takes precedence.
     */
    private function upsertDepartment(string $name, string $code): Department
    {
        $department = Department::where('name', $name)->first();

        if ($department) {
            $department->update(['code' => $code, 'is_active' => true, 'is_archived' => false]);

            return $department;
        }

        return Department::create([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
            'is_archived' => false,
        ]);
    }

    private function upsertUser(string $email, string $name, Department $department, Position $position): User
    {
        $user = User::firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => $name,
            'password' => Hash::make(self::DEMO_PASSWORD),
            'department_id' => $department->id,
            'position_id' => $position->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'is_archived' => false,
            'approval_status' => ApprovalStatus::Approved,
            'approved_at' => now(),
        ])->save();

        return $user;
    }
}
