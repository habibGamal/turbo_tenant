<?php

declare(strict_types=1);

namespace Tests\Feature\ComboPackages;

use App\Models\User;
use App\Plugins\CatalogProducts\src\Filament\Resources\Products\Pages\EditProduct;
use App\Plugins\CatalogProducts\src\Filament\Resources\Products\Pages\ListProducts;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\ComboPackages\src\Guards\PackageProductDeletionGuard;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageGroup;
use App\Plugins\ComboPackages\src\Models\PackageItem;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->category = Category::factory()->create();
    $this->product = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Original Burger',
        'name_ar' => 'برجر أصلي',
        'is_active' => true,
    ]);

    $this->package = Package::factory()->create([
        'name' => 'Burger Combo',
        'name_ar' => 'باقة البرجر',
        'is_active' => true,
    ]);

    $this->group = PackageGroup::create([
        'package_id' => $this->package->id,
        'name' => 'Main',
        'name_ar' => 'الرئيسي',
        'selection_type' => 'all',
    ]);

    $this->variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'name' => 'Double',
        'name_ar' => 'مزدوج',
        'price' => 25.00,
        'is_available' => true,
    ]);

    $this->packageItem = PackageItem::create([
        'package_group_id' => $this->group->id,
        'product_id' => $this->product->id,
        'variant_id' => $this->variant->id,
        'quantity' => 1,
        'price_adjustment' => 5.00,
        'is_default' => true,
    ]);
});

it('blocks raw database deletion of a product used in a package via FK restrict constraint', function (): void {
    expect(fn () => $this->product->delete())
        ->toThrow(QueryException::class);

    expect(Product::find($this->product->id))->not->toBeNull();
});

it('allows deleting an unused product', function (): void {
    $unusedProduct = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Unused Product',
        'is_active' => true,
    ]);

    expect($unusedProduct->delete())->toBeTrue();
    expect(Product::find($unusedProduct->id))->toBeNull();
});

it('provides a localized Arabic blocking reason via PackageProductDeletionGuard', function (): void {
    $guard = app(PackageProductDeletionGuard::class);

    $reason = $guard->blockingReason($this->product);
    expect($reason)->not->toBeNull()
        ->and($reason)->toContain('لا يمكن حذف المنتج')
        ->and($reason)->toContain('باقة البرجر');

    $unusedProduct = Product::factory()->create();
    expect($guard->blockingReason($unusedProduct))->toBeNull();
});

it('blocks single delete action in Filament EditProduct page with danger notification', function (): void {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    livewire(EditProduct::class, ['record' => $this->product->id])
        ->callAction('delete')
        ->assertNotified();

    // Verify record was not deleted
    expect(Product::find($this->product->id))->not->toBeNull();
});

it('skips blocked records and deletes unblocked records in ProductsTable bulk delete', function (): void {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    $unblockedProduct = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Free To Delete',
        'name_ar' => 'جاهز للحذف',
    ]);

    livewire(ListProducts::class)
        ->callTableBulkAction('delete', [$this->product->id, $unblockedProduct->id])
        ->assertNotified();

    // Blocked product still exists
    expect(Product::find($this->product->id))->not->toBeNull();
    // Unblocked product is deleted
    expect(Product::find($unblockedProduct->id))->toBeNull();
});

it('sets package_items.variant_id to null when variant is deleted via nullOnDelete constraint', function (): void {
    expect($this->packageItem->variant_id)->toBe($this->variant->id);

    $this->variant->delete();

    $this->packageItem->refresh();
    expect($this->packageItem->variant_id)->toBeNull()
        ->and(PackageItem::find($this->packageItem->id))->not->toBeNull();
});

it('resolves Product::packageItems relation and confirms Package::products is removed', function (): void {
    $relation = $this->product->packageItems();
    expect($relation)->toBeInstanceOf(HasMany::class);

    $items = $this->product->packageItems;
    expect($items)->toHaveCount(1)
        ->and($items->first()->id)->toBe($this->packageItem->id);

    expect(method_exists(Package::class, 'products'))->toBeFalse();
});
