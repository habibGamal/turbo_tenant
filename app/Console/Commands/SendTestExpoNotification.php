<?php

namespace App\Console\Commands;

use Stancl\Tenancy\Concerns\HasATenantsOption;
use Stancl\Tenancy\Concerns\TenantAwareCommand;


use Illuminate\Console\Command;

use App\Models\User;
use App\Notifications\TestExpoNotification;

class SendTestExpoNotification extends Command
{
    
    use TenantAwareCommand ,HasATenantsOption ;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expo:send-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test Expo notification to all users with an Expo token.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::whereNotNull('expo_token')->get();

        $this->info("Found {$users->count()} users with Expo tokens.");

        foreach ($users as $user) {
            $this->line("Sending to user: {$user->email}");
            try {
                $user->notify(new TestExpoNotification());
            } catch (\Exception $e) {
                $this->error("Failed to send to {$user->email}: " . $e->getMessage());
            }
        }

        $this->info('Done.');
    }
}
