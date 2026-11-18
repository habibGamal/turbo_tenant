@php
    use App\Plugins\StoreSettings\src\Services\SettingService;
    use App\Enums\SettingKey;
    use App\Plugins\StoreSettings\src\Theme\ThemeCssBuilder;

    $isTenant = tenant() !== null;
    $settingService = ($isTenant && app()->bound(SettingService::class)) ? app(SettingService::class) : null;

    $siteName = $settingService ? $settingService->get(SettingKey::SITE_NAME, config('app.name', 'Laravel')) : config('app.name', 'Laravel');
    $siteDescription = $settingService ? $settingService->get(SettingKey::SITE_DESCRIPTION) : null;
    $favicon = $settingService ? $settingService->get(SettingKey::SITE_FAVICON) : null;
    $bootstrap = null;
    if ($isTenant) {
        try {
            $bootstrap = app(\App\Http\Controllers\Api\BootstrapController::class)(request())->getData(true);
        } catch (\Throwable $e) {
            report($e);
        }
    }
    $themeCss = ($isTenant && $settingService && app()->bound(ThemeCssBuilder::class))
        ? app(ThemeCssBuilder::class)->build($settingService->get(SettingKey::THEME, []))
        : '';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $isArabicLocale = str_starts_with(app()->getLocale(), 'ar');
        $primarySubset = $isArabicLocale ? 'arabic' : 'latin';
    @endphp
    <link rel="preload" href="{{ Vite::asset('resources/fonts/tajawal/tajawal-'.$primarySubset.'-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ Vite::asset('resources/fonts/tajawal/tajawal-'.$primarySubset.'-700-normal.woff2') }}" as="font" type="font/woff2" crossorigin>

    <title>{{ $siteName }}</title>
    @if($siteDescription)
        <meta name="description" content="{{ $siteDescription }}">
    @endif
    @if($favicon)
        <link rel="icon" type="image/x-icon" href="{{ '/storage/'.$favicon }}">
    @endif

    @if($bootstrap)
        <script>window.__TENANT_BOOTSTRAP__ = {{ \Illuminate\Support\Js::from($bootstrap) }};</script>
    @endif
    <!-- Scripts -->
    @if(Vite::isRunningHot())
        @vite('resources/css/app.css')
    @else
        <style id="storefront-styles">{!! str_ireplace('</style', '<\\/style', Vite::content('resources/css/app.css')) !!}</style>
    @endif
    @viteReactRefresh
    @vite(['resources/js/app.tsx'])
    @if($themeCss !== '')
        <style id="tenant-theme">{!! str_ireplace('</style', '<\\/style', $themeCss) !!}</style>
    @endif
</head>

<body class="font-sans antialiased">
    <div id="app"></div>
</body>

</html>
