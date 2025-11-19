<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Section;
use Illuminate\Database\Seeder;

final class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::all();
        $sections = Section::all();

        if ($categories->isEmpty()) {
            $this->command->warn('No categories found. Please run CategorySeeder first.');

            return;
        }

        // Create 50 products distributed across categories
        foreach ($categories as $category) {
            $productCount = rand(5, 10);

            for ($i = 0; $i < $productCount; $i++) {
                $basePrice = fake()->randomFloat(2, 8, 45);
                $hasDiscount = fake()->boolean(30);

                $product = Product::create([
                    'name' => $this->getProductName($category->name),
                    'description' => fake()->sentence(rand(8, 15)),
                    'image' => 'https://placehold.co/600x400/png?text='.urlencode($category->name),
                    'base_price' => $basePrice,
                    'price_after_discount' => $hasDiscount ? $basePrice * 0.85 : null,
                    'category_id' => $category->id,
                    'is_active' => fake()->boolean(95),
                    'sell_by_weight' => false,
                ]);

                // Attach to random sections
                if ($sections->isNotEmpty() && fake()->boolean(60)) {
                    $randomSections = $sections->random(rand(1, min(2, $sections->count())));
                    foreach ($randomSections as $section) {
                        $product->sections()->attach($section->id, [
                            'sort_order' => rand(1, 100),
                        ]);
                    }
                }
            }
        }
    }

    private function getProductName(string $categoryName): string
    {
        $names = [
            'Pizza' => [
                'Margherita Pizza', 'Pepperoni Pizza', 'BBQ Chicken Pizza',
                'Veggie Supreme Pizza', 'Meat Lovers Pizza', 'Hawaiian Pizza',
                'Four Cheese Pizza', 'Mediterranean Pizza',
            ],
            'Burgers' => [
                'Classic Beef Burger', 'Cheese Burger', 'Bacon Burger',
                'Mushroom Swiss Burger', 'Chicken Burger', 'Veggie Burger',
                'Double Patty Burger', 'Spicy Jalapeño Burger',
            ],
            'Salads' => [
                'Caesar Salad', 'Greek Salad', 'Garden Salad',
                'Cobb Salad', 'Caprese Salad', 'Quinoa Salad',
                'Asian Chicken Salad', 'Spinach Salad',
            ],
            'Desserts' => [
                'Chocolate Lava Cake', 'Tiramisu', 'Cheesecake',
                'Apple Pie', 'Ice Cream Sundae', 'Brownie',
                'Panna Cotta', 'Fruit Tart',
            ],
            'Drinks' => [
                'Fresh Orange Juice', 'Lemonade', 'Iced Tea',
                'Smoothie', 'Milkshake', 'Coffee',
                'Cappuccino', 'Soft Drink',
            ],
            'Soups' => [
                'Tomato Soup', 'Chicken Noodle Soup', 'Mushroom Soup',
                'Minestrone', 'French Onion Soup', 'Lentil Soup',
            ],
            'Pasta' => [
                'Spaghetti Carbonara', 'Fettuccine Alfredo', 'Penne Arrabbiata',
                'Lasagna', 'Ravioli', 'Pesto Pasta',
            ],
            'Appetizers' => [
                'Garlic Bread', 'Mozzarella Sticks', 'Chicken Wings',
                'Nachos', 'Spring Rolls', 'Bruschetta',
            ],
        ];

        $categoryNames = $names[$categoryName] ?? ['Special Dish'];

        return fake()->randomElement($categoryNames);
    }
}
