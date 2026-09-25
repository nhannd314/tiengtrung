<?php

namespace App\Support;

class LoginIdentifier
{
    /**
     * Turn a user-typed email or phone number into a credentials lookup, e.g. ['phone' => '0912345678'].
     *
     * @return array{email: string}|array{phone: string|null}
     */
    public static function credentials(string $login): array
    {
        $login = trim($login);

        return filter_var($login, FILTER_VALIDATE_EMAIL)
            ? ['email' => mb_strtolower($login)]
            : ['phone' => PhoneNumber::normalize($login)];
    }
}
