<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProductIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'search' => ['sometimes', 'string', 'max:100'],
            'category_ids' => ['sometimes', 'array', 'max:50'],
            'category_ids.*' => ['integer'],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0'],
            'sort_by' => ['sometimes', 'string', Rule::in(['name', 'price', 'created_at'])],
            'sort_order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}
