<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicCardUrl
{
    public static function format(?string $value, string $field = 'public_card_url'): string
    {
        if ($value === null) {
            throw ValidationException::withMessages([
                $field => "L'URL publique ne peut pas être vide.",
            ]);
        }

        $slug = Str::slug($value);

        if ($slug === '') {
            throw ValidationException::withMessages([
                $field => "L'URL publique doit contenir au moins un caractère alpha-numérique.",
            ]);
        }

        return $slug;
    }
}
