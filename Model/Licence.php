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

use MageBooster\License\Api\LicenseInterface;
use Psr\Log\LoggerInterface;

/**
 * Licence state of MageBooster Reporter Lite. Without a valid serial number the module keeps working for
 * 30 days (MageBooster License grace period); after that the Reporter Lite page, scheduled e-mails and
 * download links stop and say that a licence is required. Saved reports and the run history are kept.
 * The non-dismissible admin notice comes from MageBooster License for every registered product.
 */
class Licence
{
    public const CODE = 'reporter-lite';

    /**
     * @var bool|null
     */
    private $active = null;

    /**
     * @param LicenseInterface $license
     * @param LoggerInterface $logger
     */
    public function __construct(private LicenseInterface $license, private LoggerInterface $logger)
    {
    }

    /**
     * Is the module allowed to work (valid serial or still in the grace period)?
     *
     * @return bool
     */
    public function isActive(): bool
    {
        if ($this->active === null) {
            try {
                $this->active = $this->license->isActive(self::CODE);
            } catch (\Throwable $e) {
                $this->logger->error('MageBooster_ReporterLite: licence check failed', ['exception' => $e]);
                $this->active = false;
            }
        }
        return $this->active;
    }

    /**
     * Text shown when the licence is missing and the grace period is over
     *
     * @return string
     */
    public function message(): string
    {
        return (string)__(
            'MageBooster Reporter Lite needs a licence. Enter your serial number in '
            . 'Stores > Configuration > MageBooster > Licenses.'
        );
    }
}
