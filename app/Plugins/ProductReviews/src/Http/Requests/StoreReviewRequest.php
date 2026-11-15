<?php

declare(strict_types=1);

namespace App\Plugins\ProductReviews\src\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreReviewRequest extends FormRequest
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
        $isAuthenticated = $this->user() !== null;

        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:2000'],
            'guest_name' => [$isAuthenticated ? 'nullable' : 'required', 'string', 'max:255'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:2048'],
        ];
    }
}
