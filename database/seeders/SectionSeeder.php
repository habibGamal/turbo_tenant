<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;

final class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sections = [
            [
                'title' => 'Featured Dishes',
                'location' => 'homepage',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'Popular Items',
                'location' => 'homepage',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'title' => 'New Arrivals',
                'location' => 'homepage',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'title' => 'Chef Specials',
                'location' => 'homepage',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'title' => 'Best Sellers',
                'location' => 'menu',
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($sections as $section) {
            Section::create($section);
        }
    }
}
