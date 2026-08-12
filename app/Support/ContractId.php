<?php

namespace App\Support;

/**
 * Canonical loan/contract account identifier.
 *
 * E-Banker (VGCBS) pads account numbers to 15 characters with leading zeros
 * ("000104430000062"); MAIIC's monthly loan tape carries the same account
 * unpadded ("104430000062"). Not one of the 3,609 accounts on the tape
 * begins with a zero, so stripping the padding is unambiguous and the
 * unpadded form is the canonical one.
 *
 * Without this, every Extract A/B/C row is held at import with "loan account
 * is not present in the imported loan book" — the join simply never matches.
 *
 * Normalisation is deliberately conservative: it removes padding and
 * whitespace/spreadsheet debris only. Suffixes the tape carries in its own
 * right (e.g. "104450000037_cleared") are preserved, because they identify a
 * different record rather than a different formatting of the same one.
 */
class ContractId
{
    /** Whitespace and zero-width characters spreadsheet exports leave behind. */
    private const DEBRIS = [
        "\xC2\xA0", "\xA0", "\xE2\x80\x8B", "\xE2\x80\x8C",
        "\t", "\n", "\r", ' ', '"', "'",
    ];

    /**
     * @param mixed $value raw identifier from a file, request or database
     * @return string|null canonical form, or null when there is no identifier
     */
    public static function normalise($value): ?string
    {
        if ($value === null || is_bool($value)) {
            return null;
        }

        $id = is_float($value) || is_int($value)
            ? self::fromNumber($value)
            : (string) $value;

        $id = str_replace(self::DEBRIS, '', $id);
        // Account numbers read out of a numeric cell can arrive as "1.0443E+11".
        if (preg_match('/^\d+(\.\d+)?[eE][+-]?\d+$/', $id)) {
            $id = self::fromNumber((float) $id);
        }

        if ($id === '') {
            return null;
        }

        $stripped = ltrim($id, '0');

        // An all-zero identifier is not padding — keep a single zero rather
        // than returning an empty string that would silently drop the row.
        return $stripped === '' ? '0' : $stripped;
    }

    /**
     * True when both values refer to the same account once padding differences
     * are removed. Use for reconciliation and orphan reporting, never as a
     * substitute for storing the canonical form.
     */
    public static function matches($a, $b): bool
    {
        $left = self::normalise($a);

        return $left !== null && $left === self::normalise($b);
    }

    /** Render a numeric cell as a plain digit string, never in exponent form. */
    private static function fromNumber(float|int $value): string
    {
        return number_format($value, 0, '.', '');
    }
}
