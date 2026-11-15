<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProductsByIdsRequest extends FormRequest
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
            'ids' => ['required', 'array', 'max:50'],
            'ids.*' => ['integer'],
        ];
    }
}
