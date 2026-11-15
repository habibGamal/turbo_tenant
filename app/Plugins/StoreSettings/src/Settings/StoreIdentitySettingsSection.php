<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Settings;

use App\Core\Contracts\SettingDefinition;
use App\Enums\SettingKey;
use App\Plugins\StoreSettings\src\Contracts\SettingsSection;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;

final class StoreIdentitySettingsSection implements SettingsSection
{
    public function pluginId(): string
    {
        return 'store-settings';
    }

    public function sort(): int
    {
        return 10;
    }

    public function tab(): Tab
    {
        return Tab::make('الهوية والمتجر')
            ->icon('heroicon-o-building-storefront')
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make(SettingKey::SITE_NAME->value)
                        ->label('اسم المتجر (إنجليزي)')
                        ->required()
                        ->maxLength(255),
                    TextInput::make(SettingKey::SITE_NAME_AR->value)
                        ->label('اسم المتجر (عربي)')
                        ->maxLength(255),
                    TextInput::make(SettingKey::CONTACT_EMAIL->value)
                        ->label('البريد الإلكتروني للتواصل')
                        ->email()
                        ->maxLength(255),
                    TextInput::make(SettingKey::CONTACT_PHONE->value)
                        ->label('رقم هاتف التواصل')
                        ->tel()
                        ->maxLength(20),
                    TextInput::make(SettingKey::CURRENCY->value)
                        ->label('رمز العملة الافتراضية')
                        ->required()
                        ->maxLength(10)
                        ->default('EGP'),
                    TextInput::make(SettingKey::CONTACT_ADDRESS->value)
                        ->label('عنوان المقر الرئيسي')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make(SettingKey::SITE_DESCRIPTION->value)
                        ->label('وصف المتجر المختصر')
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),
                Section::make('الشعارات والوسائط')->schema([
                    Grid::make(4)->schema([
                        FileUpload::make(SettingKey::SITE_LOGO->value)
                            ->label('شعار المتجر (Logo)')
                            ->image()
                            ->directory('images/site-images'),
                        FileUpload::make(SettingKey::SITE_FAVICON->value)
                            ->label('الأيقونة المفضلة (Favicon)')
                            ->image()
                            ->directory('images/site-images'),
                        FileUpload::make(SettingKey::IMAGE_PLACEHOLDER->value)
                            ->label('صورة بديلة للمنتجات (Placeholder)')
                            ->image()
                            ->directory('images/site-images'),
                        FileUpload::make(SettingKey::SVG_LOGO->value)
                            ->label('شعار المتجر (SVG Logo)')
                            ->image()
                            ->directory('images/site-images'),
                    ]),
                ]),
                Section::make('روابط التواصل الاجتماعي')->schema([
                    Repeater::make(SettingKey::SOCIAL_LINKS->value)
                        ->label('')
                        ->schema([
                            Select::make('platform')
                                ->label('المنصة')
                                ->options([
                                    'facebook' => 'فيسبوك (Facebook)',
                                    'instagram' => 'انستغرام (Instagram)',
                                    'twitter' => 'تويتر / X',
                                    'tiktok' => 'تيك توك (TikTok)',
                                    'youtube' => 'يوتيوب (YouTube)',
                                    'linkedin' => 'لينكد إن (LinkedIn)',
                                    'whatsapp' => 'واتساب (WhatsApp)',
                                    'telegram' => 'تيليجرام (Telegram)',
                                    'snapchat' => 'سناب شات (Snapchat)',
                                    'pinterest' => 'بينترست (Pinterest)',
                                    'other' => 'أخرى',
                                ])
                                ->required()
                                ->native(false),
                            TextInput::make('url')
                                ->label('رابط الصفحة')
                                ->url()
                                ->required()
                                ->maxLength(255),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->reorderable()
                        ->collapsible()
                        ->addActionLabel('إضافة رابط منصة جديدة'),
                ]),
            ]);
    }

    /**
     * @return list<SettingDefinition>
     */
    public function keys(): array
    {
        return [
            SettingKey::SITE_NAME,
            SettingKey::SITE_NAME_AR,
            SettingKey::CONTACT_EMAIL,
            SettingKey::CONTACT_PHONE,
            SettingKey::CONTACT_ADDRESS,
            SettingKey::SITE_DESCRIPTION,
            SettingKey::CURRENCY,
            SettingKey::SITE_LOGO,
            SettingKey::SITE_FAVICON,
            SettingKey::IMAGE_PLACEHOLDER,
            SettingKey::SVG_LOGO,
            SettingKey::SOCIAL_LINKS,
        ];
    }
}
