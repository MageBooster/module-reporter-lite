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

use MageBooster\ReporterLite\Model\Delivery;
use MageBooster\ReporterLite\Model\Licence;
use MageBooster\ReporterLite\Model\Repository;
use Psr\Log\LoggerInterface;

/**
 * Every minute: sends the reports whose next send time has come.
 */
class Dispatch
{
    /**
     * @param Repository $repository
     * @param Delivery $delivery
     * @param Licence $licence
     * @param LoggerInterface $logger
     */
    public function __construct(
        private Repository $repository,
        private Delivery $delivery,
        private Licence $licence,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Cron entry point
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->licence->isActive()) {
            return;
        }
        $now = gmdate('Y-m-d H:i:s');
        foreach ($this->repository->claimDue($now) as $id) {
            try {
                $this->delivery->run($id, 'schedule');
            } catch (\Throwable $e) {
                $this->logger->error('MageBooster_ReporterLite: report ' . $id . ': ' . $e->getMessage());
            }
        }
    }
}
