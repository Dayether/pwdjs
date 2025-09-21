<?php
/**
 * Sensitive.php
 * Utility para sa encryption/decryption ng mga sensitibong fields tulad ng Government PWD ID.
 * AES-256-CBC w/ random IV. Output format: base64( IV (16 bytes) + CIPHERTEXT ).
 *
 * Usage:
 *   $enc = Sensitive::encrypt($plain);
 *   $plain = Sensitive::decrypt($enc);
 *   $mask = Sensitive::maskLast4($plainOrMaskedSource);
 *
 * Requirements:
 *   - PHP OpenSSL extension enabled.
 *   - Set secret key either:
 *       a) define('APP_ENC_KEY','your-32-char-secret...') in config/config.php
 *       b) or environment variable APP_ENC_KEY (exactly or at least 32 chars)
 *
 * Security Notes:
 *   - Huwag i-commit sa repo ang totoong prod key.
 *   - Kung kulang sa 32 chars ang key, auto pad with '0'.
 *   - If sobra, we truncate to first 32 bytes (consistent length for AES-256).
 */
class Sensitive {

    /**
     * Get 32-byte encryption key.
     * Priority: defined constant > getenv
     * Returns a 32-byte (not hex string; raw) key derived from the configured secret.
     */
    private static function key(): string {
        $secret = null;

        if (defined('APP_ENC_KEY')) {
            // Use constant value
            $secret = constant('APP_ENC_KEY');
        } else {
            // Try environment variable
            $env = getenv('APP_ENC_KEY');
            if ($env !== false && $env !== '') {
                $secret = $env;
            }
        }

        if (!$secret) {
            // DEV fallback (DO NOT USE IN PROD)
            $secret = 'DEV_INSECURE_FALLBACK_KEY_CHANGE_ME_32B!';
        }

        // Normalize to exactly 32 bytes (UTF-8 safe: treat as bytes)
        if (strlen($secret) < 32) {
            $secret = str_pad($secret, 32, '0');
        } elseif (strlen($secret) > 32) {
            $secret = substr($secret, 0, 32);
        }
        return $secret;
    }

    /**
     * Encrypt plaintext. Returns base64 blob or null on failure.
     */
    public static function encrypt(?string $plain): ?string {
        if ($plain === null || $plain === '') return null;
        $key = self::key();
        $iv  = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) return null;
        return base64_encode($iv . $cipher);
    }

    /**
     * Decrypt stored base64 blob. Returns plaintext or null.
     */
    public static function decrypt(?string $blob): ?string {
        if ($blob === null || $blob === '') return null;
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) < 17) return null;
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $key = self::key();
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $plain === false ? null : $plain;
    }

    /**
     * Mask all but last 4 characters.
     * Examples: ABCD1234 -> ****1234; 123 -> *** (all).
     */
    public static function maskLast4(?string $full): ?string {
        if ($full === null) return null;
        $len = strlen($full);
        if ($len <= 4) return str_repeat('*', $len);
        return str_repeat('*', $len - 4) . substr($full, -4);
    }
}