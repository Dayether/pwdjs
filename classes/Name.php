<?php
// Lightweight name normalization helpers
class Name {
    public static function normalizeWhitespace(string $s): string {
        $s = trim($s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return $s ?? '';
    }

    private static function titleCaseComponent(string $s): string {
        if ($s === '') return '';
        $s = mb_strtolower($s);
        // Title-case subparts split by hyphen and apostrophe
        $dashParts = explode('-', $s);
        foreach ($dashParts as &$dp) {
            $apoParts = explode("'", $dp);
            foreach ($apoParts as &$ap) {
                if ($ap !== '') {
                    $ap = mb_strtoupper(mb_substr($ap, 0, 1)) . mb_substr($ap, 1);
                }
            }
            $dp = implode("'", $apoParts);
        }
        return implode('-', $dashParts);
    }

    public static function titleCaseName(string $name): string {
        $name = self::normalizeWhitespace($name);
        if ($name === '') return '';

        $particles = ['da','de','del','dela','di','du','la','las','le','les','van','von','y','bin','binti','ibn','al'];

        $tokens = preg_split('/\s+/u', $name);
        $out = [];
        foreach ($tokens as $i => $tok) {
            $cased = self::titleCaseComponent($tok);
            // Keep certain particles lowercase when not the first token
            if ($i > 0 && in_array(mb_strtolower($tok), $particles, true)) {
                $cased = mb_strtolower($tok);
            }
            $out[] = $cased;
        }

        // Normalize common suffixes
        $last = mb_strtolower(end($out));
        $suffixMap = ['jr'=>'Jr.', 'sr'=>'Sr.', 'ii'=>'II', 'iii'=>'III', 'iv'=>'IV', 'v'=>'V'];
        if (isset($suffixMap[$last])) {
            $out[count($out) - 1] = $suffixMap[$last];
        }
        return implode(' ', $out);
    }

    // Compress full name to "First [M.] Last" while keeping good casing
    public static function normalizeDisplayName(string $raw): string {
        $raw = self::titleCaseName($raw);
        if ($raw === '') return '';

        $parts = preg_split('/\s+/u', $raw);
        // Require at least First + Last
        if (count($parts) < 2) return '';

        $first = array_shift($parts);
        $last  = array_pop($parts);

        // Build middle initial if any middle parts exist (take first letter of the first middle)
        $mi = '';
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') continue;
            $mi = mb_strtoupper(mb_substr($p, 0, 1)) . '.';
            break;
        }

        return trim($first . ' ' . ($mi ? $mi . ' ' : '') . $last);
    }
}