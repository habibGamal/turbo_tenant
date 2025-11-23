<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Package;
use App\Models\PackageGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

final class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::where('is_active', true)->get();

        if ($products->isEmpty()) {
            $this->command->warn('No products found. Please run ProductSeeder first.');

            return;
        }

        // Example 1: Family Feast Package - Demonstrates conditional product selection
        $familyPackage = Package::create([
            'name' => 'Family Feast Package',
            'name_ar' => 'باقة العائلة',
            'description' => 'Perfect for family gatherings with a variety of dishes for everyone',
            'description_ar' => 'مثالية للتجمعات العائلية مع مجموعة متنوعة من الأطباق للجميع',
            'price' => 49.99,
            'original_price' => 69.99,
            'discount_percentage' => 29,
            'badge' => 'Best Value',
            'badge_ar' => 'أفضل قيمة',
            'icon' => 'gift',
            'gradient' => 'from-orange-500/10 via-red-500/5 to-pink-500/10',
            'is_featured' => true,
        ]);

        // Fixed items group - everyone gets these
        $fixedGroup = PackageGroup::create([
            'package_id' => $familyPackage->id,
            'name' => 'Included Items',
            'name_ar' => 'العناصر المضمنة',
            'selection_type' => 'all',
            'sort_order' => 1,
        ]);

        // Add 2 fixed products
        foreach ($products->random(2) as $index => $product) {
            $fixedGroup->items()->create([
                'product_id' => $product->id,
                'quantity' => 2,
                'sort_order' => $index,
            ]);
        }

        // Conditional group - choose one main dish
        $mainDishGroup = PackageGroup::create([
            'package_id' => $familyPackage->id,
            'name' => 'Choose Your Main Dish',
            'name_ar' => 'اختر طبقك الرئيسي',
            'selection_type' => 'choose_one',
            'min_selections' => 1,
            'max_selections' => 1,
            'sort_order' => 2,
        ]);

        // Add 3 options for main dish
        foreach ($products->random(3) as $index => $product) {
            $mainDishGroup->items()->create([
                'product_id' => $product->id,
                'quantity' => 1,
                'is_default' => $index === 0,
                'sort_order' => $index,
            ]);
        }

        // Example 2: Lunch Special - Demonstrates variant selection
        $lunchPackage = Package::create([
            'name' => 'Lunch Special',
            'name_ar' => 'عرض الغداء',
            'description' => 'Quick and delicious lunch combo for busy days',
            'description_ar' => 'كومبو غداء سريع ولذيذ للأيام المزدحمة',
            'price' => 15.99,
            'original_price' => 22.99,
            'discount_percentage' => 30,
            'badge' => 'Popular',
            'badge_ar' => 'شائع',
            'icon' => 'clock',
            'gradient' => 'from-blue-500/10 via-cyan-500/5 to-teal-500/10',
            'is_featured' => false,
        ]);

        // Find a product with variants
        $productWithVariants = $products->first(fn ($p) => $p->variants()->count() > 0);

        if ($productWithVariants) {
            $variantGroup = PackageGroup::create([
                'package_id' => $lunchPackage->id,
                'name' => 'Choose Your Size',
                'name_ar' => 'اختر الحجم',
                'selection_type' => 'choose_one',
                'min_selections' => 1,
                'max_selections' => 1,
                'sort_order' => 1,
            ]);

            // Add specific variants as options
            $variants = ProductVariant::where('product_id', $productWithVariants->id)
                ->where('is_available', true)
                ->get();

            foreach ($variants as $index => $variant) {
                $variantGroup->items()->create([
                    'product_id' => $productWithVariants->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'price_adjustment' => 0,
                    'is_default' => $index === 0,
                    'sort_order' => $index,
                ]);
            }
        }

        // Example 3: Weekend Brunch - Demonstrates choose multiple
        $brunchPackage = Package::create([
            'name' => 'Weekend Brunch',
            'name_ar' => 'برانش نهاية الأسبوع',
            'description' => 'Start your weekend right with this delightful brunch package',
            'description_ar' => 'ابدأ عطلة نهاية الأسبوع بشكل صحيح مع هذه الباقة الرائعة',
            'price' => 29.99,
            'original_price' => 39.99,
            'discount_percentage' => 25,
            'badge' => 'Weekend Special',
            'badge_ar' => 'عرض نهاية الأسبوع',
            'icon' => 'star',
            'gradient' => 'from-purple-500/10 via-violet-500/5 to-fuchsia-500/10',
            'is_featured' => false,
        ]);

        // Choose 2 sides from 4 options
        $sidesGroup = PackageGroup::create([
            'package_id' => $brunchPackage->id,
            'name' => 'Choose 2 Sides',
            'name_ar' => 'اختر جانبين',
            'selection_type' => 'choose_multiple',
            'min_selections' => 2,
            'max_selections' => 2,
            'sort_order' => 1,
        ]);

        foreach ($products->random(4) as $index => $product) {
            $sidesGroup->items()->create([
                'product_id' => $product->id,
                'quantity' => 1,
                'price_adjustment' => $index > 1 ? 2.00 : 0.00, // Premium options cost extra
                'is_default' => $index < 2,
                'sort_order' => $index,
            ]);
        }

        // Create remaining packages with simple structure
        $simplePackages = [
            [
                'name' => 'Couple Date Night',
                'name_ar' => 'أمسية الزوجين',
                'description' => 'Romantic dinner package for two with dessert included',
                'description_ar' => 'باقة عشاء رومانسية لشخصين مع الحلوى',
                'price' => 39.99,
                'original_price' => 54.99,
                'discount_percentage' => 27,
                'badge' => 'Romantic',
                'badge_ar' => 'رومانسي',
                'icon' => 'gift',
                'gradient' => 'from-pink-500/10 via-rose-500/5 to-red-500/10',
                'is_featured' => true,
                'product_count' => 5,
            ],
            [
                'name' => 'Office Lunch Box',
                'name_ar' => 'علبة غداء المكتب',
                'description' => 'Convenient meal box for office workers on the go',
                'description_ar' => 'علبة وجبة مريحة لموظفي المكاتب أثناء التنقل',
                'price' => 12.99,
                'original_price' => 17.99,
                'discount_percentage' => 28,
                'badge' => 'Quick & Easy',
                'badge_ar' => 'سريع وسهل',
                'icon' => 'clock',
                'gradient' => 'from-green-500/10 via-emerald-500/5 to-teal-500/10',
                'is_featured' => false,
                'product_count' => 3,
            ],
        ];

        foreach ($simplePackages as $packageData) {
            $productCount = $packageData['product_count'];
            unset($packageData['product_count']);

            $package = Package::create($packageData);

            $group = PackageGroup::create([
                'package_id' => $package->id,
                'name' => 'Package Items',
                'name_ar' => 'عناصر الباقة',
                'selection_type' => 'all',
                'sort_order' => 1,
            ]);

            foreach ($products->random(min($productCount, $products->count())) as $index => $product) {
                $group->items()->create([
                    'product_id' => $product->id,
                    'quantity' => rand(1, 2),
                    'sort_order' => $index,
                ]);
            }
        }
    }
}
