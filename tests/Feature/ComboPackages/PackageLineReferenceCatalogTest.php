<?php

declare(strict_types=1);

namespace Tests\Feature\ComboPackages;

use App\Core\Plugins\Contracts\LineReferenceCatalog;
use App\Core\Plugins\Services\PluginContributions;
use App\Plugins\ComboPackages\src\Catalog\PackageLineReferenceCatalog;
use App\Plugins\ComboPackages\src\Models\Package;

beforeEach(function (): void {
    $this->catalog = new PackageLineReferenceCatalog();
});

it('implements LineReferenceCatalog contract and returns correct metadata', function (): void {
    expect($this->catalog)->toBeInstanceOf(LineReferenceCatalog::class)
        ->and($this->catalog->pluginId())->toBe('combo-packages')
        ->and($this->catalog->lineType())->toBe('package')
        ->and($this->catalog->label())->toBe('باقات التوفير');
});

it('searches non-deleted packages by name and name_ar with limit', function (): void {
    $pkg1 = Package::factory()->create([
        'name' => 'Family Dinner Box',
        'name_ar' => 'صندوق العائلة',
    ]);
    $pkg2 = Package::factory()->create([
        'name' => 'Solo Snack',
        'name_ar' => 'وجبة فردية',
    ]);
    $pkgDeleted = Package::factory()->create([
        'name' => 'Deleted Family Box',
        'name_ar' => 'صندوق محذوف',
    ]);
    $pkgDeleted->delete(); // soft delete

    // Search by English name
    $resultsEn = $this->catalog->search('Dinner');
    expect($resultsEn)->toHaveKey($pkg1->id)
        ->and($resultsEn)->not->toHaveKey($pkg2->id)
        ->and($resultsEn)->not->toHaveKey($pkgDeleted->id);

    // Search by Arabic name
    $resultsAr = $this->catalog->search('فردية');
    expect($resultsAr)->toHaveKey($pkg2->id)
        ->and($resultsAr)->not->toHaveKey($pkg1->id);

    // Limit check
    $all = $this->catalog->search('', 1);
    expect($all)->toHaveCount(1);
});

it('returns labelsFor existing packages and ignores missing or deleted ids', function (): void {
    $pkg1 = Package::factory()->create(['name' => 'Box 1', 'name_ar' => 'صندوق 1']);
    $pkg2 = Package::factory()->create(['name' => 'Box 2', 'name_ar' => 'صندوق 2']);
    $pkg3 = Package::factory()->create(['name' => 'Box 3', 'name_ar' => 'صندوق 3']);
    $pkg3->delete();

    $labels = $this->catalog->labelsFor([$pkg1->id, $pkg2->id, $pkg3->id, 999999]);

    expect($labels)->toHaveKey($pkg1->id)
        ->and($labels)->toHaveKey($pkg2->id)
        ->and($labels)->not->toHaveKey($pkg3->id)
        ->and($labels)->not->toHaveKey(999999);
});

it('is registered and tagged under catalog.line-references', function (): void {
    /** @var PluginContributions $contributions */
    $contributions = app(PluginContributions::class);
    $activeCatalogs = $contributions->active('catalog.line-references');

    $found = collect($activeCatalogs)->first(fn ($c) => $c instanceof PackageLineReferenceCatalog);
    expect($found)->not->toBeNull();
});
