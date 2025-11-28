<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendOrderToPosJob;
use App\Models\Branch;
use App\Models\Order;
use App\Services\OrderPOSService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendOrderToPosJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_example()
    {
        $this->assertTrue(true);
    }
}
