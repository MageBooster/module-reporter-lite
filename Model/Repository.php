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

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Reports and run history (main Magento connection; the report queries themselves never use it).
 */
class Repository
{
    public const REPORT = 'mbreporterlite_report';
    public const RUN = 'mbreporterlite_run';

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private ResourceConnection $resource)
    {
    }

    /**
     * All reports, newest change first
     *
     * @return array[]
     */
    public function all(): array
    {
        $db = $this->resource->getConnection();
        $rows = $db->fetchAll($db->select()->from($this->table(self::REPORT))->order('updated_at DESC'));
        return array_map([$this, 'decode'], $rows);
    }

    /**
     * One report
     *
     * @param int $id
     * @return array
     * @throws NoSuchEntityException
     */
    public function get(int $id): array
    {
        $db = $this->resource->getConnection();
        $row = $db->fetchRow($db->select()->from($this->table(self::REPORT))->where('report_id = ?', $id));
        if (!$row) {
            throw new NoSuchEntityException(__('This report no longer exists.'));
        }
        return $this->decode($row);
    }

    /**
     * Inserts or updates a report and returns its id
     *
     * @param array $data columns (arrays are stored as JSON)
     * @param int|null $id
     * @return int
     */
    public function save(array $data, ?int $id = null): int
    {
        $db = $this->resource->getConnection();
        $row = [];
        foreach ($data as $k => $v) {
            if ($k === 'recipients' && is_array($v)) {
                $row[$k] = implode(',', $v);
                continue;
            }
            $row[$k] = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $v;
        }
        if ($id) {
            $db->update($this->table(self::REPORT), $row, ['report_id = ?' => $id]);
            return $id;
        }
        $db->insert($this->table(self::REPORT), $row);
        return (int)$db->lastInsertId($this->table(self::REPORT));
    }

    /**
     * Deletes a report (its history goes with it)
     *
     * @param int $id
     * @return string[] stored file names that belonged to it
     */
    public function delete(int $id): array
    {
        $db = $this->resource->getConnection();
        $files = $db->fetchCol($db->select()->from($this->table(self::RUN), 'file')
            ->where('report_id = ?', $id)->where('file IS NOT NULL'));
        $db->delete($this->table(self::REPORT), ['report_id = ?' => $id]);
        return $files;
    }

    /**
     * Reports due to be sent; each is claimed by moving its next_run_at so that two cron processes cannot
     * send the same report twice
     *
     * @param string $nowUtc
     * @return int[]
     */
    public function claimDue(string $nowUtc): array
    {
        $db = $this->resource->getConnection();
        $t = $this->table(self::REPORT);
        $due = $db->fetchAll($db->select()->from($t, ['report_id', 'next_run_at'])
            ->where('cron_status = 1')->where('is_active = 1')
            ->where('next_run_at IS NOT NULL')->where('next_run_at <= ?', $nowUtc)->limit(20));
        $claimed = [];
        foreach ($due as $r) {
            $n = $db->update($t, ['next_run_at' => null], [
                'report_id = ?' => (int)$r['report_id'],
                'next_run_at = ?' => $r['next_run_at'],
            ]);
            if ($n === 1) {
                $claimed[] = (int)$r['report_id'];
            }
        }
        return $claimed;
    }

    /**
     * Adds a history row and returns its id
     *
     * @param array $row
     * @return int
     */
    public function addRun(array $row): int
    {
        $db = $this->resource->getConnection();
        $db->insert($this->table(self::RUN), $row);
        return (int)$db->lastInsertId($this->table(self::RUN));
    }

    /**
     * Latest runs of a report
     *
     * @param int $reportId
     * @param int $limit
     * @return array[]
     */
    public function history(int $reportId, int $limit = 50): array
    {
        $db = $this->resource->getConnection();
        return $db->fetchAll($db->select()->from($this->table(self::RUN), [
            'run_id', 'run_at', 'run_trigger', 'row_count', 'result', 'message', 'file', 'expires_at', 'admin_user',
        ])->where('report_id = ?', $reportId)->order('run_id DESC')->limit($limit));
    }

    /**
     * One history row
     *
     * @param int $runId
     * @return array|null
     */
    public function run(int $runId): ?array
    {
        $db = $this->resource->getConnection();
        $row = $db->fetchRow($db->select()->from($this->table(self::RUN))->where('run_id = ?', $runId));
        return $row ?: null;
    }

    /**
     * History row for a live download token
     *
     * @param string $hash
     * @param string $nowUtc
     * @return array|null
     */
    public function runByToken(string $hash, string $nowUtc): ?array
    {
        $db = $this->resource->getConnection();
        $row = $db->fetchRow($db->select()->from(['r' => $this->table(self::RUN)])
            ->join(['p' => $this->table(self::REPORT)], 'p.report_id = r.report_id', ['name', 'export_type'])
            ->where('r.token_hash = ?', $hash)->where('r.expires_at > ?', $nowUtc));
        return $row ?: null;
    }

    /**
     * Expired files: clears token and file on their rows and returns the file names
     *
     * @param string $nowUtc
     * @return string[]
     */
    public function expire(string $nowUtc): array
    {
        $db = $this->resource->getConnection();
        $t = $this->table(self::RUN);
        $files = $db->fetchCol($db->select()->from($t, 'file')->where('file IS NOT NULL')
            ->where('expires_at <= ?', $nowUtc));
        $db->update($t, ['file' => null, 'token_hash' => null], ['expires_at <= ?' => $nowUtc, 'file IS NOT NULL']);
        $db->update($t, ['token_hash' => null], ['expires_at <= ?' => $nowUtc]);
        return $files;
    }

    /**
     * Files still referenced by a history row
     *
     * @return string[]
     */
    public function liveFiles(): array
    {
        $db = $this->resource->getConnection();
        return $db->fetchCol($db->select()->from($this->table(self::RUN), 'file')->where('file IS NOT NULL'));
    }

    /**
     * Deletes history older than the cut-off
     *
     * @param string $beforeUtc
     * @return int
     */
    public function pruneHistory(string $beforeUtc): int
    {
        return (int)$this->resource->getConnection()->delete($this->table(self::RUN), [
            'run_at < ?' => $beforeUtc,
            'file IS NULL',
        ]);
    }

    /**
     * Updates the "last run" columns
     *
     * @param int $id
     * @param array $data
     * @return void
     */
    public function touch(int $id, array $data): void
    {
        $this->resource->getConnection()->update($this->table(self::REPORT), $data, ['report_id = ?' => $id]);
    }

    /**
     * Table name with prefix
     *
     * @param string $name
     * @return string
     */
    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }

    /**
     * Report row with JSON columns decoded
     *
     * @param array $row
     * @return array
     */
    private function decode(array $row): array
    {
        foreach (['query_parameters', 'schedule'] as $k) {
            $v = json_decode((string)($row[$k] ?? ''), true);
            $row[$k] = is_array($v) ? $v : [];
        }
        $row['recipients'] = array_values(array_filter(array_map('trim', explode(',', (string)$row['recipients']))));
        foreach (['report_id', 'is_active', 'cron_status', 'skip_if_empty'] as $k) {
            $row[$k] = (int)$row[$k];
        }
        $row['last_rows'] = $row['last_rows'] === null ? null : (int)$row['last_rows'];
        return $row;
    }

    /**
     * Throws for an unknown export type or delivery
     *
     * @param string $type
     * @param string $delivery
     * @return void
     * @throws LocalizedException
     */
    public function assertOptions(string $type, string $delivery): void
    {
        if (!isset(Export::TYPES[$type]) || !in_array($delivery, ['attach', 'link'], true)) {
            throw new LocalizedException(__('Pick a file format and a delivery.'));
        }
    }
}
