<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Http\Requests;

use App\Core\Contracts\SettingsRepository;
use App\Plugins\LocationsDelivery\src\Models\Address;
use Illuminate\Foundation\Http\FormRequest;

final class AddressRequest extends FormRequest
{
    public const EGYPTIAN_PHONE_REGEX = '/^\+20[0-9]{10}$/';

    public function authorize(): bool
    {
        $address = $this->route('address');

        if ($address instanceof Address) {
            return $this->user()?->can('update', $address) ?? false;
        }

        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $simplified = app()->bound(SettingsRepository::class)
            && filter_var(app(SettingsRepository::class)->get('simplified_address_enabled', false), FILTER_VALIDATE_BOOLEAN);

        return [
            'area_id' => 'required|integer|exists:areas,id',
            'phone_number' => [
                'required',
                'string',
                'regex:'.self::EGYPTIAN_PHONE_REGEX,
            ],
            'street' => 'required|string|max:255',
            'building' => $simplified ? 'nullable|string|max:255' : 'required|string|max:255',
            'floor' => $simplified ? 'nullable|string|max:50' : 'required|string|max:50',
            'apartment' => $simplified ? 'nullable|string|max:50' : 'required|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'is_default' => 'nullable|boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_number.regex' => 'رقم الهاتف يجب أن يكون رقم مصري صحيح.',
        ];
    }
}
