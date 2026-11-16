<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Filament\Contributors;

use App\Core\Plugins\Contracts\FormComponentContributor;
use Filament\Forms\Components\TextInput;

/**
 * Adds the branch POS API key to the branch form. The POS rejects website
 * calls without a matching X-Api-Key header (its WEB_API_KEY).
 */
final class PosBranchApiKeyFormContributor implements FormComponentContributor
{
    public function pluginId(): string
    {
        return 'pos-integration';
    }

    /**
     * @return array<TextInput>
     */
    public function components(): array
    {
        return [
            TextInput::make('pos_api_key')
                ->label('مفتاح API لنقطة البيع')
                ->password()
                ->revealable()
                ->maxLength(255)
                ->helperText('قيمة WEB_API_KEY في نقطة البيع الخاصة بهذا الفرع، وتُرسل في ترويسة X-Api-Key'),
        ];
    }
}
