<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Filament\Resources\Packages\Pages;

use App\Plugins\ComboPackages\src\Filament\Resources\Packages\PackageResource;
use App\Plugins\ComboPackages\src\Filament\Resources\Packages\Schemas\PackageForm;
use App\Plugins\ComboPackages\src\Services\PackagePriceEstimator;
use App\Plugins\ComboPackages\src\Services\PackageRulesValidator;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

final class EditPackage extends EditRecord
{
    protected static string $resource = PackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function beforeFill(): void
    {
        $record = $this->getRecord();
        $record->loadMissing('groups.items');

        $productIds = [];
        foreach ($record->groups as $group) {
            foreach ($group->items as $item) {
                if ($item->product_id) {
                    $productIds[] = (int) $item->product_id;
                }
            }
        }

        PackageForm::preloadProductLabels($productIds);
    }

    protected function beforeSave(): void
    {
        $rawState = $this->form->getRawState() ?? [];
        $dataState = $this->data ?? [];
        $state = array_merge($rawState, $dataState);

        $state = $this->sanitizeAllGroupsDefault($state);
        $this->data = $state;

        $errors = app(PackageRulesValidator::class)->validate($state);
        if (! empty($errors)) {
            $mapped = [];
            foreach ($errors as $key => $msg) {
                $mapped[$key] = $msg;
                $mapped["data.{$key}"] = $msg;
            }
            throw ValidationException::withMessages($mapped);
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // packages.name is NOT NULL; an Arabic-only package stores its Arabic name there too.
        if (blank($data['name'] ?? null) && filled($data['name_ar'] ?? null)) {
            $data['name'] = $data['name_ar'];
        }

        $rawState = array_merge($this->form->getRawState() ?? [], $this->data ?? [], $data);

        $estimator = app(PackagePriceEstimator::class);
        $estimate = $estimator->estimate($rawState);

        if (! isset($data['original_price']) || $data['original_price'] === '' || $data['original_price'] === null) {
            $data['original_price'] = $estimate > 0 ? $estimate : null;
        } else {
            $data['original_price'] = (float) $data['original_price'];
        }

        $original = (float) ($data['original_price'] ?? 0);
        $price = (float) ($data['price'] ?? 0);

        if ($original > 0 && $original > $price) {
            $data['discount_percentage'] = (int) round((($original - $price) / $original) * 100);
        } else {
            $data['discount_percentage'] = null;
        }

        return $data;
    }

    private function sanitizeAllGroupsDefault(array $state): array
    {
        if (! isset($state['groups']) || ! is_array($state['groups'])) {
            return $state;
        }

        foreach ($state['groups'] as $gKey => $group) {
            $type = $group['selection_type'] ?? 'all';
            if ($type === 'choose_one') {
                $state['groups'][$gKey]['min_selections'] = 1;
                $state['groups'][$gKey]['max_selections'] = 1;
            } elseif ($type === 'all' && isset($group['items']) && is_array($group['items'])) {
                foreach ($group['items'] as $iKey => $item) {
                    $state['groups'][$gKey]['items'][$iKey]['is_default'] = false;
                }
            }
        }

        return $state;
    }
}
