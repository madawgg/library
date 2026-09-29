<?php

namespace App\Rules;

use App\Services\IsbnService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value must be a valid ISBN-10 or ISBN-13 (hyphens and spaces allowed).
 */
class ValidIsbn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! app(IsbnService::class)->isValid($value)) {
            $fail(__('El ISBN no es válido. Debe ser un ISBN-10 o ISBN-13.'));
        }
    }
}
