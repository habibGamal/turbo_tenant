<?php

declare(strict_types=1);

namespace App\Plugins\SampleGreeting;

use Illuminate\Support\ServiceProvider;

final class SampleGreetingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Plugins\SampleGreeting\src\DTOs\SampleGreetingConfig::class, function () {
            return new \App\Plugins\SampleGreeting\src\DTOs\SampleGreetingConfig();
        });

        $this->app->singleton('sample-greeting.message', function ($app) {
            $config = $app->make(\App\Plugins\SampleGreeting\src\DTOs\SampleGreetingConfig::class);

            return $config->defaultGreeting;
        });
    }

    public function boot(): void
    {
        //
    }
}
