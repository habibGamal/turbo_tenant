<?php

declare(strict_types=1);

namespace App\Plugins\SampleGreeting;

use Illuminate\Support\ServiceProvider;

final class SampleGreetingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('sample-greeting.message', function () {
            return 'Hello from SampleGreetingPlugin!';
        });
    }

    public function boot(): void
    {
        //
    }
}
