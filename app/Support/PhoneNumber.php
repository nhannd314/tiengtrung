<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normalize a Vietnamese phone number to its local form, e.g. "+84 912.345.678" => "0912345678".
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '84') && strlen($digits) === 11) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }
}
