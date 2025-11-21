<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\WeightOption;
use Illuminate\Database\Seeder;

final class WeightOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Standard weight options for different product types
        $weightOptions = [
            [
                'name' => 'Standard Weight',
                'unit' => 'kg',
                'values' => [
                    ['value' => '0.25', 'label' => 'Quarter kg', 'sort_order' => 1],
                    ['value' => '0.5', 'label' => 'Half kg', 'sort_order' => 2],
                    ['value' => '1.0', 'label' => '1 kg', 'sort_order' => 3],
                    ['value' => '1.5', 'label' => '1.5 kg', 'sort_order' => 4],
                    ['value' => '2.0', 'label' => '2 kg', 'sort_order' => 5],
                ],
            ],
            [
                'name' => 'Small Portions',
                'unit' => 'kg',
                'values' => [
                    ['value' => '0.1', 'label' => '100g', 'sort_order' => 1],
                    ['value' => '0.25', 'label' => '250g', 'sort_order' => 2],
                    ['value' => '0.5', 'label' => '500g', 'sort_order' => 3],
                    ['value' => '0.75', 'label' => '750g', 'sort_order' => 4],
                ],
            ],
            [
                'name' => 'Large Quantities',
                'unit' => 'kg',
                'values' => [
                    ['value' => '1.0', 'label' => '1 kg', 'sort_order' => 1],
                    ['value' => '2.0', 'label' => '2 kg', 'sort_order' => 2],
                    ['value' => '3.0', 'label' => '3 kg', 'sort_order' => 3],
                    ['value' => '5.0', 'label' => '5 kg', 'sort_order' => 4],
                ],
            ],
        ];

        foreach ($weightOptions as $optionData) {
            $values = $optionData['values'];
            unset($optionData['values']);

            $weightOption = WeightOption::create($optionData);

            foreach ($values as $valueData) {
                $weightOption->values()->create($valueData);
            }
        }
    }
}
