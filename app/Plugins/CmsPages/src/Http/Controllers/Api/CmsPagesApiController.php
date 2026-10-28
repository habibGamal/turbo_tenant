<?php

declare(strict_types=1);

namespace App\Plugins\CmsPages\src\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Plugins\CmsPages\src\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CmsPagesApiController extends Controller
{
    /**
     * Display a listing of active pages.
     */
    public function index(Request $request): JsonResponse
    {
        $pages = Page::query()
            ->where('is_active', true)
            ->select(['id', 'title', 'title_ar', 'slug', 'created_at', 'updated_at'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $pages,
        ]);
    }

    /**
     * Display the specified page by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $page = Page::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $page) {
            return response()->json([
                'status' => 'error',
                'message' => 'Page not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $page,
        ]);
    }
}
