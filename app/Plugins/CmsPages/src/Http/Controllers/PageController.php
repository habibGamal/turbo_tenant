<?php

declare(strict_types=1);

namespace App\Plugins\CmsPages\src\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Plugins\CmsPages\src\Models\Page;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function show(string $slug): Response
    {
        $page = Page::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return Inertia::render('Page', [
            'page' => $page,
        ]);
    }
}
