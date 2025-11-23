<?php

declare(strict_types=1);

use App\Filament\Resources\WeightOptions\Pages\CreateWeightOption;
use App\Filament\Resources\WeightOptions\Pages\EditWeightOption;
use App\Filament\Resources\WeightOptions\Pages\ListWeightOptions;
use App\Models\User;
use App\Models\WeightOption;
use App\Models\WeightOptionValue;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can render the index page', function () {
    livewire(ListWeightOptions::class)
        ->assertOk();
});

it('can render the create page', function () {
    livewire(CreateWeightOption::class)
        ->assertOk();
});

it('can render the edit page', function () {
    $weightOption = WeightOption::factory()
        ->has(WeightOptionValue::factory()->count(3))
        ->create();

    livewire(EditWeightOption::class, [
        'record' => $weightOption->id,
    ])
        ->assertOk()
        ->assertSchemaStateSet([
            'name' => $weightOption->name,
            'unit' => $weightOption->unit,
        ]);
});

it('has column', function (string $column) {
    livewire(ListWeightOptions::class)
        ->assertTableColumnExists($column);
})->with(['name', 'unit', 'values_count', 'products_count', 'created_at', 'updated_at']);

it('can render column', function (string $column) {
    WeightOption::factory()
        ->has(WeightOptionValue::factory()->count(2))
        ->create();

    livewire(ListWeightOptions::class)
        ->assertCanRenderTableColumn($column);
})->with(['name', 'unit', 'values_count', 'products_count', 'created_at', 'updated_at']);

it('can sort column', function (string $column) {
    $records = WeightOption::factory(5)->create();

    livewire(ListWeightOptions::class)
        ->loadTable()
        ->sortTable($column)
        ->assertCanSeeTableRecords($records->sortBy($column), inOrder: true)
        ->sortTable($column, 'desc')
        ->assertCanSeeTableRecords($records->sortByDesc($column), inOrder: true);
})->with(['name']);

it('can search column', function (string $column) {
    $records = WeightOption::factory(5)->create();

    $value = $records->first()->{$column};

    livewire(ListWeightOptions::class)
        ->loadTable()
        ->searchTable($value)
        ->assertCanSeeTableRecords($records->where($column, $value))
        ->assertCanNotSeeTableRecords($records->where($column, '!=', $value));
})->with(['name']);

it('can create a weight option', function () {
    $weightOption = WeightOption::factory()->make();

    livewire(CreateWeightOption::class)
        ->fillForm([
            'name' => $weightOption->name,
            'unit' => $weightOption->unit,
            'values' => [
                [
                    'value' => '0.25',
                    'label' => 'Quarter kg',
                    'sort_order' => 1,
                ],
                [
                    'value' => '0.5',
                    'label' => 'Half kg',
                    'sort_order' => 2,
                ],
            ],
        ])
        ->call('create')
        ->assertNotified();

    assertDatabaseHas(WeightOption::class, [
        'name' => $weightOption->name,
        'unit' => $weightOption->unit,
    ]);

    $createdWeightOption = WeightOption::where('name', $weightOption->name)->first();
    expect($createdWeightOption->values)->toHaveCount(2);
    assertDatabaseHas(WeightOptionValue::class, [
        'weight_option_id' => $createdWeightOption->id,
        'value' => '0.250',
        'label' => 'Quarter kg',
        'sort_order' => 1,
    ]);
});

it('can update a weight option', function () {
    $weightOption = WeightOption::factory()
        ->has(WeightOptionValue::factory()->count(2))
        ->create();

    $newWeightOptionData = WeightOption::factory()->make();

    livewire(EditWeightOption::class, [
        'record' => $weightOption->id,
    ])
        ->fillForm([
            'name' => $newWeightOptionData->name,
            'unit' => $newWeightOptionData->unit,
        ])
        ->call('save')
        ->assertNotified();

    assertDatabaseHas(WeightOption::class, [
        'id' => $weightOption->id,
        'name' => $newWeightOptionData->name,
        'unit' => $newWeightOptionData->unit,
    ]);
});

it('can update weight option values', function () {
    $weightOption = WeightOption::factory()
        ->has(WeightOptionValue::factory()->count(1))
        ->create();

    $existingValue = $weightOption->values->first();

    livewire(EditWeightOption::class, [
        'record' => $weightOption->id,
    ])
        ->fillForm([
            'name' => $weightOption->name,
            'unit' => $weightOption->unit,
            'values' => [
                [
                    'value' => '1.0',
                    'label' => 'Updated label',
                    'sort_order' => 1,
                ],
                [
                    'value' => '2.0',
                    'label' => 'New value',
                    'sort_order' => 2,
                ],
            ],
        ])
        ->call('save')
        ->assertNotified();

    $weightOption->refresh();
    expect($weightOption->values)->toHaveCount(2);
});

it('can delete a weight option', function () {
    $weightOption = WeightOption::factory()
        ->has(WeightOptionValue::factory()->count(2))
        ->create();

    livewire(EditWeightOption::class, [
        'record' => $weightOption->id,
    ])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseMissing($weightOption);
    // Verify cascade delete of values
    expect(WeightOptionValue::where('weight_option_id', $weightOption->id)->count())->toBe(0);
});

it('can bulk delete weight options', function () {
    $weightOptions = WeightOption::factory()
        ->has(WeightOptionValue::factory()->count(2))
        ->count(5)
        ->create();

    livewire(ListWeightOptions::class)
        ->loadTable()
        ->assertCanSeeTableRecords($weightOptions)
        ->selectTableRecords($weightOptions)
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertNotified()
        ->assertCanNotSeeTableRecords($weightOptions);

    $weightOptions->each(fn (WeightOption $weightOption) => assertDatabaseMissing($weightOption));
});

it('validates the form data', function (array $data, array $errors) {
    $weightOption = WeightOption::factory()
        ->has(WeightOptionValue::factory()->count(1))
        ->create();

    $newWeightOptionData = WeightOption::factory()->make();

    livewire(EditWeightOption::class, [
        'record' => $weightOption->id,
    ])
        ->fillForm([
            'name' => $newWeightOptionData->name,
            'unit' => $newWeightOptionData->unit,
            ...$data,
        ])
        ->call('save')
        ->assertHasFormErrors($errors)
        ->assertNotNotified();
})->with([
    '`name` is required' => [['name' => null], ['name' => 'required']],
    '`name` is max 255 characters' => [['name' => Str::random(256)], ['name' => 'max']],
    '`unit` is required' => [['unit' => null], ['unit' => 'required']],
]);

it('validates weight option value data', function (array $valueData, array $errors) {
    livewire(CreateWeightOption::class)
        ->fillForm([
            'name' => 'Test Weight Option',
            'unit' => 'kg',
            'values' => [$valueData],
        ])
        ->call('create')
        ->assertHasFormErrors($errors)
        ->assertNotNotified();
})->with([
    '`value` is required' => [
        ['value' => null, 'label' => 'Test', 'sort_order' => 1],
        ['values.0.value' => 'required'],
    ],
    '`value` must be numeric' => [
        ['value' => 'not-a-number', 'label' => 'Test', 'sort_order' => 1],
        ['values.0.value' => 'numeric'],
    ],
    '`value` must be at least 0.001' => [
        ['value' => '0', 'label' => 'Test', 'sort_order' => 1],
        ['values.0.value' => 'min'],
    ],
    '`sort_order` is required' => [
        ['value' => '1.0', 'label' => 'Test', 'sort_order' => null],
        ['values.0.sort_order' => 'required'],
    ],
]);

it('displays values count correctly', function () {
    $weightOption = WeightOption::factory()
        ->has(WeightOptionValue::factory()->count(5))
        ->create();

    livewire(ListWeightOptions::class)
        ->loadTable()
        ->assertTableColumnStateSet('values_count', 5, $weightOption);
});

it('can create weight option with default items', function () {
    livewire(CreateWeightOption::class)
        ->assertSchemaStateSet([
            'values' => [
                [
                    'value' => null,
                    'label' => null,
                    'sort_order' => 0,
                ],
            ],
        ]);
});
