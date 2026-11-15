<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RelayEventRequest extends FormRequest
{
    public const array ALLOWED_EVENTS = [
        'PageView',
        'ViewContent',
        'Search',
        'AddToWishlist',
        'AddToCart',
        'InitiateCheckout',
        'AddPaymentInfo',
        'CompleteRegistration',
    ];

    public const array ALLOWED_CUSTOM_DATA_KEYS = [
        'value',
        'currency',
        'content_ids',
        'contents',
        'content_type',
        'content_name',
        'content_category',
        'num_items',
        'search_string',
        'status',
    ];

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
            'event_name' => ['required', 'string', Rule::in(self::ALLOWED_EVENTS)],
            'event_id' => ['required', 'string', 'max:100'],
            'event_source_url' => ['required', 'url', 'max:2000'],
            'custom_data' => ['sometimes', 'array'],
            'custom_data.value' => ['sometimes', 'numeric'],
            'custom_data.currency' => ['sometimes', 'string', 'max:10'],
            'custom_data.content_ids' => ['sometimes', 'array', 'max:100'],
            'custom_data.content_ids.*' => ['string', 'regex:/^\d+(-\d+)?$/'],
            'custom_data.contents' => ['sometimes', 'array', 'max:100'],
            'custom_data.contents.*.id' => ['required', 'string', 'regex:/^\d+(-\d+)?$/'],
            'custom_data.contents.*.quantity' => ['required', 'numeric', 'min:0'],
            'custom_data.contents.*.item_price' => ['sometimes', 'numeric', 'min:0'],
            'custom_data.content_type' => ['sometimes', 'string', Rule::in(['product', 'product_group'])],
            'custom_data.content_name' => ['sometimes', 'string', 'max:255'],
            'custom_data.content_category' => ['sometimes', 'string', 'max:255'],
            // Sell-by-weight items can add fractional quantities.
            'custom_data.num_items' => ['sometimes', 'numeric', 'min:0'],
            'custom_data.search_string' => ['sometimes', 'string', 'max:200'],
            'custom_data.status' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Only validated keys are relayed, including inside `contents` items.
     *
     * @return array<string, mixed>
     */
    public function customData(): array
    {
        $data = $this->validated('custom_data', []);

        return is_array($data) ? array_intersect_key($data, array_flip(self::ALLOWED_CUSTOM_DATA_KEYS)) : [];
    }
}
