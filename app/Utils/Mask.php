<?php

declare(strict_types=1);

namespace App\Utils;

use Illuminate\Support\Str;

final class Mask
{
    /**
     * Enmascara un nombre completo conservando iniciales.
     * Ejemplo: "Juan Alberto Perez" -> "J*** A*** P***"
     */
    public static function name(?string $name): string
    {
        if (blank($name)) {
            return 'Anónimo';
        }

        $parts = explode(' ', $name);
        $maskedParts = array_map(function ($part) {
            if (mb_strlen($part) <= 1) {
                return $part;
            }
            return mb_substr($part, 0, 1) . str_repeat('*', 3);
        }, $parts);

        return implode(' ', $maskedParts);
    }
}
