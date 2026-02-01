<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BulkNotificationStatus;
use App\Jobs\SendBulkNotificationJob;
use App\Models\BulkNotification;
use Exception;
use Illuminate\Console\Command;
use Stancl\Tenancy\Concerns\HasATenantsOption;
use Stancl\Tenancy\Concerns\TenantAwareCommand;

final class SendScheduledBulkNotifications extends Command
{
    use HasATenantsOption, TenantAwareCommand;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send scheduled bulk notifications that are due';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $notifications = BulkNotification::query()
            ->where('status', BulkNotificationStatus::SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($notifications->isEmpty()) {
            $this->info('No scheduled notifications to send.');

            return self::SUCCESS;
        }

        $this->info("Found {$notifications->count()} scheduled notifications to send.");

        foreach ($notifications as $notification) {
            $this->line("Dispatching notification #{$notification->id}: {$notification->title}");

            try {
                SendBulkNotificationJob::dispatch($notification);
                $this->info("✓ Notification #{$notification->id} dispatched successfully");
            } catch (Exception $exception) {
                $this->error("✗ Failed to dispatch notification #{$notification->id}: {$exception->getMessage()}");
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
