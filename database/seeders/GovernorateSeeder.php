<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Governorate;
use Illuminate\Database\Seeder;

final class GovernorateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $governorates = [
            [
                'name' => 'Cairo',
                'name_ar' => 'القاهرة',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Alexandria',
                'name_ar' => 'الإسكندرية',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Giza',
                'name_ar' => 'الجيزة',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Qalyubia',
                'name_ar' => 'القليوبية',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Sharqia',
                'name_ar' => 'الشرقية',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Dakahlia',
                'name_ar' => 'الدقهلية',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Beheira',
                'name_ar' => 'البحيرة',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Kafr El Sheikh',
                'name_ar' => 'كفر الشيخ',
                'is_active' => true,
                'sort_order' => 8,
            ],
        ];

        foreach ($governorates as $governorate) {
            Governorate::create($governorate);
        }
    }
}
