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

namespace MageBooster\ReporterLite\Controller\Adminhtml\Report;

/**
 * Runs the query and returns the first 100 rows with the row count and run time.
 */
class Run extends AbstractRun
{
    private const PREVIEW = 100;

    /**
     * @inheritdoc
     */
    public function execute()
    {
        try {
            $r = $this->runInput($this->input())['result'];
            $rows = array_map(static function (array $row) {
                return array_map(static function ($v) {
                    return $v === null ? null : (string)$v;
                }, $row);
            }, array_slice($r['rows'], 0, self::PREVIEW));
            return $this->json([
                'ok' => true,
                'columns' => $r['columns'],
                'rows' => $rows,
                'count' => $r['count'],
                'truncated' => $r['truncated'],
                'ms' => $r['ms'],
                'shown' => count($rows),
            ]);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }
}
