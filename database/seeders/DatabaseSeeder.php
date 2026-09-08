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
     *
     * SupplierSeeder and AppItemSeeder belong in this list too, but their
     * tables arrive with the catalog slice.
     */
    public function run(): void
    {
        $this->call([
            PositionSeeder::class,
            RolePermissionSeeder::class,
            CollegeSeeder::class,
            ComprehensiveUserSeeder::class,
        ]);
    }
}
