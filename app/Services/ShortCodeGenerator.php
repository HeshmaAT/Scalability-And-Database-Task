<?php

namespace App\Services;

class ShortCodeGenerator
{
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public function generate(string $destination): string
    {
        do {
            $digest = hash('sha256', random_bytes(32) . $destination, true);
            $code = '';

            foreach (str_split($digest) as $byte) {
                $value = ord($byte);

                if ($value >= 248) {
                    continue;
                }

                $code .= self::ALPHABET[$value % 62];

                if (strlen($code) === 10) {
                    return $code;
                }
            }
        } while (true);
    }
}
