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

use Magento\Framework\App\ResourceConnection;

/**
 * Adds the Magento table prefix (if the store has one) to table names written after FROM and JOIN,
 * so the same query works with and without a prefix. Names that already carry the prefix, names with a
 * schema (db.table) and names that are not Magento tables stay as they are.
 */
class Prefixer
{
    /**
     * @var string[]|null
     */
    private $tables = null;

    /**
     * @param ResourceConnection $resource
     * @param Validator $validator
     */
    public function __construct(private ResourceConnection $resource, private Validator $validator)
    {
    }

    /**
     * Table prefix from env.php
     *
     * @return string
     */
    public function prefix(): string
    {
        return (string)$this->resource->getTablePrefix();
    }

    /**
     * Query with prefixed table names
     *
     * @param string $sql query without comments
     * @return string
     */
    public function apply(string $sql): string
    {
        $prefix = $this->prefix();
        if ($prefix === '') {
            return $sql;
        }
        $masked = $this->validator->mask($sql, true);
        if (!preg_match_all('/\b(FROM|JOIN)\s+/i', $masked, $m, PREG_OFFSET_CAPTURE)) {
            return $sql;
        }
        $edits = [];
        foreach ($m[0] as [$text, $offset]) {
            $pos = $offset + strlen($text);
            $quoted = ($sql[$pos] ?? '') === '`';
            if ($quoted) {
                $end = strpos($sql, '`', $pos + 1);
                if ($end === false) {
                    continue;
                }
                $name = substr($sql, $pos + 1, $end - $pos - 1);
                $after = $end + 1;
            } else {
                if (!preg_match('/\G[A-Za-z_][A-Za-z0-9_$]*/', $sql, $nm, 0, $pos)) {
                    continue;
                }
                $name = $nm[0];
                $after = $pos + strlen($name);
            }
            $rest = ltrim(substr($masked, $after, 2));
            if ($rest !== '' && ($rest[0] === '.' || $rest[0] === '(')) {
                continue;
            }
            if (str_starts_with($name, $prefix) || !$this->isTable($prefix . $name)) {
                continue;
            }
            $edits[] = [$quoted ? $pos + 1 : $pos, $name];
        }
        foreach (array_reverse($edits) as [$at]) {
            $sql = substr($sql, 0, $at) . $prefix . substr($sql, $at);
        }
        return $sql;
    }

    /**
     * Does this (prefixed) table exist?
     *
     * @param string $name
     * @return bool
     */
    private function isTable(string $name): bool
    {
        if ($this->tables === null) {
            $this->tables = array_flip(array_map('strtolower', $this->resource->getConnection()->getTables()));
        }
        return isset($this->tables[strtolower($name)]);
    }
}
