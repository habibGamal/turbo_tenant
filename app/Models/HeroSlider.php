<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class HeroSlider extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'title_ar',
        'subtitle',
        'subtitle_ar',
        'badge',
        'badge_ar',
        'cta_text',
        'cta_text_ar',
        'cta_link',
        'secondary_cta_text',
        'secondary_cta_text_ar',
        'secondary_cta_link',
        'image',
        'gradient',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
