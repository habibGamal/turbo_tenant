<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BulkNotificationStatus;
use App\Models\BulkNotification;
use App\Models\User;
use App\Notifications\BulkUserNotification;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendBulkNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public BulkNotification $bulkNotification
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Update status to sending
            $this->bulkNotification->update([
                'status' => BulkNotificationStatus::SENDING,
            ]);

            // Get target users
            $users = $this->getTargetUsers();

            $successful = 0;
            $failed = 0;

            foreach ($users as $user) {
                try {
                    if ($user->expo_token) {
                        $user->notify(new BulkUserNotification($this->bulkNotification));
                        $successful++;
                    }
                } catch (Exception $exception) {
                    logger()->error('Failed to send bulk notification to user', [
                        'bulk_notification_id' => $this->bulkNotification->id,
                        'user_id' => $user->id,
                        'error' => $exception->getMessage(),
                    ]);
                    $failed++;
                }
            }

            // Update notification with results
            $this->bulkNotification->update([
                'status' => $failed === 0 ? BulkNotificationStatus::SENT : BulkNotificationStatus::FAILED,
                'sent_at' => now(),
                'total_recipients' => $users->count(),
                'successful_sends' => $successful,
                'failed_sends' => $failed,
            ]);
        } catch (Exception $exception) {
            logger()->error('Failed to send bulk notification', [
                'bulk_notification_id' => $this->bulkNotification->id,
                'error' => $exception->getMessage(),
            ]);

            $this->bulkNotification->update([
                'status' => BulkNotificationStatus::FAILED,
            ]);

            throw $exception;
        }
    }

    /**
     * Get the users to send notifications to.
     */
    private function getTargetUsers()
    {
        $query = User::query()->whereNotNull('expo_token');

        // If specific users are targeted
        if ($this->bulkNotification->target_user_ids) {
            $query->whereIn('id', $this->bulkNotification->target_user_ids);
        }

        return $query->get();
    }
}
