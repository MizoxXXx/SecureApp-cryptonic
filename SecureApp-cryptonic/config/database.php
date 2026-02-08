<?php
/**
         * Configuration loader - Loads environment variables
         * Security: Use proper .env file loading in production
 */

class ConfigLoader {
    private static $config = [];

    public static function load($envFile) {
        if (!file_exists($envFile)) {
            throw new Exception(".env file not found at: " . $envFile);
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                self::$config[$key] = $value;
            }
        }
    }

    public static function get($key, $default = null) {
        return self::$config[$key] ?? $default;
    }

    public static function getAll() {
        return self::$config;
    }
}

// Load environment variables
$envFile = __DIR__ . '/../.env';
ConfigLoader::load($envFile);

// Define configuration constants
define('DB_HOST', ConfigLoader::get('DB_HOST', '127.0.0.1'));
define('DB_PORT', ConfigLoader::get('DB_PORT', '5432'));
define('DB_NAME', ConfigLoader::get('DB_NAME', 'trafic_notes'));
define('DB_USER', ConfigLoader::get('DB_USER', 'postgres'));
define('DB_PASSWORD', ConfigLoader::get('DB_PASSWORD'));

define('APP_NAME', ConfigLoader::get('APP_NAME', 'trafic_notes'));
define('APP_ENV', ConfigLoader::get('APP_ENV', 'production'));
define('APP_DEBUG', ConfigLoader::get('APP_DEBUG', 'false') === 'true');
define('APP_URL', ConfigLoader::get('APP_URL', 'http://127.0.0.1/SecureApp-cryptonic'));

define('SESSION_TIMEOUT', (int)ConfigLoader::get('SESSION_TIMEOUT', 3600));
define('SESSION_NAME', ConfigLoader::get('SESSION_NAME', 'PVMS_SESSION'));

// rate limiting settings
define('CSRF_TOKEN_EXPIRY', (int)ConfigLoader::get('CSRF_TOKEN_EXPIRY', 3600));
define('RATE_LIMIT_LOGIN_ATTEMPTS', (int)ConfigLoader::get('RATE_LIMIT_LOGIN_ATTEMPTS', 5));
define('RATE_LIMIT_LOGIN_WINDOW', (int)ConfigLoader::get('RATE_LIMIT_LOGIN_WINDOW', 900));

// PDO DSN for PostgreSQL
define('PDO_DSN', sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    DB_HOST,
    DB_PORT,
    DB_NAME
));

// Security headers defaults
define('SECURITY_HEADERS', [
    'X-Content-Type-Options' => 'nosniff', // Prevents browsers from MIME-sniffing (guessing file types)
    'X-Frame-Options' => 'DENY', // Blocks page from being loaded in <frame>, <iframe>, <embed> (prevents clickjacking)
    'X-XSS-Protection' => '1; mode=block',
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains', //Forces HTTPS for 1 year including subdomains
    'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';",
]);
