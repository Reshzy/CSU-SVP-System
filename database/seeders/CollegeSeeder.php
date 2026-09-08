<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * The ten academic colleges.
 *
 * Known code drift: three of the codes below are overwritten by
 * {@see ComprehensiveUserSeeder}, which runs immediately after this seeder and
 * matches its dean roster on department *name*:
 *
 * | Name                  | Seeded here | After ComprehensiveUserSeeder |
 * |-----------------------|-------------|-------------------------------|
 * | College of Agriculture| `COA`       | `CA`                          |
 * | College of Teacher Education | `CTE` | `CTED`                       |
 * | Graduate School       | `GRADSCH`   | `GS`                          |
 *
 * After a full `db:seed` the surviving set is `CA` / `CTED` / `GS`. Both codes
 * never coexist, so tests and fixtures must assume only the post-seed set.
 * `DepartmentSeededCodesTest` locks this in.
 */
class CollegeSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    public const COLLEGES = [
        'CALEXT' => 'Calayan Extension',
        'COA' => 'College of Agriculture',
        'CBEA' => 'College of Business Entrepreneurship and Accountancy',
        'CCJE' => 'College of Criminal Justice Education',
        'COE' => 'College of Engineering',
        'CHM' => 'College of Hospitality Management',
        'CIT' => 'College of Industrial Technology',
        'CICS' => 'College of Information and Computing Sciences',
        'CTE' => 'College of Teacher Education',
        'GRADSCH' => 'Graduate School',
    ];

    public function run(): void
    {
        foreach (self::COLLEGES as $code => $name) {
            $department = Department::where('name', $name)->orWhere('code', $code)->first();

            $attributes = [
                'name' => $name,
                'code' => $code,
                'is_active' => true,
                'is_archived' => false,
            ];

            if ($department) {
                $department->update($attributes);

                continue;
            }

            Department::create($attributes);
        }
    }
}
