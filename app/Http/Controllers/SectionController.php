<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Section;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SectionController extends Controller
{
    public function show(Request $request, Section $section): Response
    {
        $products = $section->products()
            ->where('is_active', true)
            ->cursorPaginate(12);

        if ($request->wantsJson()) {
            return Inertia::render('Section/Show', [
                'section' => $section,
                'products' => $products,
            ]);
        }

        return Inertia::render('Section/Show', [
            'section' => $section,
            'products' => $products,
        ]);
    }
}
