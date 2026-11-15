<?php

declare(strict_types=1);

use App\Core\Plugins\Contracts\FormComponentContributor;
use App\Core\Plugins\Services\PluginContributions;
use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\PosIntegration\src\Filament\Contributors\PosPackageMappingFormContributor;
use App\Plugins\PosIntegration\src\Models\PosLineMapping;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->user);
});

function createContributorRepeater(Product $record): Repeater
{
    $livewire = new class extends Component implements HasSchemas
    {
        use InteractsWithSchemas;

        public ?array $pos_line_mappings = [];
    };

    $schema = Schema::make($livewire);
    $schema->record($record);

    $contributor = new PosPackageMappingFormContributor();
    $components = $contributor->components();
    $section = $components[0];
    $section->container($schema);

    /** @var Repeater $repeater */
    $repeater = $section->getChildComponents()[0];

    return $repeater;
}

describe('PosPackageMappingFormContributor', function () {
    it('implements FormComponentContributor and is tagged in service provider', function () {
        $contributor = app(PosPackageMappingFormContributor::class);
        expect($contributor)->toBeInstanceOf(FormComponentContributor::class)
            ->and($contributor->pluginId())->toBe('pos-integration');

        $active = app(PluginContributions::class)->active('admin.form.combo-packages.package');
        $classes = array_map(fn ($c) => get_class($c), $active);
        expect($classes)->toContain(PosPackageMappingFormContributor::class);
    });

    it('renders Section and Repeater structure with dehydrated false', function () {
        $product = Product::factory()->create();
        $repeater = createContributorRepeater($product);

        expect($repeater)->toBeInstanceOf(Repeater::class)
            ->and($repeater->getName())->toBe('pos_line_mappings')
            ->and($repeater->isDehydrated())->toBeFalse();
    });

    it('hydrates existing rows for a record', function () {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        PosLineMapping::create([
            'line_type' => 'package',
            'line_reference_id' => $product->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'POS-HYDRATE-1',
        ]);
        PosLineMapping::create([
            'line_type' => 'package',
            'line_reference_id' => $product->id,
            'branch_id' => null,
            'pos_item_id' => 'POS-HYDRATE-ALL',
        ]);

        $repeater = createContributorRepeater($product);
        $repeater->callAfterStateHydrated();

        $state = $repeater->getState();
        expect($state)->toBeArray()
            ->and($state)->toHaveCount(2)
            ->and($state[0])->toBe([
                'branch_id' => $branch->id,
                'pos_item_id' => 'POS-HYDRATE-1',
            ])
            ->and($state[1])->toBe([
                'branch_id' => null,
                'pos_item_id' => 'POS-HYDRATE-ALL',
            ]);
    });

    it('saves and replaces rows keyed by the record key', function () {
        $product = Product::factory()->create();
        $branch1 = Branch::factory()->create();
        $branch2 = Branch::factory()->create();

        // Old existing row that should be replaced
        PosLineMapping::create([
            'line_type' => 'package',
            'line_reference_id' => $product->id,
            'branch_id' => $branch1->id,
            'pos_item_id' => 'POS-OLD',
        ]);

        $repeater = createContributorRepeater($product);
        $newState = [
            ['branch_id' => $branch2->id, 'pos_item_id' => 'POS-NEW-2'],
            ['branch_id' => null, 'pos_item_id' => 'POS-NEW-GLOBAL'],
        ];

        $repeater->state($newState);
        $repeater->saveRelationships();

        $mappings = PosLineMapping::where('line_type', 'package')
            ->where('line_reference_id', $product->id)
            ->get();

        expect($mappings)->toHaveCount(2)
            ->and($mappings->pluck('pos_item_id')->toArray())->toEqualCanonicalizing(['POS-NEW-2', 'POS-NEW-GLOBAL']);
    });

    it('rejects duplicate branches', function () {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $repeater = createContributorRepeater($product);
        $duplicateState = [
            ['branch_id' => $branch->id, 'pos_item_id' => 'POS-1'],
            ['branch_id' => $branch->id, 'pos_item_id' => 'POS-2'],
        ];

        $repeater->state($duplicateState);

        expect(fn () => $repeater->saveRelationships())->toThrow(ValidationException::class);
    });

    it('rejects multiple null (all branches) entries as duplicate branches', function () {
        $product = Product::factory()->create();

        $repeater = createContributorRepeater($product);
        $duplicateState = [
            ['branch_id' => null, 'pos_item_id' => 'POS-ALL-1'],
            ['branch_id' => null, 'pos_item_id' => 'POS-ALL-2'],
        ];

        $repeater->state($duplicateState);

        expect(fn () => $repeater->saveRelationships())->toThrow(ValidationException::class);
    });
});
