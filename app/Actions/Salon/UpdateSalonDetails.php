<?php

namespace App\Actions\Salon;

use App\Models\Salon;

class UpdateSalonDetails
{
    /**
     * Profile and location fields. Only attributes listed as fillable on
     * Salon can change here; slug, status and trial never do.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Salon $salon, array $attributes): Salon
    {
        $salon->fill($attributes)->save();

        return $salon;
    }
}
