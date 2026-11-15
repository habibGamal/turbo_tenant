<?php

declare(strict_types=1);

namespace Tests\Feature\CouponsPromotions;

use App\Core\Plugins\Contracts\LineReferenceCatalog;
use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginManager;
use App\Models\TenantPlugin;
use App\Models\User;
use App\Plugins\CouponsPromotions\src\Enums\ApplicableTo;
use App\Plugins\CouponsPromotions\src\Enums\DiscountType;
use App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\Schemas\CouponForm;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use Livewire\Livewire;
use Tests\TestCase;

final class CouponAdminFormLineTypesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create([
            'is_admin' => true,
        ]));

        // Coupons must work without the combo plugin; only this test's fake catalog should provide references.
        TenantPlugin::query()
            ->where('tenant_id', tenant()->id)
            ->where('plugin_id', 'combo-packages')
            ->update(['status' => PluginStatus::DEACTIVATED]);
        app(PluginManager::class)->resetTenant(tenant());
    }

    public function test_line_types_option_is_hidden_when_no_provider_is_active(): void
    {
        $providers = CouponForm::getActiveLineReferenceProviders();
        $this->assertEmpty($providers);

        $livewire = Livewire::test(CreateCoupon::class);
        $livewire->assertOk();

        // The applicable_to type select options should not contain line_types
        $typeField = $livewire->instance()->form->getComponent('conditions.applicable_to.type');
        $options = $typeField->getOptions();

        $this->assertArrayNotHasKey(ApplicableTo::LINE_TYPES->value, $options);
        $this->assertArrayHasKey('all', $options);
        $this->assertArrayHasKey('products', $options);
        $this->assertArrayHasKey('categories', $options);
    }

    public function test_line_types_option_is_visible_when_provider_is_active(): void
    {
        $this->registerFakeCatalog();

        $providers = CouponForm::getActiveLineReferenceProviders();
        $this->assertNotEmpty($providers);
        $this->assertSame('باقات التوفير', $providers[0]->label());

        $livewire = Livewire::test(CreateCoupon::class);
        $livewire->assertOk();

        $typeField = $livewire->instance()->form->getComponent('conditions.applicable_to.type');
        $options = $typeField->getOptions();

        $this->assertArrayHasKey(ApplicableTo::LINE_TYPES->value, $options);
        $this->assertSame('باقات التوفير', $options[ApplicableTo::LINE_TYPES->value]);
    }

    public function test_admin_form_saves_line_types_conditions_shape_with_ids(): void
    {
        $this->registerFakeCatalog();

        Livewire::test(CreateCoupon::class)
            ->fillForm([
                'code' => 'COMBO20',
                'name' => 'Combo 20% Off',
                'type' => 'percentage',
                'value' => 20,
                'expiry_date' => now()->addDays(7),
                'conditions.applicable_to.type' => 'line_types',
                'conditions.applicable_to.ids' => [10, 20],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $coupon = Coupon::where('code', 'COMBO20')->first();
        $this->assertNotNull($coupon);
        $this->assertSame('line_types', $coupon->conditions['applicable_to']['type']);
        $this->assertSame('package', $coupon->conditions['applicable_to']['line_type']);
        $this->assertSame([10, 20], $coupon->conditions['applicable_to']['ids']);
    }

    public function test_admin_form_saves_line_types_with_empty_ids_for_all_combos(): void
    {
        $this->registerFakeCatalog();

        Livewire::test(CreateCoupon::class)
            ->fillForm([
                'code' => 'ALLCOMBOS',
                'name' => 'All Combos Off',
                'type' => 'fixed',
                'value' => 15,
                'expiry_date' => now()->addDays(7),
                'conditions.applicable_to.type' => 'line_types',
                'conditions.applicable_to.ids' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $coupon = Coupon::where('code', 'ALLCOMBOS')->first();
        $this->assertNotNull($coupon);
        $this->assertSame('line_types', $coupon->conditions['applicable_to']['type']);
        $this->assertSame('package', $coupon->conditions['applicable_to']['line_type']);
        $this->assertSame([], $coupon->conditions['applicable_to']['ids']);
    }

    public function test_table_renders_applicable_to_column_with_new_type(): void
    {
        $this->registerFakeCatalog();

        $coupon = Coupon::factory()->create([
            'code' => 'COMBO_TABLE_TEST',
            'is_active' => true,
            'type' => DiscountType::PERCENTAGE,
            'value' => 10,
            'conditions' => [
                'applicable_to' => [
                    'type' => 'line_types',
                    'line_type' => 'package',
                    'ids' => [10],
                ],
            ],
        ]);

        $this->assertSame('باقات التوفير', $coupon->applicable_to_label);

        Livewire::test(ListCoupons::class)
            ->assertOk()
            ->assertTableColumnExists('applicable_to')
            ->assertCanSeeTableRecords([$coupon])
            ->assertSee('باقات التوفير');
    }

    public function test_storefront_api_exposes_line_types_applicable_to(): void
    {
        $cartService = $this->createMock(\App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface::class);
        $cartService->method('getLines')->willReturn([
            new \App\Plugins\CartEngine\src\DTOs\CartLine(
                itemId: 1,
                productId: null,
                categoryId: null,
                variantId: null,
                weightOptionValueId: null,
                weightMultiplier: 1,
                quantity: 1.0,
                unitPrice: 50.0,
                extrasUnitTotal: 0.0,
                subtotal: 50.0,
                productName: 'Combo',
                productNameAr: null,
                variantName: null,
                variantNameAr: null,
                weightLabel: null,
                type: 'package',
                referenceId: 10,
                couponEligible: true,
            ),
        ]);
        $this->app->instance(\App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface::class, $cartService);

        $coupon = Coupon::factory()->create([
            'code' => 'API_COMBO_TEST',
            'is_active' => true,
            'type' => DiscountType::PERCENTAGE,
            'value' => 15,
            'conditions' => [
                'applicable_to' => [
                    'type' => 'line_types',
                    'line_type' => 'package',
                    'ids' => [10, 20],
                ],
            ],
        ]);

        $response = $this->postJson('/api/coupons/validate', [
            'code' => 'API_COMBO_TEST',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.coupon.code', 'API_COMBO_TEST')
            ->assertJsonPath('data.coupon.applicable_to.type', 'line_types')
            ->assertJsonPath('data.coupon.applicable_to.line_type', 'package')
            ->assertJsonPath('data.coupon.applicable_to.ids', [10, 20]);
    }

    private function registerFakeCatalog(): LineReferenceCatalog
    {
        $fakeCatalog = new class implements LineReferenceCatalog
        {
            public function pluginId(): string
            {
                return 'coupons-promotions';
            }

            public function lineType(): string
            {
                return 'package';
            }

            public function label(): string
            {
                return 'باقات التوفير';
            }

            public function search(string $term, int $limit = 50): array
            {
                return [
                    10 => 'Combo Meal A',
                    20 => 'Combo Meal B',
                ];
            }

            public function labelsFor(array $ids): array
            {
                $all = [
                    10 => 'Combo Meal A',
                    20 => 'Combo Meal B',
                ];

                return array_intersect_key($all, array_flip($ids));
            }
        };

        app()->instance('fake.line-reference.catalog', $fakeCatalog);
        app()->tag(['fake.line-reference.catalog'], 'catalog.line-references');

        return $fakeCatalog;
    }
}
