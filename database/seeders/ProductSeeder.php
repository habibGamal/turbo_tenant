<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ExtraOption;
use App\Models\ExtraOptionItem;
use App\Models\Product;
use App\Models\ProductPosMapping;
use App\Models\ProductVariant;
use App\Models\Section;
use App\Models\WeightOption;
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
        $weightOptions = WeightOption::all();
        $branches = Branch::all();

        if ($categories->isEmpty()) {
            $this->command->warn('No categories found. Please run CategorySeeder first.');

            return;
        }

        if ($branches->isEmpty()) {
            $this->command->warn('No branches found. Please run BranchSeeder first.');

            return;
        }

        // Create 50 products distributed across categories
        foreach ($categories as $category) {
            $productCount = rand(5, 10);

            for ($i = 0; $i < $productCount; $i++) {
                // 20% chance to be sold by weight
                $sellByWeight = fake()->boolean(20);

                // Weight-based products typically have price per kg/lb
                $basePrice = $sellByWeight
                    ? fake()->randomFloat(2, 15, 80)  // Higher price per kg
                    : fake()->randomFloat(2, 8, 45);   // Regular unit price

                $hasDiscount = fake()->boolean(30);

                // Assign a weight option if selling by weight
                $weightOptionId = null;
                if ($sellByWeight && $weightOptions->isNotEmpty()) {
                    $weightOptionId = $weightOptions->random()->id;
                }

                // 40% chance to have extra options
                $extraOptionId = null;
                if (fake()->boolean(40)) {
                    $extraOption = ExtraOption::create([
                        'name' => 'Customization Options',
                        'description' => 'Choose your preferred options',
                        'min_selections' => fake()->randomElement([0, 0, 0, 1]), // Mostly optional
                        'max_selections' => fake()->randomElement([null, null, 3, 5]), // Mostly unlimited
                        'allow_multiple' => fake()->boolean(90), // Mostly allow multiple
                    ]);
                    $extraOptionId = $extraOption->id;

                    // Create 3-5 extra option items
                    $extraItemsCount = rand(3, 5);
                    for ($j = 0; $j < $extraItemsCount; $j++) {
                        $extraItem = ExtraOptionItem::create([
                            'extra_option_id' => $extraOption->id,
                            'name' => fake()->randomElement([
                                'Extra Cheese',
                                'Extra Sauce',
                                'Spicy',
                                'No Onions',
                                'Extra Toppings',
                                'Gluten Free',
                                'Large Size',
                                'Extra Meat',
                            ]),
                            'price' => fake()->randomFloat(2, 0, 5),
                            'is_default' => $j === 0, // First item is default
                            'sort_order' => $j,
                            'pos_mapping_type' => fake()->randomElement(['pos_item', 'pos_item', 'pos_item', 'notes']), // Mostly pos_item
                            'allow_quantity' => fake()->boolean(30), // 30% allow quantity
                        ]);

                        // Create POS mappings for this extra item across branches
                        foreach ($branches as $branch) {
                            ProductPosMapping::create([
                                'extra_option_item_id' => $extraItem->id,
                                'branch_id' => $branch->id,
                                'pos_item_id' => 'EXTRA_'.mb_strtoupper(str_replace(' ', '_', $extraItem->name)).'_'.$branch->id,
                            ]);
                        }
                    }
                }

                $product = Product::create([
                    'name' => $this->getProductName($category->name, $sellByWeight),
                    'description' => fake()->sentence(rand(8, 15)),
                    'image' => 'https://placehold.co/600x400/png?text='.urlencode($category->name),
                    'base_price' => $basePrice,
                    'price_after_discount' => $hasDiscount ? $basePrice * 0.85 : null,
                    'category_id' => $category->id,
                    'extra_option_id' => $extraOptionId,
                    'is_active' => fake()->boolean(95),
                    'sell_by_weight' => $sellByWeight,
                    'weight_options_id' => $weightOptionId,
                ]);

                // Create POS mappings for the product across all branches
                foreach ($branches as $branch) {
                    ProductPosMapping::create([
                        'product_id' => $product->id,
                        'branch_id' => $branch->id,
                        'pos_item_id' => 'PROD_'.$product->id.'_BR_'.$branch->id,
                    ]);
                }

                // 50% chance to have variants
                if (fake()->boolean(50)) {
                    $variantCount = rand(2, 4);
                    $sizes = ['Small', 'Medium', 'Large', 'Extra Large'];

                    for ($v = 0; $v < $variantCount; $v++) {
                        $variantPrice = $basePrice * (1 + ($v * 0.25)); // Increase price by 25% per variant

                        $variant = ProductVariant::create([
                            'product_id' => $product->id,
                            'name' => $sizes[$v] ?? "Variant {$v}",
                            'price' => $variantPrice,
                            'is_available' => fake()->boolean(90),
                            'sort_order' => $v,
                        ]);

                        // Create POS mappings for variants across all branches
                        foreach ($branches as $branch) {
                            ProductPosMapping::create([
                                'product_id' => $product->id,
                                'variant_id' => $variant->id,
                                'branch_id' => $branch->id,
                                'pos_item_id' => 'PROD_'.$product->id.'_VAR_'.$variant->id.'_BR_'.$branch->id,
                            ]);
                        }
                    }
                }

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

    private function getProductName(string $categoryName, bool $sellByWeight = false): string
    {
        $weightBasedNames = [
            'Salads' => ['Fresh Mixed Greens', 'Organic Baby Spinach', 'Arugula Mix'],
            'Soups' => ['Homemade Soup', 'Daily Special Soup'],
            'Desserts' => ['Assorted Cookies', 'Chocolate Truffles', 'Candy Mix'],
            'Pasta' => ['Fresh Pasta Dough', 'Stuffed Ravioli'],
        ];

        if ($sellByWeight && isset($weightBasedNames[$categoryName])) {
            return fake()->randomElement($weightBasedNames[$categoryName]);
        }

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
