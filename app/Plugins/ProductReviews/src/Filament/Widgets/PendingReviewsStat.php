<?php

declare(strict_types=1);

namespace App\Plugins\ProductReviews\src\Filament\Widgets;

use App\Plugins\ProductReviews\src\Filament\Resources\ReviewResource;
use App\Plugins\ProductReviews\src\Models\Review;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Throwable;

final class PendingReviewsStat extends BaseWidget
{
    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        if (! class_exists(Review::class)) {
            return false;
        }

        if (function_exists('tenancy') && tenancy()->initialized && ! app(\App\Core\Plugins\Services\PluginManager::class)->isPluginActive('product-reviews')) {
            return false;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('reviews')) {
            return false;
        }

        try {
            return Review::query()->where('is_approved', false)->count() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    protected function getStats(): array
    {
        $count = Review::query()->where('is_approved', false)->count();

        $url = class_exists(ReviewResource::class)
            ? ReviewResource::getUrl('index')
            : null;

        $stat = Stat::make('تقييمات بانتظار الموافقة', (string) $count)
            ->description('اضغط لمراجعة واعتماد التقييمات')
            ->descriptionIcon('heroicon-m-chat-bubble-bottom-center-text')
            ->color('warning');

        if ($url) {
            $stat->url($url);
        }

        return [$stat];
    }
}
