<?php

namespace App\Services;

/**
 * Codes à usage unique (TOTP, RFC 6238) compatibles Google Authenticator, Microsoft Authenticator, etc.
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function nouveauSecret(int $longueur = 32): string
    {
        $s = '';
        for ($i = 0; $i < $longueur; $i++) {
            $s .= self::ALPHABET[random_int(0, 31)];
        }

        return $s;
    }

    public static function verifier(string $secret, string $code, int $fenetre = 1, ?int $instant = null): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }
        $pas = intdiv($instant ?? time(), 30);
        for ($i = -$fenetre; $i <= $fenetre; $i++) {
            if (hash_equals(self::code($secret, $pas + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function code(string $secret, int $pas): string
    {
        $cle = self::base32($secret);
        $hash = hash_hmac('sha1', pack('N*', 0, $pas), $cle, true);
        $offset = ord($hash[19]) & 0x0F;
        $valeur = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($valeur % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public static function urlOtpauth(string $secret, string $compte): string
    {
        return 'otpauth://totp/'.rawurlencode('FEGGU:'.$compte).'?secret='.$secret.'&issuer=FEGGU&digits=6&period=30';
    }

    private static function base32(string $s): string
    {
        $bits = '';
        foreach (str_split(strtoupper($s)) as $c) {
            $v = strpos(self::ALPHABET, $c);
            if ($v !== false) {
                $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
            }
        }
        $octets = '';
        foreach (str_split($bits, 8) as $o) {
            if (strlen($o) === 8) {
                $octets .= chr(bindec($o));
            }
        }

        return $octets;
    }
}
