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

namespace MageBooster\ReporterLite\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Settings from Stores > Configuration > MageBooster > Reporter Lite, with safe bounds.
 */
class Config
{
    private const BASE = 'magebooster_reporterlite/';

    /**
     * @param ScopeConfigInterface $config
     */
    public function __construct(private ScopeConfigInterface $config)
    {
    }

    /**
     * Most rows one run may read
     *
     * @return int
     */
    public function maxRows(): int
    {
        return $this->int('sql/max_rows', 5000, 10, 100000);
    }

    /**
     * Statement time limit in seconds
     *
     * @return int
     */
    public function maxSeconds(): int
    {
        return $this->int('sql/max_seconds', 10, 1, 120);
    }

    /**
     * How long download links and files live
     *
     * @return int
     */
    public function linkDays(): int
    {
        return $this->int('files/link_days', 7, 1, 30);
    }

    /**
     * How long run history rows are kept
     *
     * @return int
     */
    public function historyDays(): int
    {
        return $this->int('files/history_days', 90, 7, 730);
    }

    /**
     * Largest attachment in bytes
     *
     * @return int
     */
    public function maxAttachBytes(): int
    {
        return $this->int('mail/max_attach_mb', 10, 1, 25) * 1024 * 1024;
    }

    /**
     * E-mail identity code (general, sales, ...)
     *
     * @return string
     */
    public function senderIdentity(): string
    {
        $v = (string)$this->config->getValue(self::BASE . 'mail/sender');
        return preg_match('/^[a-z0-9_]+$/', $v) ? $v : 'general';
    }

    /**
     * Store sender address and name for the configured identity
     *
     * @return array{email:string,name:string}
     */
    public function sender(): array
    {
        $id = $this->senderIdentity();
        return [
            'email' => (string)$this->config->getValue('trans_email/ident_' . $id . '/email'),
            'name' => (string)$this->config->getValue('trans_email/ident_' . $id . '/name'),
        ];
    }

    /**
     * Is e-mail switched off for the whole store?
     *
     * @return bool
     */
    public function mailDisabled(): bool
    {
        return $this->config->isSetFlag('system/smtp/disable');
    }

    /**
     * Store timezone name (default scope)
     *
     * @return string
     */
    public function timezone(): string
    {
        $tz = (string)$this->config->getValue('general/locale/timezone');
        try {
            new \DateTimeZone($tz);
        } catch (\Throwable $e) {
            $tz = 'UTC';
        }
        return $tz ?: 'UTC';
    }

    /**
     * Integer setting within bounds
     *
     * @param string $path
     * @param int $default
     * @param int $min
     * @param int $max
     * @return int
     */
    private function int(string $path, int $default, int $min, int $max): int
    {
        $v = $this->config->getValue(self::BASE . $path);
        $v = is_numeric($v) ? (int)$v : $default;
        return max($min, min($max, $v));
    }
}
