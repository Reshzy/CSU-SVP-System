<?php

namespace Database\Seeders;

use App\Models\Position;
use App\Support\PositionRoleMap;
use Illuminate\Database\Seeder;

/**
 * Job-title lookup. Each name must stay in sync with
 * {@see PositionRoleMap}, which decides the Spatie role an
 * approved holder of that position receives.
 */
class PositionSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const POSITIONS = [
        'System Administrator',
        'Supply Officer',
        'Employee',
        'Budget Officer',
        'Executive Officer',
        'BAC Chairman',
        'BAC Member',
        'BAC Secretary',
        'Accounting Officer',
        'Canvassing Officer',
    ];

    public function run(): void
    {
        foreach (self::POSITIONS as $name) {
            Position::firstOrCreate(['name' => $name]);
        }
    }
}
