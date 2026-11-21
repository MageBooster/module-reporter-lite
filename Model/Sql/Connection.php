<?php
/*
 * Copyright (c) 2026 WebWorks 101 OÜ (MageBooster). All rights reserved.
 * Commercial licence, see LICENSE.txt. https://magebooster.com
 * Licensed per serial number for one production installation (one store domain); staging and
 * development copies of that store are allowed. Serial numbers and downloads are tied to the customer.
 * You may modify this code for your own licensed store; keep this notice.
 * Not allowed: copying to other stores, redistributing, reselling, sublicensing, publishing it publicly,
 * removing licence checks or notices, sharing serial numbers. Unauthorised use: the serial number is
 * revoked without refund, updates and support stop, damages and legal action may follow.
 * Estonian law applies; court: Harju County Court (Harju Maakohus).
 */
declare(strict_types=1);

namespace MageBooster\ReporterLite\Model\Sql;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Exception\LocalizedException;

/**
 * PDO connection as the read-only report user from app/etc/env.php:
 *   'mbreporterlite' => ['sql' => ['username' => '...', 'password' => '...', 'host' => '...', 'dbname' => '...']]
 * If that key is missing, the user of MageBooster Reports (mbreports/sql) is used. host and dbname default to
 * the main connection. There is no fallback to the main Magento user. The password is never shown or logged.
 *
 * Every session is READ ONLY, runs in UTC (like Magento) and has a statement time limit.
 */
class Connection
{
    private const KEYS = ['mbreporterlite/sql', 'mbreports/sql'];

    /**
     * @param DeploymentConfig $deploymentConfig
     */
    public function __construct(private DeploymentConfig $deploymentConfig)
    {
    }

    /**
     * Which env.php key holds the read-only user, or null when none is set up
     *
     * @return string|null
     */
    public function source(): ?string
    {
        foreach (self::KEYS as $key) {
            $cfg = $this->deploymentConfig->get($key);
            if (is_array($cfg) && !empty($cfg['username']) && array_key_exists('password', $cfg)) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Opens a read-only session
     *
     * @param int $maxSeconds
     * @return \PDO
     * @throws LocalizedException
     */
    public function open(int $maxSeconds): \PDO
    {
        $key = $this->source();
        if ($key === null) {
            throw new LocalizedException(__(
                'Reporter Lite is not set up yet: add the read-only database user to app/etc/env.php '
                . '(mbreporterlite/sql). The guide shows how.'
            ));
        }
        $cfg = (array)$this->deploymentConfig->get($key);
        $main = (array)$this->deploymentConfig->get('db/connection/default');
        if (strcasecmp((string)$cfg['username'], (string)($main['username'] ?? '')) === 0
            && ($cfg['host'] ?? $main['host'] ?? '') === ($main['host'] ?? '')) {
            throw new LocalizedException(__(
                'The report user is the same as the Magento database user. Create a separate read-only user.'
            ));
        }
        $host = (string)($cfg['host'] ?? $main['host'] ?? 'localhost');
        $db = (string)($cfg['dbname'] ?? $main['dbname'] ?? '');
        $dsn = str_starts_with($host, '/') ? 'mysql:unix_socket=' . $host : 'mysql:host=' . $host;
        if (!str_starts_with($host, '/') && str_contains($host, ':')) {
            [$h, $port] = explode(':', $host, 2);
            $dsn = ctype_digit($port) ? 'mysql:host=' . $h . ';port=' . $port : 'mysql:unix_socket=' . $port;
        }
        try {
            $pdo = new \PDO(
                $dsn . ';dbname=' . $db . ';charset=utf8mb4',
                (string)$cfg['username'],
                (string)$cfg['password'],
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_EMULATE_PREPARES => true,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_TIMEOUT => 5,
                    \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
                    \PDO::ATTR_STRINGIFY_FETCHES => false,
                ]
            );
        } catch (\PDOException $e) {
            throw new LocalizedException(__('Could not connect as the read-only report user.'));
        }
        $seconds = max(1, min(120, $maxSeconds));
        $limited = false;
        try {
            $pdo->exec('SET SESSION max_statement_time = ' . $seconds); // MariaDB, seconds
            $limited = true;
        } catch (\PDOException $e) {
            $limited = false;
        }
        try {
            $pdo->exec('SET SESSION max_execution_time = ' . ($seconds * 1000)); // MySQL 5.7+, milliseconds
            $limited = true;
        } catch (\PDOException $e) {
            $limited = $limited || false;
        }
        if (!$limited) {
            throw new LocalizedException(__('The database does not support a statement time limit.'));
        }
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');
        $pdo->exec("SET time_zone = '+00:00'"); // Magento stores UTC; TIMESTAMP columns read as UTC too
        return $pdo;
    }

    /**
     * State for the page header: not set up, cannot connect, can write (warning) or connected
     *
     * @return array{state:string,key:?string}
     */
    public function status(): array
    {
        $key = $this->source();
        if ($key === null) {
            return ['state' => 'missing', 'key' => null];
        }
        try {
            $pdo = $this->open(5);
            $grants = $pdo->query('SHOW GRANTS FOR CURRENT_USER()')->fetchAll(\PDO::FETCH_COLUMN);
            $pdo = null;
        } catch (\Throwable $e) {
            return ['state' => 'error', 'key' => $key];
        }
        foreach ((array)$grants as $g) {
            if (preg_match('/GRANT\s+(ALL|.*\b(INSERT|UPDATE|DELETE|CREATE|DROP|ALTER|FILE|SUPER)\b)/i', (string)$g)) {
                return ['state' => 'writable', 'key' => $key];
            }
        }
        return ['state' => 'ok', 'key' => $key];
    }
}
