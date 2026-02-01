<?php

declare(strict_types=1);

namespace App\Filament\Resources\BulkNotifications\Schemas;

use App\Enums\BulkNotificationStatus;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class BulkNotificationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('محتوى الإشعار')
                    ->schema([
                        TextEntry::make('title')
                            ->label('العنوان')
                            ->size('lg')
                            ->weight('bold')
                            ->columnSpanFull(),
                        TextEntry::make('body')
                            ->label('الرسالة')
                            ->columnSpanFull(),
                        KeyValueEntry::make('data')
                            ->label('البيانات الإضافية')
                            ->visible(fn ($record) => ! empty($record->data))
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(2),

                Section::make('الحالة')
                    ->schema([
                        TextEntry::make('status')
                            ->label('حالة الإرسال')
                            ->badge()
                            ->color(fn (BulkNotificationStatus $state): string => $state->color())
                            ->formatStateUsing(fn (BulkNotificationStatus $state): string => $state->label()),
                        TextEntry::make('scheduled_at')
                            ->label('موعد الإرسال')
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('إرسال فوري'),
                        TextEntry::make('sent_at')
                            ->label('وقت الإرسال الفعلي')
                            ->dateTime('Y-m-d H:i')
                            ->visible(fn ($record) => $record->sent_at),
                    ])
                    ->columnSpan(1),

                Section::make('إحصائيات الإرسال')
                    ->schema([
                        TextEntry::make('total_recipients')
                            ->label('إجمالي المستلمين')
                            ->numeric()
                            ->badge()
                            ->color('info'),
                        TextEntry::make('successful_sends')
                            ->label('تم الإرسال بنجاح')
                            ->numeric()
                            ->badge()
                            ->color('success'),
                        TextEntry::make('failed_sends')
                            ->label('فشل الإرسال')
                            ->numeric()
                            ->badge()
                            ->color('danger')
                            ->visible(fn ($record) => $record->failed_sends > 0),
                    ])
                    ->columns(3)
                    ->columnSpanFull()
                    ->visible(fn ($record) => $record->total_recipients > 0),

                Section::make('التواريخ')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('تاريخ الإنشاء')
                            ->dateTime('Y-m-d H:i'),
                        TextEntry::make('updated_at')
                            ->label('آخر تحديث')
                            ->dateTime('Y-m-d H:i'),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible(),
            ]);
    }
}
