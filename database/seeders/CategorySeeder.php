<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

final class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Pizza',
                'description' => 'Delicious pizzas with various toppings',
                'image' => null,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Burgers',
                'description' => 'Juicy burgers made with fresh ingredients',
                'image' => null,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Salads',
                'description' => 'Fresh and healthy salad options',
                'image' => null,
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Desserts',
                'description' => 'Sweet treats and delicious desserts',
                'image' => null,
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Drinks',
                'description' => 'Refreshing beverages and specialty drinks',
                'image' => null,
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Soups',
                'description' => 'Hot and comforting soups',
                'image' => null,
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'name' => 'Pasta',
                'description' => 'Italian pasta dishes with rich sauces',
                'image' => null,
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'name' => 'Appetizers',
                'description' => 'Starters and small plates',
                'image' => null,
                'sort_order' => 8,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
