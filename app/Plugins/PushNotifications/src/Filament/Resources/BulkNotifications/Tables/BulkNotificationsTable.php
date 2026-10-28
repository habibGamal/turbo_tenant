<?php

declare(strict_types=1);

namespace App\Plugins\PushNotifications\src\Filament\Resources\BulkNotifications\Tables;

use App\Plugins\PushNotifications\src\Enums\BulkNotificationStatus;
use App\Plugins\PushNotifications\src\Filament\Resources\BulkNotifications\BulkNotificationResource;
use App\Plugins\PushNotifications\src\Models\BulkNotification;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class BulkNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('body')
                    ->label('نص الرسالة')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->body)
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (BulkNotificationStatus $state): string => $state->color())
                    ->formatStateUsing(fn (BulkNotificationStatus $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('total_recipients')
                    ->label('المستلمون')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('successful_sends')
                    ->label('تم الإرسال')
                    ->numeric()
                    ->color('success')
                    ->sortable(),
                TextColumn::make('failed_sends')
                    ->label('فشل')
                    ->numeric()
                    ->color('danger')
                    ->sortable()
                    ->visible(fn ($record) => $record && $record->failed_sends > 0),
                TextColumn::make('success_rate')
                    ->label('نسبة النجاح')
                    ->badge()
                    ->state(function (BulkNotification $record): string {
                        if ($record->total_recipients <= 0) {
                            return '—';
                        }
                        $pct = round(($record->successful_sends / $record->total_recipients) * 100);

                        return "{$pct}%";
                    })
                    ->color(function (BulkNotification $record): string {
                        if ($record->total_recipients <= 0) {
                            return 'gray';
                        }
                        $pct = ($record->successful_sends / $record->total_recipients) * 100;

                        return match (true) {
                            $pct >= 90 => 'success',
                            $pct >= 70 => 'warning',
                            default => 'danger',
                        };
                    })
                    ->toggleable(),
                TextColumn::make('scheduled_at')
                    ->label('موعد الإرسال')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('فوري')
                    ->toggleable(),
                TextColumn::make('sent_at')
                    ->label('وقت الإرسال الفعلي')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        BulkNotificationStatus::DRAFT->value => BulkNotificationStatus::DRAFT->label(),
                        BulkNotificationStatus::SCHEDULED->value => BulkNotificationStatus::SCHEDULED->label(),
                        BulkNotificationStatus::SENDING->value => BulkNotificationStatus::SENDING->label(),
                        BulkNotificationStatus::SENT->value => BulkNotificationStatus::SENT->label(),
                        BulkNotificationStatus::FAILED->value => BulkNotificationStatus::FAILED->label(),
                    ])
                    ->multiple(),
                TernaryFilter::make('is_scheduled')
                    ->label('نوع الإرسال')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('scheduled_at'),
                        false: fn ($query) => $query->whereNull('scheduled_at'),
                    )
                    ->trueLabel('مجدول فقط')
                    ->falseLabel('فوري فقط')
                    ->native(false),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label('من تاريخ'),
                        DatePicker::make('created_until')->label('إلى تاريخ'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('لا توجد إشعارات جماعية')
            ->emptyStateDescription('أرسل إشعارات Push تنبيهية وترويجية مباشرة لهواتف عملائك المستهدفين.')
            ->emptyStateIcon('heroicon-o-bell')
            ->emptyStateActions([
                Action::make('create')
                    ->label('إنشاء إشعار جديد')
                    ->url(fn () => BulkNotificationResource::getUrl('create'))
                    ->icon('heroicon-m-plus')
                    ->button(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
