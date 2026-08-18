<?php
/**
 * ULID — port da lib JS do Gestor desktop (src/js/backend/ulid.js).
 *
 * ULID 26 chars: 10 timestamp (base32) + 16 random (base32).
 * Lexicamente ordenavel. Monotonic (incrementa random quando mesmo ms).
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api\Util;

defined('ABSPATH') || exit;

/**
 * Gerador de ULIDs (Universally Unique Lexicographically Sortable Identifier).
 */
final class Ulid
{
    private const CROCKFORD_BASE32 = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private static ?int $last_time = null;
    private static string $last_random = '';

    /**
     * Gera um novo ULID.
     *
     * @param int|null $timestamp_ms Timestamp em ms (opcional; padrao agora).
     * @return string ULID 26 chars.
     */
    public static function generate(?int $timestamp_ms = null): string
    {
        if ($timestamp_ms === null) {
            $timestamp_ms = (int) floor(microtime(true) * 1000);
        }

        $time_part = self::encode_time($timestamp_ms);

        if (self::$last_time === $timestamp_ms) {
            $random_part = self::increment_random(self::$last_random);
        } else {
            $random_part = self::generate_random();
        }

        self::$last_time = $timestamp_ms;
        self::$last_random = $random_part;

        return $time_part . $random_part;
    }

    /**
     * Valida formato de ULID.
     */
    public static function is_valid(string $ulid): bool
    {
        return (bool) preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $ulid);
    }

    /**
     * Converte ULID pra timestamp em ms.
     */
    public static function to_timestamp(string $ulid): int
    {
        $time_chars = substr($ulid, 0, 10);
        return self::decode_time($time_chars);
    }

    private static function encode_time(int $timestamp_ms): string
    {
        $out = '';
        $value = $timestamp_ms;
        for ($i = 9; $i >= 0; $i--) {
            $mod = $value & 31;
            $out = self::CROCKFORD_BASE32[$mod] . $out;
            $value = (int) (($value - $mod) / 32);
        }
        return $out;
    }

    private static function decode_time(string $time_chars): int
    {
        $value = 0;
        $len = strlen($time_chars);
        for ($i = 0; $i < $len; $i++) {
            $char = $time_chars[$i];
            $idx = strpos(self::CROCKFORD_BASE32, $char);
            if ($idx === false) {
                return 0;
            }
            $value = $value * 32 + $idx;
        }
        return $value;
    }

    private static function generate_random(): string
    {
        // 80 bits de entropia = 16 chars em base32 (cada char = 5 bits).
        // random_bytes(16) gera 128 bits; mascaramos 3 bits por byte (desperdicio OK).
        $bytes = random_bytes(16);
        $out = '';
        for ($i = 0; $i < 16; $i++) {
            $out .= self::CROCKFORD_BASE32[ord($bytes[$i]) & 31];
        }
        return $out;
    }

    private static function increment_random(string $random): string
    {
        $chars = str_split($random);
        for ($i = count($chars) - 1; $i >= 0; $i--) {
            $idx = strpos(self::CROCKFORD_BASE32, $chars[$i]);
            if ($idx === false) {
                $idx = 0;
            }
            if ($idx < 31) {
                $chars[$i] = self::CROCKFORD_BASE32[$idx + 1];
                return implode('', $chars);
            }
            $chars[$i] = '0';
        }
        return implode('', $chars);
    }
}
