<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Plugins\CartEngine\src\Models\CartItemExtra>
 */
final class CartItemExtraFactory extends Factory
{
    protected $model = \App\Plugins\CartEngine\src\Models\CartItemExtra::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cart_item_id' => CartItem::factory(),
            'extra_option_item_id' => ExtraOptionItem::factory(),
        ];
    }
}
