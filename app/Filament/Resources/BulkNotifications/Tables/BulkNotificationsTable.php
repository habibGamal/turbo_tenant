<?php

declare(strict_types=1);

namespace App\Filament\Resources\BulkNotifications\Tables;

use App\Enums\BulkNotificationStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                    ->limit(50),
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
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
