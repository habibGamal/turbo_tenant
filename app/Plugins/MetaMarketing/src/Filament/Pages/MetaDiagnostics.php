<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Filament\Pages;

use App\Core\Plugins\Concerns\BelongsToPlugin;
use App\Plugins\MetaMarketing\src\Catalog\CatalogFeedValidator;
use App\Plugins\MetaMarketing\src\Diagnostics\MetaDiagnosticsService;
use App\Plugins\MetaMarketing\src\Diagnostics\MetaIdConsistencyChecker;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Jobs\SendMetaPurchaseEvent;
use App\Plugins\MetaMarketing\src\Models\MetaCapiEvent;
use App\Plugins\StoreSettings\src\Filament\Pages\Settings;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Admin page to confirm the Meta integration works: configuration, token check, test event,
 * Conversions API delivery log, catalog feed check and content-id consistency.
 * Built only from native Filament schema components so it follows the panel theme.
 */
final class MetaDiagnostics extends Page implements HasTable
{
    use BelongsToPlugin;
    use InteractsWithTable;

    private const array PROBLEM_LABELS = [
        CatalogFeedValidator::PROBLEM_MISSING_IMAGE => 'المنتج بدون صورة — لن يظهر في الكتالوج',
        CatalogFeedValidator::PROBLEM_MISSING_BRAND => 'العلامة التجارية غير محددة',
        CatalogFeedValidator::PROBLEM_DUPLICATE_ID => 'معرّف مكرر',
        CatalogFeedValidator::PROBLEM_EMPTY_TITLE => 'العنوان فارغ',
        CatalogFeedValidator::PROBLEM_LONG_TITLE => 'العنوان أطول من 200 حرف',
        CatalogFeedValidator::PROBLEM_MISSING_PRICE => 'السعر غير محدد أو صفر',
        CatalogFeedValidator::PROBLEM_SALE_NOT_BELOW_PRICE => 'سعر الخصم ليس أقل من السعر',
        CatalogFeedValidator::PROBLEM_DISCOUNT_ABOVE_BASE => 'سعر الخصم أعلى من السعر الأساسي (يُحصّل من العميل)',
    ];

    /**
     * @var array{successful: bool, pixel_id: ?string, name: ?string, error: ?string, fbtrace_id: ?string, checked_at: string}|null
     */
    public ?array $tokenCheckResult = null;

    /**
     * @var array{successful: bool, events_received: ?int, fbtrace_id: ?string, error: ?string, sent_at: string}|null
     */
    public ?array $testEventResult = null;

    /**
     * @var array{total_products: int, total_rows: int, rows_skipped: int, issues: list<array{id: string, field: string, problem: string}>}|null
     */
    public ?array $catalogValidationReport = null;

    /**
     * @var list<array<string, string>>|null
     */
    public ?array $catalogPreviewRows = null;

    /**
     * @var array{total_orders: int, total_ids: int, matched: int, percentage: float, missing_ids: list<string>, checked_at: string}|null
     */
    public ?array $idConsistencyReport = null;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'تشخيص تكامل ميتا';

    protected static ?string $title = 'تشخيص تكامل ميتا';

    protected static string|UnitEnum|null $navigationGroup = 'المحتوى والتسويق';

    protected static ?int $navigationSort = 10;

    public function getConfig(): MetaMarketingConfig
    {
        return app(MetaMarketingConfig::class);
    }

    /**
     * Why Graph API calls are not possible yet, or null when the Pixel ID and access token are both saved.
     */
    public function missingCredentials(): ?string
    {
        $missing = array_keys(array_filter([
            'معرّف البكسل (Pixel ID)' => blank($this->getConfig()->pixelId),
            'رمز الوصول (Access Token)' => blank($this->getConfig()->accessToken),
        ]));

        return $missing === [] ? null : 'أضف '.implode(' و', $missing).' من الإعدادات ← تسويق ميتا ← واجهة التحويلات.';
    }

    public function getSubheading(): string
    {
        return 'تحقّق من أن البكسل وواجهة التحويلات (Conversions API) وكتالوج المنتجات تعمل بشكل صحيح.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('metaDiagnostics')
                ->persistTabInQueryString('tab')
                ->contained(false)
                ->tabs([
                    Tab::make('الحالة والاتصال')
                        ->key('status', isInheritable: false)
                        ->icon('heroicon-o-signal')
                        ->schema([$this->configurationSection(), $this->connectionTests()]),
                    Tab::make('سجل الأحداث')
                        ->key('log', isInheritable: false)
                        ->icon('heroicon-o-queue-list')
                        ->badge(fn (): ?int => MetaCapiEvent::query()->where('status', 'failed')->count() ?: null)
                        ->badgeColor('danger')
                        ->schema([EmbeddedTable::make()]),
                    Tab::make('كتالوج المنتجات')
                        ->key('catalog', isInheritable: false)
                        ->icon('heroicon-o-shopping-bag')
                        ->schema($this->catalogComponents()),
                    Tab::make('تطابق المعرّفات')
                        ->key('ids', isInheritable: false)
                        ->icon('heroicon-o-arrows-right-left')
                        ->schema([$this->idConsistencySection()]),
                ]),
        ]);
    }

    public function openSettingsAction(): Action
    {
        return Action::make('openSettings')
            ->label('إعدادات ميتا')
            ->icon('heroicon-o-cog-6-tooth')
            ->color('gray')
            ->url(fn (): string => Settings::getUrl());
    }

    public function verifyCredentialsAction(): Action
    {
        return Action::make('verifyCredentials')
            ->label('فحص الرمز')
            ->icon('heroicon-o-shield-check')
            ->disabled(fn (): bool => $this->missingCredentials() !== null)
            ->tooltip(fn (): ?string => $this->missingCredentials())
            ->action(fn () => $this->executeVerifyCredentials());
    }

    public function sendTestEventAction(): Action
    {
        return Action::make('sendTestEvent')
            ->label('إرسال حدث تجريبي')
            ->icon('heroicon-o-paper-airplane')
            // Works before the Conversions API toggle is on, so the setup can be verified first.
            ->disabled(fn (): bool => $this->missingCredentials() !== null)
            ->tooltip(fn (): ?string => $this->missingCredentials())
            ->requiresConfirmation(fn (): bool => blank($this->getConfig()->testEventCode))
            ->modalHeading('رمز أحداث الاختبار غير محدد')
            ->modalDescription('سيُحتسب هذا الحدث كحدث حقيقي في إحصائيات ميتا. هل تريد المتابعة؟')
            ->modalSubmitActionLabel('نعم، أرسل')
            ->action(fn () => $this->executeSendTestEvent());
    }

    public function runCatalogCheckAction(): Action
    {
        return Action::make('runCatalogCheck')
            ->label('تشغيل فحص الكتالوج')
            ->icon('heroicon-o-magnifying-glass')
            ->action(fn () => $this->executeCatalogCheck());
    }

    public function checkIdConsistencyAction(): Action
    {
        return Action::make('checkIdConsistency')
            ->label('تشغيل فحص التطابق')
            ->icon('heroicon-o-arrows-right-left')
            ->action(fn () => $this->executeIdConsistencyCheck());
    }

    public function executeVerifyCredentials(): void
    {
        $result = app(MetaDiagnosticsService::class)->verifyCredentials();

        $this->tokenCheckResult = [
            'successful' => $result->successful,
            'pixel_id' => isset($result->raw['id']) ? (string) $result->raw['id'] : $this->getConfig()->pixelId,
            'name' => isset($result->raw['name']) ? (string) $result->raw['name'] : null,
            'error' => $result->errorMessage,
            'fbtrace_id' => $result->fbtraceId,
            'checked_at' => now()->toDateTimeString(),
        ];

        Notification::make()
            ->title($result->successful ? 'الرمز صالح' : 'فشل فحص الرمز')
            ->body($result->successful ? null : ($result->errorMessage ?? 'تعذّر الاتصال بميتا'))
            ->status($result->successful ? 'success' : 'danger')
            ->send();
    }

    public function executeSendTestEvent(): void
    {
        $result = app(MetaDiagnosticsService::class)->sendTestPageView(request());

        $this->testEventResult = [
            'successful' => $result->successful,
            'events_received' => $result->eventsReceived,
            'fbtrace_id' => $result->fbtraceId,
            'error' => $result->errorMessage,
            'sent_at' => now()->toDateTimeString(),
        ];

        Notification::make()
            ->title($result->successful ? 'تم إرسال الحدث التجريبي' : 'فشل إرسال الحدث التجريبي')
            ->body($result->successful ? 'راجع Events Manager ← Test Events' : ($result->errorMessage ?? 'تعذّر الاتصال بميتا'))
            ->status($result->successful ? 'success' : 'danger')
            ->send();
    }

    public function executeCatalogCheck(): void
    {
        $validator = app(CatalogFeedValidator::class);
        $report = $validator->validate();

        $this->catalogValidationReport = $report->toArray();
        $this->catalogPreviewRows = $validator->previewRows(20);

        Notification::make()
            ->title('اكتمل فحص الكتالوج')
            ->body(count($report->issues) === 0 ? 'لا توجد مشاكل.' : 'عدد المشاكل: '.count($report->issues))
            ->status(count($report->issues) === 0 ? 'success' : 'warning')
            ->send();
    }

    public function executeIdConsistencyCheck(): void
    {
        $report = app(MetaIdConsistencyChecker::class)->check(50);

        $this->idConsistencyReport = [
            'total_orders' => $report->totalOrdersChecked,
            'total_ids' => $report->totalContentIdsChecked,
            'matched' => $report->matchedCount,
            'percentage' => $report->matchPercentage,
            'missing_ids' => $report->missingIds,
            'checked_at' => now()->toDateTimeString(),
        ];

        Notification::make()
            ->title('اكتمل فحص التطابق')
            ->body("نسبة التطابق: {$report->matchPercentage}%")
            ->status($report->matchPercentage >= 90 ? 'success' : 'warning')
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('سجل أحداث الخادم')
            ->description('أحداث Conversions API المرسلة من الخادم. أحداث الشراء تُسجّل دائماً، والأحداث الأخرى تُسجّل عند الفشل فقط.')
            ->query(MetaCapiEvent::query())
            ->columns([
                TextColumn::make('event_name')
                    ->label('الحدث')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Purchase' ? 'success' : 'gray')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sent' => 'تم الإرسال',
                        'failed' => 'فشل',
                        default => 'قيد الانتظار',
                    }),
                TextColumn::make('order_id')
                    ->label('الطلب')
                    ->prefix('#')
                    ->placeholder('—'),
                TextColumn::make('error')
                    ->label('الخطأ')
                    ->limit(60)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->color('danger')
                    ->placeholder('—'),
                TextColumn::make('attempts')
                    ->label('المحاولات')
                    ->alignCenter(),
                TextColumn::make('http_status')
                    ->label('HTTP')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('event_id')
                    ->label('معرّف الحدث')
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('الوقت')
                    ->since()
                    ->dateTimeTooltip('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(['sent' => 'تم الإرسال', 'failed' => 'فشل', 'pending' => 'قيد الانتظار']),
                SelectFilter::make('event_name')
                    ->label('الحدث')
                    ->options(fn (): array => MetaCapiEvent::query()->distinct()->orderBy('event_name')->pluck('event_name', 'event_name')->all()),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('إعادة المحاولة')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('إعادة إرسال حدث الشراء')
                    ->modalDescription('سيُعاد إرسال حدث الشراء بنفس معرّف الحدث، فلن يُحتسب مرتين في ميتا.')
                    ->visible(fn (MetaCapiEvent $record): bool => $record->status === 'failed' && $record->event_name === 'Purchase' && $record->order_id !== null)
                    ->action(function (MetaCapiEvent $record): void {
                        $eventTime = is_numeric($record->payload['event_time'] ?? null)
                            ? (int) $record->payload['event_time']
                            : now()->getTimestamp();

                        SendMetaPurchaseEvent::dispatch((int) $record->order_id, $eventTime);

                        Notification::make()->title('تمت جدولة إعادة الإرسال')->success()->send();
                    }),
                Action::make('viewPayload')
                    ->label('البيانات')
                    ->icon('heroicon-o-code-bracket')
                    ->color('gray')
                    ->modalHeading('بيانات الحدث المرسلة')
                    ->modalDescription('بيانات العميل مُشفّرة (SHA-256) كما تُرسل إلى ميتا. لا يُخزّن رمز الوصول هنا.')
                    ->schema([CodeEntry::make('payload')->hiddenLabel()])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق'),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateIcon('heroicon-o-inbox')
            ->emptyStateHeading('لا توجد أحداث بعد')
            ->emptyStateDescription('ستظهر هنا أحداث الشراء بعد تفعيل Conversions API وإتمام أول طلب.')
            ->paginated([10, 25, 50]);
    }

    protected function getHeaderActions(): array
    {
        return [$this->openSettingsAction()];
    }

    private function configurationSection(): Section
    {
        $config = $this->getConfig();

        return Section::make('إعدادات التكامل')
            ->description('القيم المحفوظة في الإعدادات ← تسويق ميتا.')
            ->icon('heroicon-o-adjustments-horizontal')
            ->schema([
                Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->schema([
                    $this->statusEntry('pixel', 'معرّف البكسل (Pixel ID)', filled($config->pixelId), (string) $config->pixelId, 'غير محدد'),
                    $this->statusEntry('capi', 'Conversions API', $config->capiEnabled, 'مفعّلة', 'معطّلة'),
                    $this->statusEntry('token', 'رمز الوصول (Access Token)', filled($config->accessToken), 'محفوظ ومشفّر', 'غير محفوظ')
                        ->helperText(blank($config->accessToken) ? 'مطلوب لفحص الرمز والحدث التجريبي وإرسال الأحداث من الخادم. أنشئه من Events Manager ← الإعدادات ← Conversions API.' : null),
                    $this->statusEntry('testCode', 'رمز أحداث الاختبار', filled($config->testEventCode), (string) $config->testEventCode, 'غير محدد', missingColor: 'gray', okColor: 'warning')
                        ->helperText(filled($config->testEventCode) ? 'كل الأحداث تذهب إلى Test Events. احذفه بعد انتهاء الاختبار.' : null),
                    $this->statusEntry('catalog', 'كتالوج المنتجات', $config->catalogFeedAvailable(), 'مفعّل', 'معطّل', missingColor: 'gray'),
                    $this->statusEntry('debug', 'وضع التصحيح في المتجر', $config->debugMode, 'مفعّل', 'متوقف', missingColor: 'gray', okColor: 'warning'),
                ]),
            ]);
    }

    private function connectionTests(): Grid
    {
        return Grid::make(1)->schema([
            Section::make('فحص رمز الوصول')
                ->description('يتحقق من Pixel ID ورمز الوصول عبر Graph API.')
                ->icon('heroicon-o-shield-check')
                ->headerActions([$this->verifyCredentialsAction()])
                ->schema(fn (): array => $this->resultEntries(
                    $this->tokenCheckResult,
                    'token',
                    successText: fn (array $r): string => mb_trim(($r['name'] ?? '').' ('.($r['pixel_id'] ?? '').')'),
                    successLabel: 'البكسل',
                )),
            Section::make('حدث تجريبي')
                ->description('يرسل PageView واحداً عبر Conversions API ليظهر في Events Manager ← Test Events.')
                ->icon('heroicon-o-paper-airplane')
                ->headerActions([$this->sendTestEventAction()])
                ->schema(fn (): array => $this->resultEntries(
                    $this->testEventResult,
                    'testEvent',
                    successText: fn (array $r): string => 'استلمت ميتا '.($r['events_received'] ?? 0).' حدث — راجع Events Manager ← Test Events',
                    successLabel: 'النتيجة',
                )),
        ]);
    }

    /**
     * @return list<Component>
     */
    private function catalogComponents(): array
    {
        if (! $this->getConfig()->catalogFeedAvailable()) {
            return [
                EmptyState::make('كتالوج المنتجات غير مفعل حالياً')
                    ->description('فعّل رابط تصدير الكتالوج من الإعدادات ← تسويق ميتا، ثم عُد إلى هنا لنسخ الروابط وفحص المنتجات.')
                    ->icon('heroicon-o-shopping-bag')
                    ->footer([$this->openSettingsAction()]),
            ];
        }

        $token = (string) $this->getConfig()->catalogFeedToken;

        return [
            Section::make('روابط الكتالوج')
                ->description('في Commerce Manager ← Catalog ← Data sources: أضف الرابط الأساسي كـ Data feed مجدول، ورابط العربية كـ Language override feed. يجب أن يكون الموقع على رابط عام (HTTPS).')
                ->icon('heroicon-o-link')
                ->schema([
                    $this->urlEntry('feedCsv', 'الرابط الأساسي (CSV)', route('meta.catalog.feed', ['token' => $token, 'format' => 'csv'])),
                    $this->urlEntry('feedXml', 'بديل XML (RSS)', route('meta.catalog.feed', ['token' => $token, 'format' => 'xml'])),
                    $this->urlEntry('feedAr', 'ترجمة العربية (Language override)', route('meta.catalog.feed.ar', ['token' => $token])),
                ]),
            Section::make('فحص الكتالوج')
                ->description('يفحص الصفوف التي سيُرسلها الرابط فعلياً إلى ميتا.')
                ->icon('heroicon-o-clipboard-document-check')
                ->headerActions([$this->runCatalogCheckAction()])
                ->schema(fn (): array => $this->catalogReportComponents()),
        ];
    }

    /**
     * @return list<Component>
     */
    private function catalogReportComponents(): array
    {
        $report = $this->catalogValidationReport;
        if ($report === null) {
            return [Text::make('لم يُشغّل الفحص بعد. اضغط «تشغيل فحص الكتالوج».')->color('gray')];
        }

        $issues = collect($report['issues']);
        $summary = $issues
            ->groupBy('problem')
            ->map(fn ($group, string $problem): array => [
                'problem' => self::PROBLEM_LABELS[$problem] ?? $problem,
                'field' => (string) $group->first()['field'],
                'count' => $group->count(),
                'examples' => $group->pluck('id')->take(6)->implode('، ').($group->count() > 6 ? ' …' : ''),
            ])
            ->values()
            ->all();

        return [
            Grid::make(['default' => 2, 'lg' => 4])->schema([
                $this->statEntry('statProducts', 'المنتجات', $report['total_products']),
                $this->statEntry('statRows', 'صفوف الكتالوج', $report['total_rows'], 'success'),
                $this->statEntry('statSkipped', 'صفوف مستبعدة', $report['rows_skipped'], $report['rows_skipped'] > 0 ? 'warning' : 'gray'),
                $this->statEntry('statIssues', 'المشاكل', $issues->count(), $issues->isEmpty() ? 'success' : 'danger'),
            ]),
            $issues->isEmpty()
                ? Text::make('لا توجد مشاكل — الكتالوج جاهز للرفع.')->color('success')
                : RepeatableEntry::make('issueSummary')
                    ->label('ملخص المشاكل')
                    ->state($summary)
                    ->table([
                        TableColumn::make('المشكلة'),
                        TableColumn::make('الحقل'),
                        TableColumn::make('العدد'),
                        TableColumn::make('أمثلة على المعرّفات'),
                    ])
                    ->schema([
                        TextEntry::make('problem'),
                        TextEntry::make('field')->badge()->color('gray'),
                        TextEntry::make('count')->badge()->color('danger'),
                        TextEntry::make('examples')->fontFamily(FontFamily::Mono)->color('gray'),
                    ]),
            Section::make('معاينة أول 20 صف من الرابط')
                ->collapsible()
                ->collapsed()
                ->compact()
                ->schema([
                    RepeatableEntry::make('previewRows')
                        ->hiddenLabel()
                        ->state($this->catalogPreviewRows ?? [])
                        ->table([
                            TableColumn::make('المعرّف'),
                            TableColumn::make('العنوان'),
                            TableColumn::make('السعر'),
                            TableColumn::make('سعر الخصم'),
                            TableColumn::make('التوفر'),
                        ])
                        ->schema([
                            TextEntry::make('id')->fontFamily(FontFamily::Mono),
                            TextEntry::make('title'),
                            TextEntry::make('price'),
                            TextEntry::make('sale_price')->placeholder('—'),
                            TextEntry::make('availability')
                                ->badge()
                                ->color(fn (string $state): string => $state === 'in stock' ? 'success' : 'gray'),
                        ]),
                ]),
        ];
    }

    private function idConsistencySection(): Section
    {
        return Section::make('تطابق معرّفات المنتجات')
            ->description('يقارن معرّفات المنتجات في آخر 50 طلباً من المتجر بمعرّفات الكتالوج. التطابق الكامل شرط لعمل الإعلانات الديناميكية.')
            ->icon('heroicon-o-arrows-right-left')
            ->headerActions([$this->checkIdConsistencyAction()])
            ->schema(function (): array {
                $report = $this->idConsistencyReport;
                if ($report === null) {
                    return [Text::make('لم يُشغّل الفحص بعد. اضغط «تشغيل فحص التطابق».')->color('gray')];
                }

                $percentage = (float) $report['percentage'];

                return [
                    Grid::make(['default' => 2, 'lg' => 4])->schema([
                        $this->statEntry('idPercentage', 'نسبة التطابق', $percentage.'%', $percentage >= 90 ? 'success' : 'danger'),
                        $this->statEntry('idOrders', 'الطلبات المفحوصة', $report['total_orders']),
                        $this->statEntry('idTotal', 'عناصر الطلبات', $report['total_ids']),
                        $this->statEntry('idMatched', 'موجودة في الكتالوج', $report['matched'], 'success'),
                    ]),
                    $report['missing_ids'] === []
                        ? Text::make('كل المعرّفات موجودة في الكتالوج.')->color('success')
                        : TextEntry::make('missingIds')
                            ->label('معرّفات غير موجودة في الكتالوج (منتج بلا صورة أو محذوف)')
                            ->state($report['missing_ids'])
                            ->badge()
                            ->color('danger')
                            ->fontFamily(FontFamily::Mono),
                ];
            });
    }

    /**
     * @param  array<string, mixed>|null  $result
     * @param  Closure(array<string, mixed>): string  $successText
     * @return list<Component>
     */
    private function resultEntries(?array $result, string $key, Closure $successText, string $successLabel): array
    {
        if ($result === null) {
            $missing = $this->missingCredentials();

            return [$missing === null
                ? Text::make('لم يُشغّل بعد.')->color('gray')
                : Text::make($missing)->color('warning')];
        }

        return [
            TextEntry::make("{$key}Result")
                ->label($result['successful'] ? $successLabel : 'الخطأ')
                ->state($result['successful'] ? $successText($result) : ($result['error'] ?? 'خطأ غير معروف'))
                ->icon($result['successful'] ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                ->color($result['successful'] ? 'success' : 'danger'),
            Grid::make(2)->schema([
                TextEntry::make("{$key}Trace")
                    ->label('fbtrace_id')
                    ->state($result['fbtrace_id'] ?? null)
                    ->placeholder('—')
                    ->fontFamily(FontFamily::Mono)
                    ->copyable(),
                TextEntry::make("{$key}At")
                    ->label('وقت التنفيذ')
                    ->state($result['checked_at'] ?? $result['sent_at'] ?? null),
            ]),
        ];
    }

    private function statusEntry(string $key, string $label, bool $ok, string $okText, string $missingText, string $missingColor = 'danger', string $okColor = 'success'): TextEntry
    {
        return TextEntry::make($key)
            ->label($label)
            ->state($ok ? $okText : $missingText)
            ->badge()
            ->icon($ok ? 'heroicon-m-check-circle' : 'heroicon-m-minus-circle')
            ->color($ok ? $okColor : $missingColor);
    }

    private function statEntry(string $key, string $label, int|string $value, string $color = 'gray'): TextEntry
    {
        return TextEntry::make($key)
            ->label($label)
            ->state((string) $value)
            ->size('lg')
            ->weight('bold')
            ->color($color);
    }

    private function urlEntry(string $key, string $label, string $url): TextEntry
    {
        return TextEntry::make($key)
            ->label($label)
            ->state($url)
            ->fontFamily(FontFamily::Mono)
            ->color('gray')
            ->copyable()
            ->copyMessage('تم نسخ الرابط')
            ->icon('heroicon-o-clipboard-document')
            ->iconPosition('after');
    }
}
