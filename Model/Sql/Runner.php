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

use MageBooster\ReporterLite\Model\Config;
use Magento\Framework\Exception\LocalizedException;

/**
 * Runs a checked SELECT on the read-only connection with bound parameters, a time limit and a row cap.
 */
class Runner
{
    /**
     * @param Validator $validator
     * @param Connection $connection
     * @param Prefixer $prefixer
     * @param Config $config
     */
    public function __construct(
        private Validator $validator,
        private Connection $connection,
        private Prefixer $prefixer,
        private Config $config
    ) {
    }

    /**
     * Runs the query
     *
     * @param string $sql
     * @param array<string,string|int|float|null> $values parameter values by name (without the colon)
     * @param int|null $limit rows to read, at most the configured cap
     * @return array{columns:array,rows:array,count:int,truncated:bool,ms:int}
     * @throws LocalizedException
     */
    public function run(string $sql, array $values, ?int $limit = null): array
    {
        $check = $this->validator->check($sql);
        $cap = $this->config->maxRows();
        $limit = max(1, min($cap, $limit ?? $cap));
        foreach ($check['params'] as $p) {
            if (!array_key_exists($p, $values) || $values[$p] === null || $values[$p] === '') {
                throw new LocalizedException(__('Fill in a value for :%1.', $p));
            }
        }
        $query = $this->prefixer->apply($check['sql']);
        $started = microtime(true);
        $pdo = $this->connection->open($this->config->maxSeconds());
        $rows = [];
        $truncated = false;
        $columns = [];
        try {
            $stmt = $pdo->prepare($query);
            foreach ($check['params'] as $p) {
                $v = $values[$p];
                $isInt = is_int($v) || (is_string($v) && preg_match('/^-?\d{1,15}$/', $v));
                $stmt->bindValue(':' . $p, $isInt ? (int)$v : (string)$v, $isInt ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $stmt->execute();
            for ($i = 0; $i < $stmt->columnCount(); $i++) {
                $meta = $stmt->getColumnMeta($i) ?: [];
                $columns[] = (string)($meta['name'] ?? ('column_' . ($i + 1)));
            }
            while (($row = $stmt->fetch(\PDO::FETCH_NUM)) !== false) {
                if (count($rows) >= $limit) {
                    $truncated = true;
                    break;
                }
                $rows[] = $row;
            }
            $stmt = null;
        } catch (\PDOException $e) {
            throw new LocalizedException(__('The query failed: %1', $this->safeMessage($e)));
        } finally {
            // closing the connection also stops a statement that still sends rows
            $pdo = null;
        }
        return [
            'columns' => $this->describe($columns, $rows),
            'rows' => $rows,
            'count' => count($rows),
            'truncated' => $truncated,
            'ms' => (int)round((microtime(true) - $started) * 1000),
        ];
    }

    /**
     * Column names with a numeric flag (all non-empty values in the first 200 rows are numbers)
     *
     * @param string[] $names
     * @param array $rows
     * @return array<int,array{key:string,numeric:bool}>
     */
    private function describe(array $names, array $rows): array
    {
        $out = [];
        foreach ($names as $i => $name) {
            $numeric = $rows !== [];
            foreach (array_slice($rows, 0, 200) as $r) {
                if ($r[$i] !== null && !is_int($r[$i]) && !is_float($r[$i]) && !is_numeric($r[$i])) {
                    $numeric = false;
                    break;
                }
            }
            if (preg_match('/(^id$|_id$|^entity_id$|year$|^sku$|code$|phone|postcode|zip)/i', $name)) {
                $numeric = false;
            }
            $out[] = ['key' => $name, 'numeric' => $numeric];
        }
        return $out;
    }

    /**
     * Database error without connection details
     *
     * @param \PDOException $e
     * @return string
     */
    private function safeMessage(\PDOException $e): string
    {
        $msg = $e->getMessage();
        if (preg_match('/max_statement_time|max_execution_time|\b1969\b|\b3024\b|interrupted/i', $msg)) {
            return (string)__('it took too long and was stopped.');
        }
        if (preg_match('/command denied|\b1142\b|\b1143\b/i', $msg)) {
            return (string)__('the report user may not read this table or column.');
        }
        if (preg_match('/READ ONLY transaction|\b1792\b/i', $msg)) {
            return (string)__('only reading is allowed.');
        }
        $msg = preg_replace('/SQLSTATE\[[^\]]*\]:?\s*/', '', $msg) ?? $msg;
        $msg = preg_replace('/^[^:]*error:\s*\d+\s*/i', '', $msg) ?? $msg;
        $msg = preg_replace("/'[^']*'@'[^']*'/", 'report user', $msg) ?? $msg;
        return mb_substr($msg, 0, 300);
    }
}
