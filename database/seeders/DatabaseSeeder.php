<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters: roles must exist before ComprehensiveUserSeeder assigns
     * them, and colleges before it attaches deans to departments.
     */
    public function run(): void
    {
        $this->call([
            PositionSeeder::class,
            RolePermissionSeeder::class,
            CollegeSeeder::class,
            ComprehensiveUserSeeder::class,
            AppItemSeeder::class,
            SupplierSeeder::class,
        ]);
    }
}
