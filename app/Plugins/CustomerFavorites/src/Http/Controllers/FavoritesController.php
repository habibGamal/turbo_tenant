<?php

declare(strict_types=1);

namespace App\Plugins\CustomerFavorites\src\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class FavoritesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Favorites');
    }
}
