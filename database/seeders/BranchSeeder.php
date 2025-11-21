<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

final class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            [
                'name' => 'Main Branch',
                'link' => 'https://pos.example.com/api/main',
                'is_active' => true,
            ],
            [
                'name' => 'Downtown Branch',
                'link' => 'https://pos.example.com/api/downtown',
                'is_active' => true,
            ],
            [
                'name' => 'Airport Branch',
                'link' => 'https://pos.example.com/api/airport',
                'is_active' => true,
            ],
        ];

        foreach ($branches as $branch) {
            Branch::create($branch);
        }
    }
}
