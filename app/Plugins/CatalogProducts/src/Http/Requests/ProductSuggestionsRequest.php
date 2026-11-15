<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProductSuggestionsRequest extends FormRequest
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
            'q' => ['required', 'string', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('q') && $this->has('query')) {
            $this->merge(['q' => $this->input('query')]);
        }
    }
}
