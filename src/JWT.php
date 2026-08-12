<?php
namespace App;

/**
 * JSON Web Token (JWT) Security Component
 * 
 * Implements HMAC-SHA256 (HS256) JWT generation, validation, cookie management,
 * and request authentication for Customer, Admin, and Driver portals.
 */
class JWT {
    const DEFAULT_EXPIRY = 86400; // 24 hours
    const COOKIE_USER   = 'ke_jwt_token';
    const COOKIE_ADMIN  = 'ke_admin_jwt';
    const COOKIE_DRIVER = 'ke_driver_jwt';

    /**
     * Retrieves the secret key used for signing tokens from the environment
     */
    public static function getSecret(): string {
        $secret = getenv('JWT_SECRET') ?: ($_ENV['JWT_SECRET'] ?? ($_SERVER['JWT_SECRET'] ?? ''));
        if (empty($secret) || $secret === 'YOUR_JWT_SECRET') {
            return 'kesara_enterprises_production_sec_jwt_key_2026_@#9821';
        }
        return $secret;
    }

    /**
     * URL-safe Base64 encode
     */
    public static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * URL-safe Base64 decode
     */
    public static function base64UrlDecode(string $data): string {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=', STR_PAD_RIGHT));
    }

    /**
     * Generates a signed JWT token
     * 
     * @param array $payload Key-value claims to include in the token
     * @param int $expirySeconds Token lifetime in seconds (default: 24h)
     * @param string|null $secret Optional custom secret key
     * @return string Complete JWT string (header.payload.signature)
     */
    public static function encode(array $payload, int $expirySeconds = self::DEFAULT_EXPIRY, ?string $secret = null): string {
        $key = $secret ?: self::getSecret();
        $now = time();

        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        // Standard claims
        $claims = array_merge([
            'iss' => 'kesara-enterprises',
            'iat' => $now,
            'exp' => $now + $expirySeconds
        ], $payload);

        $base64Header = self::base64UrlEncode(json_encode($header));
        $base64Payload = self::base64UrlEncode(json_encode($claims));

        $signature = hash_hmac('sha256', "$base64Header.$base64Payload", $key, true);
        $base64Signature = self::base64UrlEncode($signature);

        return "$base64Header.$base64Payload.$base64Signature";
    }

    /**
     * Decodes and cryptographically verifies a JWT token
     * 
     * @param string $jwt The JWT token to verify
     * @param string|null $secret Optional custom secret key
     * @return array|null Returns the payload array if valid and not expired, or null if invalid
     */
    public static function decode(string $jwt, ?string $secret = null): ?array {
        $jwt = trim($jwt);
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        list($base64Header, $base64Payload, $base64Signature) = $parts;

        $key = $secret ?: self::getSecret();
        $expectedSignature = self::base64UrlEncode(hash_hmac('sha256', "$base64Header.$base64Payload", $key, true));

        // Timing attack safe comparison
        if (!hash_equals($expectedSignature, $base64Signature)) {
            return null;
        }

        $header = json_decode(self::base64UrlDecode($base64Header), true);
        if (!$header || ($header['alg'] ?? '') !== 'HS256') {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($base64Payload), true);
        if (!$payload || !is_array($payload)) {
            return null;
        }

        // Expiration check
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Sets a secure HttpOnly authentication cookie with the JWT token
     */
    public static function setAuthCookie(string $cookieName, string $jwt, int $expirySeconds = self::DEFAULT_EXPIRY, string $path = '/'): bool {
        $expires = time() + $expirySeconds;
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                   (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        return setcookie($cookieName, $jwt, [
            'expires'  => $expires,
            'path'     => $path,
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    /**
     * Clears an authentication cookie
     */
    public static function clearAuthCookie(string $cookieName, string $path = '/'): bool {
        unset($_COOKIE[$cookieName]);
        return setcookie($cookieName, '', [
            'expires'  => time() - 3600,
            'path'     => $path,
            'domain'   => '',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    /**
     * Extracts Bearer token from the HTTP Authorization header
     */
    public static function getBearerToken(): ?string {
        $headers = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $headers = $requestHeaders['Authorization'] ?? $requestHeaders['authorization'] ?? '';
        }

        if (!empty($headers) && preg_match('/Bearer\s(\S+)/i', $headers, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Authenticates customer / wholesale user from JWT token or cookie and synchronizes $_SESSION
     */
    public static function authenticateUser(): ?array {
        $token = self::getBearerToken() ?: ($_COOKIE[self::COOKIE_USER] ?? ($_SESSION['jwt_token'] ?? null));
        if (!$token) return null;

        $payload = self::decode($token);
        if (!$payload || !isset($payload['user_id'])) return null;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Sync session with JWT claims
        $_SESSION['user_id'] = $payload['user_id'];
        $_SESSION['user_email'] = $payload['email'] ?? '';
        $_SESSION['user_name'] = $payload['name'] ?? '';
        $_SESSION['user_type'] = $payload['user_type'] ?? 'individual';
        $_SESSION['jwt_token'] = $token;

        return $payload;
    }

    /**
     * Authenticates administrator from JWT token or cookie and synchronizes $_SESSION
     */
    public static function authenticateAdmin(): ?array {
        $token = self::getBearerToken() ?: ($_COOKIE[self::COOKIE_ADMIN] ?? ($_SESSION['admin_jwt'] ?? null));
        if (!$token) return null;

        $payload = self::decode($token);
        if (!$payload || !isset($payload['admin_id'])) return null;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Sync session with JWT claims
        $_SESSION['admin_id'] = $payload['admin_id'];
        $_SESSION['admin_username'] = $payload['username'] ?? '';
        $_SESSION['admin_role'] = $payload['role'] ?? 'admin';
        $_SESSION['admin_jwt'] = $token;

        return $payload;
    }

    /**
     * Authenticates delivery driver from JWT token or cookie and synchronizes $_SESSION
     */
    public static function authenticateDriver(): ?array {
        $token = self::getBearerToken() ?: ($_COOKIE[self::COOKIE_DRIVER] ?? ($_SESSION['driver_jwt'] ?? null));
        if (!$token) return null;

        $payload = self::decode($token);
        if (!$payload || !isset($payload['driver_id'])) return null;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Sync session with JWT claims
        $_SESSION['driver_id'] = $payload['driver_id'];
        $_SESSION['driver_name'] = $payload['name'] ?? '';
        $_SESSION['driver_vehicle'] = $payload['vehicle'] ?? '';
        $_SESSION['driver_jwt'] = $token;

        return $payload;
    }
}

// Global class alias for ease of use across procedural files
if (!class_alias('App\JWT', 'JWT')) {
    // Already defined
}
