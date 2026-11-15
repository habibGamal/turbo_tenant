<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Filament\Contributors;

use App\Core\Plugins\Contracts\FormComponentContributor;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\PosIntegration\src\Filament\Components\PosItemSelect;
use App\Plugins\PosIntegration\src\Models\PosLineMapping;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PosPackageMappingFormContributor implements FormComponentContributor
{
    public function pluginId(): string
    {
        return 'pos-integration';
    }

    /**
     * @return array<Section>
     */
    public function components(): array
    {
        return [
            Section::make('ربط نقطة البيع (POS)')
                ->schema([
                    Repeater::make('pos_line_mappings')
                        ->label('ربط الفروع بنقطة البيع')
                        ->dehydrated(false)
                        ->schema([
                            Select::make('branch_id')
                                ->label('الفرع')
                                ->options(fn () => Branch::query()->pluck('name', 'id')->toArray())
                                ->placeholder('جميع الفروع')
                                ->distinct()
                                ->nullable(),
                            PosItemSelect::make('pos_item_id')
                                ->required(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('إضافة ربط جديد')
                        ->rules([
                            fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (! is_array($value)) {
                                    return;
                                }

                                $seen = [];
                                foreach ($value as $row) {
                                    $branchKey = ! empty($row['branch_id']) ? (string) $row['branch_id'] : '__all__';
                                    if (in_array($branchKey, $seen, true)) {
                                        $fail('لا يمكن تكرار نفس الفرع في الربط.');

                                        return;
                                    }
                                    $seen[] = $branchKey;
                                }
                            },
                        ])
                        ->afterStateHydrated(function (Repeater $component, ?Model $record): void {
                            if ($record === null || ! $record->exists) {
                                $component->state([]);

                                return;
                            }

                            $mappings = PosLineMapping::query()
                                ->where('line_type', 'package')
                                ->where('line_reference_id', $record->getKey())
                                ->get();

                            $component->state($mappings->map(fn (PosLineMapping $m): array => [
                                'branch_id' => $m->branch_id,
                                'pos_item_id' => $m->pos_item_id,
                            ])->toArray());
                        })
                        ->saveRelationshipsUsing(function (?Model $record, mixed $state, Repeater $component): void {
                            if ($record === null || ! $record->exists) {
                                return;
                            }

                            $rows = array_values(is_array($state) ? $state : ($component->getState() ?: []));

                            $seen = [];
                            foreach ($rows as $row) {
                                $branchKey = ! empty($row['branch_id']) ? (string) $row['branch_id'] : '__all__';
                                if (in_array($branchKey, $seen, true)) {
                                    throw ValidationException::withMessages([
                                        'pos_line_mappings' => ['لا يمكن تكرار نفس الفرع في الربط.'],
                                    ]);
                                }
                                $seen[] = $branchKey;
                            }

                            DB::transaction(function () use ($record, $rows): void {
                                PosLineMapping::query()
                                    ->where('line_type', 'package')
                                    ->where('line_reference_id', $record->getKey())
                                    ->delete();

                                foreach ($rows as $row) {
                                    if (empty($row['pos_item_id'])) {
                                        continue;
                                    }

                                    PosLineMapping::create([
                                        'line_type' => 'package',
                                        'line_reference_id' => $record->getKey(),
                                        'branch_id' => ! empty($row['branch_id']) ? (int) $row['branch_id'] : null,
                                        'pos_item_id' => (string) $row['pos_item_id'],
                                    ]);
                                }
                            });
                        }),
                ]),
        ];
    }
}
