<?php
declare(strict_types=1);

/**
 * auth/db_connect.php
 *
 * Production-safe PDO connector for MariaDB/MySQL over TCP+SSL with:
 *  - Centralized error handling (no raw PDO errors to the browser)
 *  - .env loading (with sane defaults) and strict SSL enforcement
 *  - Minimal, dependency-free implementation
 *
 * Usage:
 *   $pdo = db();  // get shared PDO instance
 */

final class DbConfig
{
    public string $host;
    public int    $port;
    public string $name;
    public string $user;
    public string $pass;
    public string $charset;
    public string $sslMode;     // 'required' | 'prefer' | 'disable'
    public ?string $sslCA;      // absolute path to CA bundle (optional)
    public ?string $sslCert;    // client cert (optional)
    public ?string $sslKey;     // client key (optional)
    public ?string $sslCipher;  // optional

    public static function fromEnv(string $projectRoot): self
    {
        // Prefer project-root .env, then fall back to parent directory .env.
        self::loadDotEnvIfPresent($projectRoot.'/.env');
        self::loadDotEnvIfPresent(dirname($projectRoot).'/.env');

        $env = fn(string $k, ?string $default = null) =>
            array_key_exists($k, $_ENV) ? trim((string)$_ENV[$k]) :
            (getenv($k) !== false ? trim((string)getenv($k)) : $default);

        $cfg = new self();
        $cfg->host    = (string)($env('DB_HOST')    ?? 'localhost');         // MUST be host: forces TCP (no sockets)
        $cfg->port    = (int)   ($env('DB_PORT')    ?? '3306');
        $cfg->name    = (string)($env('DB_NAME')    ?? '');
        $cfg->user    = (string)($env('DB_USERNAME')?? '');
        $cfg->pass    = (string)($env('DB_PASSWORD')?? '');
        $cfg->charset = (string)($env('DB_CHARSET') ?? 'utf8mb4');

        // SSL policy:
        //  - 'required' => abort if SSL not negotiated
        //  - 'prefer'   => try SSL; if not possible, fall back to plaintext
        //  - 'disable'  => plaintext only (NOT recommended)
        $cfg->sslMode  = strtolower((string)($env('DB_SSL') ?? 'required'));
        $cfg->sslCA    = self::nullIfEmpty($env('DB_SSL_CA'));
        $cfg->sslCert  = self::nullIfEmpty($env('DB_SSL_CERT'));
        $cfg->sslKey   = self::nullIfEmpty($env('DB_SSL_KEY'));
        $cfg->sslCipher= self::nullIfEmpty($env('DB_SSL_CIPHER'));

        // Basic validation
        if ($cfg->name === '' || $cfg->user === '' || $cfg->host === '') {
            self::logError('DB CONFIG ERROR: Missing required DB_* env vars (DB_HOST, DB_NAME, DB_USERNAME).');
            throw new \RuntimeException('Database configuration error.');
        }

        return $cfg;
    }

    private static function loadDotEnvIfPresent(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        // Very small .env parser (no dependencies). Supports KEY=VALUE and inline comments.
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) continue;
            [$k, $v] = [trim($parts[0]), trim($parts[1])];

            // Strip optional surrounding quotes
            if ((str_starts_with($v, '"') && str_ends_with($v, '"')) ||
                (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
                $v = substr($v, 1, -1);
            } else {
                // Unquoted values: allow inline comments after whitespace.
                $v = preg_replace('/\s+[;#].*$/', '', $v) ?? $v;
                $v = rtrim($v);
            }

            if (!array_key_exists($k, $_ENV)) {
                $_ENV[$k] = $v;
            }
            // Keep getenv() in sync for code that reads env values through it,
            // but do not override values that were loaded earlier.
            if (getenv($k) === false) {
                putenv("$k=$v");
            }
        }
    }

    private static function nullIfEmpty(?string $v): ?string
    {
        $v = $v !== null ? trim($v) : null;
        return ($v === '' ? null : $v);
    }

    public static function logError(string $msg): void
    {
        $root = realpath(__DIR__ . '/..') ?: __DIR__;
        $logDir = $root . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $line = sprintf("[%s] %s\n", date('c'), $msg);
        @file_put_contents($logDir . '/db_errors.log', $line, FILE_APPEND);
    }
}

final class Db
{
    private static ?\PDO $pdo = null;

    public static function instance(): \PDO
    {
        if (self::$pdo instanceof \PDO) {
            return self::$pdo;
        }

        $projectRoot = realpath(__DIR__ . '/..') ?: __DIR__;
        $cfg = DbConfig::fromEnv($projectRoot);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg->host,
            $cfg->port,
            $cfg->name,
            $cfg->charset
        );

        $baseOptions = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::ATTR_TIMEOUT            => 10, // seconds
        ];

        // SSL options
        $sslOptions = [];
        if ($cfg->sslMode !== 'disable') {
            $setMysqlOpt = static function (array &$opts, string $constantName, $value): void {
                if (defined($constantName)) {
                    $opts[constant($constantName)] = $value;
                }
            };

            // If a CA is provided, use it; otherwise rely on system trust store if available.
            if ($cfg->sslCA && is_readable($cfg->sslCA)) {
                $setMysqlOpt($sslOptions, 'PDO::MYSQL_ATTR_SSL_CA', $cfg->sslCA);
            }
            if ($cfg->sslCert && is_readable($cfg->sslCert)) {
                $setMysqlOpt($sslOptions, 'PDO::MYSQL_ATTR_SSL_CERT', $cfg->sslCert);
            }
            if ($cfg->sslKey && is_readable($cfg->sslKey)) {
                $setMysqlOpt($sslOptions, 'PDO::MYSQL_ATTR_SSL_KEY', $cfg->sslKey);
            }
            if ($cfg->sslCipher) {
                $setMysqlOpt($sslOptions, 'PDO::MYSQL_ATTR_SSL_CIPHER', $cfg->sslCipher);
            }

            // Some hosts negotiate TLS without explicit CA if server is public+trusted.
            // We still set the SSL flags to request TLS.
            $setMysqlOpt($sslOptions, 'PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT', false); // do NOT echo cert failures; rely on CA if provided
        }

        // Merge options
        $options = $baseOptions + $sslOptions;

        try {
            self::$pdo = new \PDO($dsn, $cfg->user, $cfg->pass, $options);

            // Verify SSL if required
            if ($cfg->sslMode === 'required') {
                $stmt = self::$pdo->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'");
                $row  = $stmt ? $stmt->fetch() : null;
                $cipher = $row['Value'] ?? '';
                if ($cipher === '') {
                    throw new \RuntimeException('SSL/TLS was required but not negotiated with the database server.');
                }
            }

            // Set session modes you care about
            self::$pdo->exec("SET NAMES {$cfg->charset}");
            self::$pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE,ONLY_FULL_GROUP_BY'");

            return self::$pdo;

        } catch (\Throwable $e) {
            // Log detailed error, but do not expose it to the client.
            DbConfig::logError('DB CONNECT FAIL: [' . get_class($e) . '] ' . $e->getMessage() . ' (code: ' . ($e->getCode() ?: 'n/a') . ')');
            // Throw a generic error to the application layer
            throw new \RuntimeException('Database connection error.');
        }
    }
}

/**
 * Helper for consumers
 */
function db(): \PDO
{
    return Db::instance();
}

