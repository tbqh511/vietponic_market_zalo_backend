<?php

namespace App\Support;

use App\Models\Customer;

class AffiliateCodeGenerator
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // exclude 0/O/1/I
    private const DEFAULT_LENGTH = 8;
    private const FALLBACK_LENGTH = 10;
    private const MAX_ATTEMPTS = 5;

    public static function generate(): string
    {
        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $code = self::randomCode(self::DEFAULT_LENGTH);
            if (!Customer::where('affiliate_code', $code)->exists()) {
                return $code;
            }
        }
        do {
            $code = self::randomCode(self::FALLBACK_LENGTH);
        } while (Customer::where('affiliate_code', $code)->exists());
        return $code;
    }

    private static function randomCode(int $length): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }
}
