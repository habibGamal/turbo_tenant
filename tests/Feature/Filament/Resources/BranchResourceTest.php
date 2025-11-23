<?php

declare(strict_types=1);

use App\Filament\Resources\Branches\Pages\CreateBranch;
use App\Filament\Resources\Branches\Pages\EditBranch;
use App\Filament\Resources\Branches\Pages\ListBranches;
use App\Models\Branch;
use App\Models\User;
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
    livewire(ListBranches::class)
        ->assertOk();
});

it('can render the create page', function () {
    livewire(CreateBranch::class)
        ->assertOk();
});

it('can render the edit page', function () {
    $branch = Branch::factory()->create();

    livewire(EditBranch::class, [
        'record' => $branch->id,
    ])
        ->assertOk()
        ->assertSchemaStateSet([
            'name' => $branch->name,
            'link' => $branch->link,
            'is_active' => $branch->is_active,
        ]);
});

it('has column', function (string $column) {
    livewire(ListBranches::class)
        ->assertTableColumnExists($column);
})->with(['name', 'link', 'is_active', 'created_at', 'updated_at']);

it('can render column', function (string $column) {
    Branch::factory()->create();

    livewire(ListBranches::class)
        ->assertCanRenderTableColumn($column);
})->with(['name', 'link', 'is_active', 'created_at', 'updated_at']);

it('can sort column', function (string $column) {
    $records = Branch::factory(5)->create();

    livewire(ListBranches::class)
        ->loadTable()
        ->sortTable($column)
        ->assertCanSeeTableRecords($records->sortBy($column), inOrder: true)
        ->sortTable($column, 'desc')
        ->assertCanSeeTableRecords($records->sortByDesc($column), inOrder: true);
})->with(['name']);

it('can search column', function (string $column) {
    $records = Branch::factory(5)->create();

    $value = $records->first()->{$column};

    livewire(ListBranches::class)
        ->loadTable()
        ->searchTable($value)
        ->assertCanSeeTableRecords($records->where($column, $value))
        ->assertCanNotSeeTableRecords($records->where($column, '!=', $value));
})->with(['name', 'link']);

it('can create a branch', function () {
    $branch = Branch::factory()->make();

    livewire(CreateBranch::class)
        ->fillForm([
            'name' => $branch->name,
            'link' => $branch->link,
            'is_active' => $branch->is_active,
        ])
        ->call('create')
        ->assertNotified();

    assertDatabaseHas(Branch::class, [
        'name' => $branch->name,
        'link' => $branch->link,
        'is_active' => $branch->is_active,
    ]);
});

it('can update a branch', function () {
    $branch = Branch::factory()->create();
    $newBranchData = Branch::factory()->make();

    livewire(EditBranch::class, [
        'record' => $branch->id,
    ])
        ->fillForm([
            'name' => $newBranchData->name,
            'link' => $newBranchData->link,
            'is_active' => $newBranchData->is_active,
        ])
        ->call('save')
        ->assertNotified();

    assertDatabaseHas(Branch::class, [
        'id' => $branch->id,
        'name' => $newBranchData->name,
        'link' => $newBranchData->link,
        'is_active' => $newBranchData->is_active,
    ]);
});

it('can delete a branch', function () {
    $branch = Branch::factory()->create();

    livewire(EditBranch::class, [
        'record' => $branch->id,
    ])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseMissing($branch);
});

it('can bulk delete branches', function () {
    $branches = Branch::factory(5)->create();

    livewire(ListBranches::class)
        ->loadTable()
        ->assertCanSeeTableRecords($branches)
        ->selectTableRecords($branches)
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertNotified()
        ->assertCanNotSeeTableRecords($branches);

    $branches->each(fn (Branch $branch) => assertDatabaseMissing($branch));
});

it('validates the form data', function (array $data, array $errors) {
    $branch = Branch::factory()->create();
    $newBranchData = Branch::factory()->make();

    livewire(EditBranch::class, [
        'record' => $branch->id,
    ])
        ->fillForm([
            'name' => $newBranchData->name,
            'link' => $newBranchData->link,
            'is_active' => $newBranchData->is_active,
            ...$data,
        ])
        ->call('save')
        ->assertHasFormErrors($errors)
        ->assertNotNotified();
})->with([
    '`name` is required' => [['name' => null], ['name' => 'required']],
    '`name` is max 255 characters' => [['name' => Str::random(256)], ['name' => 'max']],
    '`link` is required' => [['link' => null], ['link' => 'required']],
    '`is_active` is required' => [['is_active' => null], ['is_active' => 'required']],
]);

it('can filter by is_active', function () {
    $activeBranches = Branch::factory(3)->create(['is_active' => true]);
    $inactiveBranches = Branch::factory(2)->create(['is_active' => false]);

    livewire(ListBranches::class)
        ->loadTable()
        ->assertCanSeeTableRecords($activeBranches)
        ->assertCanSeeTableRecords($inactiveBranches);
});

it('creates branch with default is_active value', function () {
    livewire(CreateBranch::class)
        ->assertSchemaStateSet([
            'is_active' => true,
        ]);
});
