<?php
/**
 * Name utility helper.
 *
 * Primary method used by your registration flow:
 *   Name::normalizeDisplayName($raw)
 *
 * Goals:
 *  - Trim leading/trailing whitespace.
 *  - Collapse multiple internal spaces to single spaces.
 *  - Remove illegal characters (digits, most symbols) but keep letters, hyphen, apostrophe, and periods.
 *  - Normalize capitalization:
 *      * First letter of each main token uppercase, rest lowercase.
 *      * Preserve common lowercase particles in surnames (de, del, dela, de la, la, van, von, da, dos, das, di, du, le, el, y, bin, binti)
 *        UNLESS they are the first token.
 *  - Middle initial rules:
 *      * If a middle token is a single letter (with or without a period), convert to "X."
 *  - If result becomes empty, return empty string (caller already handles the validation error).
 *
 * This is intentionally lightweight; you can expand exceptions list as needed.
 */
class Name
{
    /**
     * Normalize a raw display name string.
     *
     * @param string $raw
     * @return string normalized name or '' if nothing usable
     */
    public static function normalizeDisplayName(string $raw): string
    {
        // Basic trim & collapse whitespace
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        // Replace multiple spaces or tabs/newlines with single spaces
        $raw = preg_replace('/\s+/u', ' ', $raw);

        // Strip disallowed characters:
        // Allow letters (any unicode), space, apostrophe, hyphen, period
        // Remove digits and other symbols
        $filtered = preg_replace("/[^\\p{L}\\s'\\-.]/u", '', $raw);
        $filtered = trim($filtered);
        if ($filtered === '') {
            return '';
        }

        // Split into tokens
        $parts = explode(' ', $filtered);

        // Lowercase particles (not first)
        $lowerParticles = [
            'de','del','dela','la','van','von','da','dos','das','di','du','le','el','y','bin','binti','al'
        ];

        $normalized = [];
        $count = count($parts);

        foreach ($parts as $i => $p) {
            $clean = trim($p, " ."); // trim periods/spaces around
            if ($clean === '') {
                continue;
            }

            // Middle initial rule: single letter or letter with period
            if (preg_match('/^[A-Za-z]$/u', $clean)) {
                $normalized[] = strtoupper($clean) . '.';
                continue;
            }
            if (preg_match('/^[A-Za-z]\\.$/u', $clean)) {
                $normalized[] = strtoupper(substr($clean, 0, 1)) . '.';
                continue;
            }

            $lowerCandidate = mb_strtolower($clean, 'UTF-8');

            // If not first token & is a recognized particle -> keep lowercase
            if ($i > 0 && in_array($lowerCandidate, $lowerParticles, true)) {
                $normalized[] = $lowerCandidate;
                continue;
            }

            // Handle hyphenated or apostrophe segments separately (e.g., Jean-Luc, O'Connor)
            $normalized[] = self::capitalizeCompound($clean);
        }

        $result = implode(' ', $normalized);

        // Final guard: collapse any double spaces that might have reappeared
        $result = preg_replace('/\s{2,}/', ' ', $result);

        return trim($result);
    }

    /**
     * Capitalize compounds with hyphen or apostrophe.
     * Examples:
     *   "o'neil"  -> "O'Neil"
     *   "jean-luc" -> "Jean-Luc"
     *   "macapagal" -> "Macapagal" (falls through standard path)
     *
     * @param string $segment
     * @return string
     */
    protected static function capitalizeCompound(string $segment): string
    {
        // Process apostrophes first (O'Neil, D'Angelo)
        if (str_contains($segment, "'")) {
            $pieces = explode("'", $segment);
            $pieces = array_map(fn($p) => self::ucfirstUtf8(mb_strtolower($p, 'UTF-8')), $pieces);
            return implode("'", $pieces);
        }

        // Hyphenated (Jean-Luc, Mary-Jayne)
        if (str_contains($segment, '-')) {
            $pieces = explode('-', $segment);
            $pieces = array_map(fn($p) => self::ucfirstUtf8(mb_strtolower($p, 'UTF-8')), $pieces);
            return implode('-', $pieces);
        }

        return self::ucfirstUtf8(mb_strtolower($segment, 'UTF-8'));
    }

    /**
     * UTF-8 safe ucfirst.
     */
    protected static function ucfirstUtf8(string $str): string
    {
        if ($str === '') return '';
        $first = mb_substr($str, 0, 1, 'UTF-8');
        $rest  = mb_substr($str, 1, null, 'UTF-8');
        return mb_strtoupper($first, 'UTF-8') . $rest;
    }
}