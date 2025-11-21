<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => config(key: 'app.default_user.name'),
            'email' => config('app.default_user.email'),
            'password' => bcrypt(config('app.default_user.password')),
        ]);

        // Seed restaurant data
        $this->call([
            GovernorateSeeder::class,
            BranchSeeder::class,
            AreaSeeder::class,
            // CategorySeeder::class,
            // SectionSeeder::class,
            // WeightOptionSeeder::class,
            // ProductSeeder::class,
            // PackageSeeder::class,
        ]);
    }
}
