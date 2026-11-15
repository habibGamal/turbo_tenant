<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SettingKey;
use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\Section;
use App\Plugins\CatalogProducts\src\Models\WeightOption;
use App\Plugins\CmsPages\src\Models\Page;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Services\IntegrationTokenService;
use App\Plugins\OrderManagement\src\Support\OrderManagementSettings;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Fixtures for a dedicated, migrated E2E tenant only. */
final class E2eSeeder extends Seeder
{
    public function run(): void
    {
        if (! tenancy()->initialized || (! app()->environment('testing') && (app()->environment('production') || (string) tenant('id') !== 'e2e'))) {
            throw new RuntimeException('E2E fixtures require an initialized non-production tenant.');
        }

        DB::transaction(function (): void {
            foreach ([5 => 'E2E Simple Coffee', 15 => 'E2E Espresso', 16 => 'E2E Latte'] as $id => $name) {
                $existing = Product::find($id);
                if ($existing && $existing->name !== $name) {
                    throw new RuntimeException("Product ID {$id} is already in use; use a dedicated E2E tenant.");
                }
            }

            $user = User::updateOrCreate(['email' => 'admin@example.com'], [
                'name' => 'E2E Customer', 'password' => 'password', 'email_verified_at' => now(), 'is_admin' => false,
            ]);
            $branch = Branch::updateOrCreate(['name' => 'E2E Main Branch'], ['link' => 'https://example.test', 'is_active' => true]);
            $governorate = Governorate::updateOrCreate(['name' => 'E2E Cairo'], ['name_ar' => 'القاهرة', 'is_active' => true, 'sort_order' => 1]);
            $area = Area::updateOrCreate(['name' => 'E2E Downtown'], [
                'name_ar' => 'وسط البلد', 'governorate_id' => $governorate->id, 'branch_id' => $branch->id,
                'shipping_cost' => 20, 'is_active' => true, 'sort_order' => 1,
            ]);
            Address::updateOrCreate(['user_id' => $user->id, 'street' => 'E2E Test Street'], [
                'area_id' => $area->id, 'phone_number' => '01012345678', 'building' => '4',
                'floor' => '1', 'apartment' => '1', 'is_default' => true,
            ]);

            $category = Category::updateOrCreate(['name' => 'E2E Turkish Coffee'], ['is_active' => true, 'sort_order' => 1]);
            $espressoCategory = Category::updateOrCreate(['name' => 'E2E Espresso'], ['is_active' => true, 'sort_order' => 2]);
            Category::updateOrCreate(['name' => 'E2E Coffee Tools'], ['is_active' => true, 'sort_order' => 3]);
            Page::updateOrCreate(['slug' => 'about-us'], [
                'title' => 'About Us', 'content' => '<p>E2E test storefront</p>', 'is_active' => true,
            ]);
            $section = Section::updateOrCreate(['title' => 'E2E Featured'], ['location' => 'homepage', 'is_active' => true, 'sort_order' => 1]);
            $weight = WeightOption::firstOrCreate(['name' => 'E2E Coffee Weight'], ['unit' => 'kg']);
            foreach (['0.125' => '125 g', '0.250' => '250 g', '0.500' => '500 g', '1.000' => '1 kg'] as $value => $label) {
                $weight->values()->updateOrCreate(['value' => $value], ['label' => $label, 'sort_order' => (int) ((float) $value * 1000)]);
            }
            $extras = ExtraOption::updateOrCreate(['name' => 'E2E Extras'], ['is_active' => true, 'min_selections' => 0, 'allow_multiple' => true]);
            foreach (['Gensing 1 g' => 10, 'Extra milk' => 15] as $name => $price) {
                $extras->items()->updateOrCreate(['name' => $name], ['price' => $price, 'is_default' => false, 'allow_quantity' => true]);
            }
            foreach ([5 => ['E2E Simple Coffee', 600], 15 => ['E2E Espresso', 166], 16 => ['E2E Latte', 135]] as $id => [$name, $price]) {
                $product = Product::unguarded(fn () => Product::updateOrCreate(['id' => $id], [
                    'name' => $name, 'description' => 'E2E test product', 'image' => '/images/placeholder-food.svg',
                    'base_price' => $price, 'category_id' => $id === 5 ? $category->id : $espressoCategory->id, 'is_active' => true,
                    'sell_by_weight' => $id === 5, 'weight_options_id' => $id === 5 ? $weight->id : null,
                    'extra_option_id' => $id === 5 ? $extras->id : null,
                ]));
                $section->products()->syncWithoutDetaching([$id => ['sort_order' => $id === 5 ? 3 : $id - 15]]);
                if ($id === 5) {
                    foreach (['Mid' => 600, 'سادة' => 700] as $variant => $variantPrice) {
                        $product->variants()->updateOrCreate(['name' => $variant], ['price' => $variantPrice, 'is_available' => true, 'sort_order' => $variantPrice]);
                    }
                }
            }
            for ($index = 1; $index <= 55; $index++) {
                Product::updateOrCreate(['name' => sprintf('E2E Menu Coffee %02d', $index)], [
                    'description' => 'E2E pagination fixture', 'image' => '/images/placeholder-food.svg',
                    'base_price' => 200 + $index, 'category_id' => $category->id, 'is_active' => true,
                    'sell_by_weight' => false,
                ]);
            }
            $order = Order::updateOrCreate(['order_number' => 'E2E-0001'], [
                'user_id' => $user->id, 'branch_id' => $branch->id, 'status' => 'pending', 'type' => 'web_takeaway',
                'payment_method' => 'cod', 'payment_status' => 'pending', 'sub_total' => 166, 'tax' => 0,
                'service' => 0, 'delivery_fee' => 0, 'discount' => 0, 'total' => 166,
            ]);
            $order->items()->updateOrCreate(['product_id' => 15], ['product_name' => 'E2E Espresso', 'quantity' => 1, 'unit_price' => 166, 'total' => 166]);
            app(SettingService::class)->set(SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES, true);
            $integrationToken = env('E2E_INTEGRATION_TOKEN');
            if (is_string($integrationToken) && $integrationToken !== '') {
                app(SettingService::class)->set(OrderManagementSettings::INTEGRATION_TOKEN_KEY, $integrationToken);
            } elseif (app(IntegrationTokenService::class)->get() === null) {
                app(IntegrationTokenService::class)->generate();
            }
        });
    }
}
