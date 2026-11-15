<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout\src\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TrackOrderRequest extends FormRequest
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
            'order_number' => ['required', 'string'],
            'token' => ['required_without:phone', 'nullable', 'string'],
            'phone' => ['required_without:token', 'nullable', 'string'],
            'phone_country_code' => ['nullable', 'string'],
        ];
    }
}
