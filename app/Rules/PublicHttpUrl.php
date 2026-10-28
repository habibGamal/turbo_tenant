<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Network\InvalidEndpointException;
use App\Support\Network\PublicEndpointGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PublicHttpUrl implements ValidationRule
{
    public function __construct(
        private readonly PublicEndpointGuard $guard = new PublicEndpointGuard()
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_trim($value) === '') {
            return;
        }

        $trimmed = mb_trim($value);
        if (! str_contains($trimmed, '://')) {
            $fail('The link must be a valid http or https URL.');

            return;
        }

        try {
            $this->guard->guard($trimmed);
        } catch (InvalidEndpointException $e) {
            $fail($e->getMessage());
        }
    }
}
