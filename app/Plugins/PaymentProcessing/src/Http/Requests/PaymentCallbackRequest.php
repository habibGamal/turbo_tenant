<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PaymentCallbackRequest extends FormRequest
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
            'status' => ['nullable', 'string'],
        ];
    }
}
