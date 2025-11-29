<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

final class HomeController extends Controller
{
    public function index(): Response
    {
        // Get categories with their products
        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name', 'description','image']);

        $sections = \App\Models\Section::query()
            ->with([
                'products' => function ($query) {
                    $query->where('is_active', true)
                        ->with('category:id,name');
                }
            ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Transform sections to match frontend expectations if needed,
        // or just pass them as is and handle in frontend.
        // The Section model has 'products' relation.

        $heroSlides = \App\Models\HeroSlider::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('HomePage', [
            'categories' => $categories,
            'sections' => $sections,
            'heroSlides' => $heroSlides,
            'tenant' => [
                'name' => tenant('id'),
                'theme' => 'default',
            ],
        ]);
    }
}
