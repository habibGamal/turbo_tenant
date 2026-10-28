<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Filament\Pages;

use App\Services\ManifestService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
final class ManifestSettings extends Page
{
    use \App\Core\Plugins\Concerns\BelongsToPlugin;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static ?string $navigationLabel = 'تطبيق الويب (PWA)';

    protected static ?string $title = 'إعدادات تطبيق الويب (PWA Manifest)';

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات والنظام';

    protected string $view = 'filament.pages.settings';

    protected static ?int $navigationSort = 3;

    public function mount(): void
    {
        $manifestService = app(ManifestService::class);
        $this->form->fill($manifestService->getManifest());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('هوية التطبيق')
                        ->schema([
                            TextInput::make('name')
                                ->label('الاسم الكامل')
                                ->required()
                                ->maxLength(255)
                                ->helperText('الاسم الكامل للتطبيق كما يظهر على الشاشة الرئيسية للمستخدم'),
                            TextInput::make('short_name')
                                ->label('الاسم المختصر')
                                ->required()
                                ->maxLength(12)
                                ->helperText('الاسم المختصر المعروض أسفل أيقونة التطبيق (يوصى بحد أقصى 12 حرفاً)'),
                            TextInput::make('id')
                                ->label('معرف التطبيق')
                                ->required()
                                ->maxLength(255)
                                ->helperText('معرف فريد لتطبيقك (مثال: com.example.app)')
                                ->placeholder('com.example.app'),
                        ])
                        ->columns(2),

                    Section::make('المظهر والألوان')
                        ->schema([
                            ColorPicker::make('theme_color')
                                ->label('لون السمة (Theme Color)')
                                ->required()
                                ->helperText('لون شريط العنوان وواجهة المتصفح'),
                            ColorPicker::make('background_color')
                                ->label('لون الخلفية (Background Color)')
                                ->required()
                                ->helperText('لون الخلفية المعروض أثناء تحميل التطبيق'),
                        ])
                        ->columns(2),

                    Section::make('إعدادات العرض')
                        ->schema([
                            TextInput::make('start_url')
                                ->label('رابط البداية (Start URL)')
                                ->required()
                                ->maxLength(255)
                                ->default('.')
                                ->helperText('الرابط الذي يفتح عند تشغيل التطبيق'),
                            Select::make('display')
                                ->label('وضع العرض (Display Mode)')
                                ->required()
                                ->options([
                                    'fullscreen' => 'ملء الشاشة (Fullscreen)',
                                    'standalone' => 'تطبيق مستقل (Standalone)',
                                    'minimal-ui' => 'واجهة مصغرة (Minimal UI)',
                                    'browser' => 'متصفح تقليدي (Browser)',
                                ])
                                ->default('standalone')
                                ->helperText('طريقة عرض نافذة التطبيق'),
                            Select::make('orientation')
                                ->label('اتجاه الشاشة (Orientation)')
                                ->required()
                                ->options([
                                    'any' => 'تلقائي / أي اتجاه (Any)',
                                    'natural' => 'طبيعي للجهاز (Natural)',
                                    'landscape' => 'أفقي (Landscape)',
                                    'portrait' => 'عمودي (Portrait)',
                                ])
                                ->default('natural')
                                ->helperText('الاتجاه الافتراضي لعرض شاشة التطبيق'),
                        ])
                        ->columns(3),

                    Section::make('أيقونات التطبيق')
                        ->schema([
                            Repeater::make('icons')
                                ->schema([
                                    FileUpload::make('src')
                                        ->label('مصدر الأيقونة')
                                        ->image()
                                        ->directory('images/pwa-icons')
                                        ->required(),
                                    TextInput::make('sizes')
                                        ->label('الأبعاد (Sizes)')
                                        ->required()
                                        ->maxLength(50)
                                        ->placeholder('192x192')
                                        ->helperText('أبعاد الأيقونة بالبكسل (مثال: 192x192)'),
                                    TextInput::make('type')
                                        ->label('نوع الملف (MIME Type)')
                                        ->required()
                                        ->maxLength(50)
                                        ->placeholder('image/png')
                                        ->helperText('نوع ملف الصورة (MIME Type)'),
                                ])
                                ->columns(3)
                                ->defaultItems(3)
                                ->addActionLabel('إضافة أيقونة جديدة')
                                ->helperText('تحديد أيقونات التطبيق لمختلف أحجام الشاشات وأنظمة التشغيل'),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('حفظ الإعدادات')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $manifestService = app(ManifestService::class);
        $manifestService->saveManifest($data);

        Notification::make()
            ->success()
            ->title('تم الحفظ بنجاح')
            ->body('تم تحديث إعدادات تطبيق الويب (PWA) بنجاح.')
            ->send();
    }
}
