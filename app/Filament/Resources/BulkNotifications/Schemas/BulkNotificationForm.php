<?php

declare(strict_types=1);

namespace App\Filament\Resources\BulkNotifications\Schemas;

use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class BulkNotificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('محتوى الإشعار')
                    ->description('أدخل محتوى الإشعار الذي سيتم إرساله')
                    ->schema([
                        TextInput::make('title')
                            ->label('العنوان')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('body')
                            ->label('الرسالة')
                            ->required()
                            ->rows(4)
                            ->maxLength(65535)
                            ->columnSpanFull(),
                        KeyValue::make('data')
                            ->label('بيانات إضافية (اختياري)')
                            ->keyLabel('المفتاح')
                            ->valueLabel('القيمة')
                            ->addActionLabel('إضافة بيانات')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpan(2),

                Section::make('الإعدادات')
                    ->schema([
                        Radio::make('send_type')
                            ->label('نوع الإرسال')
                            ->options([
                                'immediate' => 'إرسال فوري',
                                'scheduled' => 'جدولة الإرسال',
                            ])
                            ->default('immediate')
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state === 'immediate') {
                                    $set('scheduled_at', null);
                                }
                            }),
                        DateTimePicker::make('scheduled_at')
                            ->label('وقت الإرسال المجدول')
                            ->visible(fn ($get) => $get('send_type') === 'scheduled')
                            ->required(fn ($get) => $get('send_type') === 'scheduled')
                            ->minDate(now())
                            ->native(false)
                            ->seconds(false),
                    ])
                    ->columnSpan(1),

                Section::make('المستخدمون المستهدفون')
                    ->description('اختر المستخدمين الذين سيتلقون الإشعار')
                    ->schema([
                        Toggle::make('send_to_all')
                            ->label('إرسال لجميع المستخدمين')
                            ->default(true)
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $set('target_user_ids', null);
                                }
                            }),
                        CheckboxList::make('target_user_ids')
                            ->label('اختر المستخدمين')
                            ->visible(fn ($get) => ! $get('send_to_all'))
                            ->options(
                                User::whereNotNull('expo_token')
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(2)
                            ->required(fn ($get) => ! $get('send_to_all')),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
