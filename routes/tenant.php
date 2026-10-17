<?php

declare(strict_types=1);

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes (Platform Host Shell)
|--------------------------------------------------------------------------
|
| Plugin domain routes are encapsulated in each plugin's routes/tenant.php
| loaded via their respective ServiceProviders.
|
*/

Route::get('/storage/{path}', function (Request $request, string $path) {
    $file = Storage::path($path);

    if (! file_exists($file)) {
        abort(404);
    }

    if ($request->query->count() === 0) {
        $mimeType = mime_content_type($file);
        if (ob_get_length()) {
            ob_end_clean();
        }

        return response()->file($file, [
            'Content-Type' => $mimeType,
            'Content-Length' => filesize($file),
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    $cacheKey = md5($path.serialize($request->all()));
    $extension = pathinfo($file, PATHINFO_EXTENSION);
    $cacheFilename = $cacheKey.'.'.$extension;
    $cachePath = 'cache/'.$cacheFilename;
    $fullCachePath = Storage::path($cachePath).'.webp';

    if (! Storage::exists('cache')) {
        Storage::makeDirectory('cache');
    }

    if (! file_exists($fullCachePath)) {
        try {
            $image = Spatie\Image\Image::load($file)->optimize();
            if (str_contains($path, 'products')) {
                $image->width(480);
                $image->quality(80);
            } else {
                if ($request->has('w')) {
                    $image->width((int) $request->input('w'));
                }
                if ($request->has('h')) {
                    $image->height((int) $request->input('h'));
                }
                if ($request->has('fit')) {
                    $image->fit(Spatie\Image\Enums\Fit::tryFrom($request->input('fit')) ?? Spatie\Image\Enums\Fit::Contain);
                }
            }
            $image->save($fullCachePath);
        } catch (Exception $e) {
            logger()->error('Image manipulation failed: '.$e->getMessage());

            return response()->file($file);
        }
    }

    $mimeType = mime_content_type($fullCachePath);
    if (ob_get_length()) {
        ob_end_clean();
    }

    return response()->file($fullCachePath, [
        'Content-Type' => $mimeType,
        'Content-Length' => filesize($fullCachePath),
        'Accept-Ranges' => 'bytes',
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->where('path', '.*')->middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
]);

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/manifest.json', function () {
        return response()->file(Storage::path('manifest.json'));
    });

    Route::get('/theme.css', function () {
        return response()->file(Storage::path('theme.css'), [
            'Content-Type' => 'text/css',
        ]);
    });

    Route::get('/', [HomeController::class, 'index'])->name('home');

    // Profile routes (requires authentication)
    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    require __DIR__.'/auth.php';
});
