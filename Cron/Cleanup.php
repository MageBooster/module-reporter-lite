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

namespace MageBooster\ReporterLite\Cron;

use MageBooster\ReporterLite\Model\Config;
use MageBooster\ReporterLite\Model\Files;
use MageBooster\ReporterLite\Model\Repository;

/**
 * Nightly: deletes expired files and download links, files no history row points to, and old history.
 */
class Cleanup
{
    /**
     * @param Repository $repository
     * @param Files $files
     * @param Config $config
     */
    public function __construct(private Repository $repository, private Files $files, private Config $config)
    {
    }

    /**
     * Cron entry point
     *
     * @return array{files:int,history:int}
     */
    public function execute(): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $deleted = 0;
        foreach ($this->repository->expire($now) as $name) {
            $this->files->delete((string)$name);
            $deleted++;
        }
        $live = array_flip($this->repository->liveFiles());
        foreach ($this->files->all() as $name) {
            if (!isset($live[$name])) {
                $this->files->delete($name);
                $deleted++;
            }
        }
        $cut = gmdate('Y-m-d H:i:s', time() - $this->config->historyDays() * 86400);
        return ['files' => $deleted, 'history' => $this->repository->pruneHistory($cut)];
    }
}
