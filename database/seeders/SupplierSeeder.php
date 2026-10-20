<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * @var list<array{business_name: string, email: string}>
     */
    public const SUPPLIERS = [
        ['business_name' => 'Lienmavel', 'email' => 'lienmavel@supplier.local'],
        ['business_name' => 'ME', 'email' => 'me@supplier.local'],
        ['business_name' => 'Mr. DIY', 'email' => 'mrdiy@supplier.local'],
        ['business_name' => 'AW Commercial', 'email' => 'aw@supplier.local'],
        ['business_name' => 'Pandayan', 'email' => 'pandayan@supplier.local'],
        ['business_name' => 'Derima', 'email' => 'derima@supplier.local'],
        ['business_name' => 'Migrants', 'email' => 'migrants@supplier.local'],
    ];

    public function run(): void
    {
        foreach (self::SUPPLIERS as $index => $row) {
            Supplier::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'supplier_code' => sprintf('SUP-%d-%04d', now()->year, $index + 1),
                    'business_name' => $row['business_name'],
                    'business_type' => 'corporation',
                    'contact_person' => $row['business_name'].' liaison',
                    'phone' => '078-000-'.sprintf('%04d', $index + 1),
                    'address' => 'Sanchez Mira, Cagayan',
                    'status' => 'active',
                    'password' => null,
                ],
            );
        }
    }
}
