@php
    use App\Services\SettingService;
    use App\Enums\SettingKey;
    use Illuminate\Support\Facades\Storage;

    $settingService = app(SettingService::class);
    $isTenant = tenant() !== null;

    $siteName = $isTenant ? $settingService->get(SettingKey::SITE_NAME, config('app.name', 'Laravel')) : config('app.name', 'Laravel');
    $siteDescription = $isTenant ? $settingService->get(SettingKey::SITE_DESCRIPTION) : null;
    $favicon = $isTenant ? $settingService->get(SettingKey::SITE_FAVICON) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ $siteName }}</title>
    @if($siteDescription)
        <meta name="description" content="{{ $siteDescription }}">
    @endif
    @if($favicon)
        <link rel="icon" type="image/x-icon" href="{{ '/storage/'.$favicon }}">
    @endif

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <style>
        .loading-container {
            height: 100vh;
            width: 100vw;
            overflow: hidden;
        }

        .logo-svg {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 100%;
            background-color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .logo-svg img {
            height: 100%;
            display: block;
            object-fit: contain;
            max-width: 100%;
        }

        .loading-container.disabled {
            height: inherit;
            width: inherit;
            overflow: inherit;
        }

        .loading-container.disabled .logo-svg {
            display: none;
        }
    </style>
    <!-- Scripts -->
    @routes
    @viteReactRefresh
    @vite(['resources/js/app.tsx', "resources/js/themes/default/pages/{$page['component']}.tsx"])
    <link rel="stylesheet" href="/theme.css">
    @inertiaHead
</head>

<body class="font-sans antialiased">
    <div class="loading-container" id="loading_container">
        <div class="logo-svg">
            <img id="section-logo-animation" src={{ "/storage/" . app(SettingService::class)->get(SettingKey::SVG_LOGO)}} alt="Logo" class="img-fade-in">
        </div>
    </div>
    @inertia
</body>

</html>
