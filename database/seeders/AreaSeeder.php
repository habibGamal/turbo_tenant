<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Governorate;
use Illuminate\Database\Seeder;

final class AreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cairo = Governorate::where('name', 'Cairo')->first();
        $alexandria = Governorate::where('name', 'Alexandria')->first();
        $giza = Governorate::where('name', 'Giza')->first();

        $mainBranch = Branch::where('name', 'Main Branch')->first();
        $downtownBranch = Branch::where('name', 'Downtown Branch')->first();
        $airportBranch = Branch::where('name', 'Airport Branch')->first();

        $areas = [
            // Cairo areas
            [
                'name' => 'Downtown Cairo',
                'name_ar' => 'وسط القاهرة',
                'shipping_cost' => 15.00,
                'governorate_id' => $cairo->id,
                'branch_id' => $mainBranch->id,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Heliopolis',
                'name_ar' => 'مصر الجديدة',
                'shipping_cost' => 20.00,
                'governorate_id' => $cairo->id,
                'branch_id' => $mainBranch->id,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Nasr City',
                'name_ar' => 'مدينة نصر',
                'shipping_cost' => 18.00,
                'governorate_id' => $cairo->id,
                'branch_id' => $downtownBranch->id,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Maadi',
                'name_ar' => 'المعادي',
                'shipping_cost' => 25.00,
                'governorate_id' => $cairo->id,
                'branch_id' => $mainBranch->id,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Zamalek',
                'name_ar' => 'الزمالك',
                'shipping_cost' => 22.00,
                'governorate_id' => $cairo->id,
                'branch_id' => $downtownBranch->id,
                'is_active' => true,
                'sort_order' => 5,
            ],
            // Alexandria areas
            [
                'name' => 'Downtown Alexandria',
                'name_ar' => 'وسط الإسكندرية',
                'shipping_cost' => 12.00,
                'governorate_id' => $alexandria->id,
                'branch_id' => $airportBranch->id,
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Sidi Gaber',
                'name_ar' => 'سيدي جابر',
                'shipping_cost' => 14.00,
                'governorate_id' => $alexandria->id,
                'branch_id' => $airportBranch->id,
                'is_active' => true,
                'sort_order' => 7,
            ],
            // Giza areas
            [
                'name' => 'Dokki',
                'name_ar' => 'الدقي',
                'shipping_cost' => 16.00,
                'governorate_id' => $giza->id,
                'branch_id' => $mainBranch->id,
                'is_active' => true,
                'sort_order' => 8,
            ],
            [
                'name' => 'Mohandessin',
                'name_ar' => 'المهندسين',
                'shipping_cost' => 18.00,
                'governorate_id' => $giza->id,
                'branch_id' => $mainBranch->id,
                'is_active' => true,
                'sort_order' => 9,
            ],
        ];

        foreach ($areas as $area) {
            Area::create($area);
        }
    }
}
