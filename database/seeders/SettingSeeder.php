<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\SettingService;
use Illuminate\Database\Seeder;

final class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settingService = app(SettingService::class);
        $settingService->seedDefaults();
    }
}
