<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Filament\RelationManagers;

use App\Plugins\PosIntegration\src\Filament\Actions\OrderDeliveryActions;
use App\Plugins\PosIntegration\src\Models\OrderPosDeliveryAttempt;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class OrderDeliveryAttemptsRelationManager extends RelationManager
{
    protected static string $relationship = 'posDeliveryAttempts';

    protected static ?string $title = 'سجل محاولات الإرسال والمراجعات';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return OrderDeliveryActions::authorized() && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function table(Table $table): Table
    {
        abort_unless(OrderDeliveryActions::authorized(), 403);

        return $table->defaultSort('id', 'desc')->poll('5s')->paginated([10, 25, 50])
            ->emptyStateHeading('لا توجد محاولات مسجلة')
            ->emptyStateDescription('تظهر المحاولات هنا عند بدء عامل قائمة الانتظار معالجة الطلب.')
            ->columns([
                TextColumn::make('attempt_number')->label('المحاولة')->formatStateUsing(fn ($state, OrderPosDeliveryAttempt $record) => $record->trigger === 'verification' ? 'مراجعة' : $state),
                TextColumn::make('trigger')->label('المصدر')->formatStateUsing(fn (string $state): string => match ($state) {
                    'manual' => 'إرسال يدوي', 'verification' => 'تحقق مع الفرع', default => 'تلقائي',
                }),
                TextColumn::make('requested_by_name')->label('بواسطة')->placeholder('النظام'),
                TextColumn::make('outcome')->label('النتيجة')->formatStateUsing(fn (OrderPosDeliveryAttempt $record): string => $record->outcomeLabel())->badge(),
                TextColumn::make('started_at')->label('بدأت في')->dateTime('Y-m-d H:i:s'),
                TextColumn::make('finished_at')->label('انتهت في')->dateTime('Y-m-d H:i:s')->placeholder('جارِ التنفيذ'),
                TextColumn::make('duration_ms')->label('المدة (مللي ثانية)')->placeholder('—'),
                TextColumn::make('error')->label('سبب الفشل / تفاصيل المراجعة')->wrap()->placeholder('—'),
            ]);
    }
}
