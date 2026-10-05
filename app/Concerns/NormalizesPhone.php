<?php

namespace App\Concerns;

use Illuminate\Foundation\Http\FormRequest;

/**
 * People type phones as "+51 999 888-777"; keep only "+" and digits so the
 * value validates and works in wa.me links.
 *
 * @mixin FormRequest
 */
trait NormalizesPhone
{
    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (is_string($phone)) {
            $this->merge(['phone' => preg_replace('/[\s\-().]/', '', $phone) ?: null]);
        }
    }
}
