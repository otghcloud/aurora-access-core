<?php

namespace OTGH\AccessControl\Core\Support;

class SignalValueMapper
{
    /**
     * @var array<int,string>
     */
    private const TRUE_TOKENS = [
        '1',
        'true',
        'on',
        'yes',
        'high',
    ];

    /**
     * @var array<int,string>
     */
    private const FALSE_TOKENS = [
        '0',
        'false',
        'off',
        'no',
        'low',
    ];

    public static function toCanonicalBool(mixed $wireValue, bool $signalReversed = false): ?bool
    {
        $parsed = self::parseBoolLike($wireValue);

        if ($parsed === null) {
            return null;
        }

        return $signalReversed ? ! $parsed : $parsed;
    }

    public static function fromCanonicalBool(
        bool $canonicalValue,
        bool $signalReversed = false,
        mixed $wireOnValue = 1,
        mixed $wireOffValue = 0,
    ): mixed {
        $effectiveValue = $signalReversed ? ! $canonicalValue : $canonicalValue;

        return $effectiveValue ? $wireOnValue : $wireOffValue;
    }

    private static function parseBoolLike(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            if ((float) $value === 1.0) {
                return true;
            }

            if ((float) $value === 0.0) {
                return false;
            }

            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if (in_array($normalized, self::TRUE_TOKENS, true)) {
            return true;
        }

        if (in_array($normalized, self::FALSE_TOKENS, true)) {
            return false;
        }

        return null;
    }
}
