<?php

namespace App\Support;

class Base58
{
    private const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public static function encode(string $bytes): string
    {
        $zeros = strspn($bytes, "\0");
        $digits = [];
        foreach (unpack('C*', substr($bytes, $zeros)) ?: [] as $byte) {
            $carry = $byte;
            foreach ($digits as &$digit) {
                $carry += $digit * 256;
                $digit = $carry % 58;
                $carry = intdiv($carry, 58);
            }
            unset($digit);
            while ($carry > 0) { $digits[] = $carry % 58; $carry = intdiv($carry, 58); }
        }
        return str_repeat('1', $zeros).implode('', array_map(fn ($digit) => self::ALPHABET[$digit], array_reverse($digits)));
    }

    public static function decode(string $value): ?string
    {
        if ($value === '' || strlen($value) > 100) { return null; }
        $zeros = strspn($value, '1');
        $bytes = [];
        foreach (str_split(substr($value, $zeros)) as $character) {
            $carry = strpos(self::ALPHABET, $character);
            if ($carry === false) { return null; }
            foreach ($bytes as &$byte) {
                $carry += $byte * 58;
                $byte = $carry % 256;
                $carry = intdiv($carry, 256);
            }
            unset($byte);
            while ($carry > 0) { $bytes[] = $carry % 256; $carry = intdiv($carry, 256); }
        }
        return str_repeat("\0", $zeros).implode('', array_map('chr', array_reverse($bytes)));
    }

    public static function address(?string $value): bool
    {
        return $value !== null && strlen(self::decode($value) ?? '') === 32 && $value !== str_repeat('1', 32);
    }
}
