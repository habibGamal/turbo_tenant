<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Governorate;
use Illuminate\Database\Seeder;

final class KofeAreasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branch = Branch::query()->first();

        if (! $branch) {
            $this->command->error('No branch found. Please create a branch first.');

            return;
        }

        $governorates = [
            ['name' => 'Cairo', 'name_ar' => 'القاهره', 'shipping_cost' => 95],
            ['name' => 'Giza', 'name_ar' => 'الجيزه', 'shipping_cost' => 95],
            ['name' => 'Outskirts of Cairo and Giza', 'name_ar' => 'اطراف القاهره و الجيزه', 'shipping_cost' => 115],
            ['name' => 'Sharqia', 'name_ar' => 'الشرقيه', 'shipping_cost' => 130],
            ['name' => 'Dakahlia', 'name_ar' => 'الدقهليه', 'shipping_cost' => 130],
            ['name' => 'Beheira', 'name_ar' => 'البحيره', 'shipping_cost' => 130],
            ['name' => 'Qalyubia', 'name_ar' => 'القليوبيه', 'shipping_cost' => 105],
            ['name' => 'Alexandria', 'name_ar' => 'اسكندريه', 'shipping_cost' => 105],
            ['name' => 'Gharbia', 'name_ar' => 'غربيه', 'shipping_cost' => 130],
            ['name' => 'Monufia', 'name_ar' => 'منوفيه', 'shipping_cost' => 130],
            ['name' => 'Kafr El Sheikh', 'name_ar' => 'كفر الشيخ', 'shipping_cost' => 130],
            ['name' => 'Fayoum', 'name_ar' => 'فيوم', 'shipping_cost' => 85],
            ['name' => 'Beni Suef', 'name_ar' => 'بني سويف', 'shipping_cost' => 85],
            ['name' => 'Minya', 'name_ar' => 'المنيا', 'shipping_cost' => 85],
            ['name' => 'Asyut Centers', 'name_ar' => 'مراكز اسيوط', 'shipping_cost' => 50],
            ['name' => 'Sohag', 'name_ar' => 'سوهاج', 'shipping_cost' => 85],
            ['name' => 'Qena', 'name_ar' => 'قنا', 'shipping_cost' => 95],
            ['name' => 'Luxor', 'name_ar' => 'الاقصر', 'shipping_cost' => 95],
            ['name' => 'Aswan', 'name_ar' => 'اسوان', 'shipping_cost' => 95],
            ['name' => 'Damietta', 'name_ar' => 'دمياط', 'shipping_cost' => 130],
            ['name' => 'Ismailia', 'name_ar' => 'اسماعيليه', 'shipping_cost' => 130],
            ['name' => 'Port Said', 'name_ar' => 'بور سعيد', 'shipping_cost' => 130],
            ['name' => 'Suez', 'name_ar' => 'السويس', 'shipping_cost' => 130],
            ['name' => 'Matrouh', 'name_ar' => 'مطروح', 'shipping_cost' => 200],
            ['name' => 'North Sinai', 'name_ar' => 'شمال سيناء', 'shipping_cost' => 200],
            ['name' => 'Red Sea', 'name_ar' => 'البحر الاحمر', 'shipping_cost' => 200],
            ['name' => 'New Valley', 'name_ar' => 'الوادي الجديد', 'shipping_cost' => 200],
            ['name' => 'South Sinai', 'name_ar' => 'جنوب سيناء', 'shipping_cost' => 200],
        ];

        $sortOrder = 1;

        foreach ($governorates as $governorateData) {
            $governorate = Governorate::query()->create([
                'name' => $governorateData['name'],
                'name_ar' => $governorateData['name_ar'],
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]);

            Area::query()->create([
                'name' => $governorateData['name'],
                'name_ar' => $governorateData['name_ar'],
                'shipping_cost' => $governorateData['shipping_cost'],
                'governorate_id' => $governorate->id,
                'branch_id' => $branch->id,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]);

            $sortOrder++;
        }

        $this->command->info('Kofe governorates and areas seeded successfully!');
    }
}
