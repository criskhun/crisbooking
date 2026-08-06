<?php

namespace App\Support;

final class PhoneNumber
{
    public static function normalize(string $number): ?string
    {
        $number = preg_replace('/[^0-9+]/', '', trim($number));

        if (str_starts_with($number, '09')) {
            $number = '+63'.substr($number, 1);
        } elseif (str_starts_with($number, '639')) {
            $number = '+'.$number;
        } elseif (! str_starts_with($number, '+')) {
            return null;
        }

        return preg_match('/^\+[1-9][0-9]{7,14}$/', $number) ? $number : null;
    }
}
