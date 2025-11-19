<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Package;
use App\Models\Product;
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

        $packages = [
            [
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
                'product_count' => 6,
            ],
            [
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
                'product_count' => 3,
            ],
            [
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
                'product_count' => 4,
            ],
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
            [
                'name' => 'Party Pack',
                'name_ar' => 'باقة الحفلات',
                'description' => 'Large package perfect for parties and celebrations',
                'description_ar' => 'باقة كبيرة مثالية للحفلات والاحتفالات',
                'price' => 79.99,
                'original_price' => 109.99,
                'discount_percentage' => 27,
                'badge' => 'For Groups',
                'badge_ar' => 'للمجموعات',
                'icon' => 'gift',
                'gradient' => 'from-yellow-500/10 via-orange-500/5 to-red-500/10',
                'is_featured' => true,
                'product_count' => 10,
            ],
        ];

        foreach ($packages as $packageData) {
            $productCount = $packageData['product_count'];
            unset($packageData['product_count']);

            $package = Package::create($packageData);

            // Attach random products to the package
            $randomProducts = $products->random(min($productCount, $products->count()));
            foreach ($randomProducts as $product) {
                $package->products()->attach($product->id, [
                    'quantity' => rand(1, 3),
                ]);
            }
        }
    }
}
