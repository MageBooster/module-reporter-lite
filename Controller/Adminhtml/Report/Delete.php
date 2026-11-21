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

use MageBooster\ReporterLite\Controller\Adminhtml\AbstractAction;
use MageBooster\ReporterLite\Model\Files;
use MageBooster\ReporterLite\Model\Presenter;
use MageBooster\ReporterLite\Model\Repository;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;

/**
 * Deletes a report, its history and its files.
 */
class Delete extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageBooster_ReporterLite::edit';

    /**
     * @param Context $context
     * @param Repository $repository
     * @param Files $files
     * @param Presenter $presenter
     */
    public function __construct(
        Context $context,
        private Repository $repository,
        private Files $files,
        private Presenter $presenter
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        try {
            $id = (int)($this->input()['id'] ?? 0);
            $this->repository->get($id);
            foreach ($this->repository->delete($id) as $file) {
                $this->files->delete((string)$file);
            }
            return $this->json(['ok' => true, 'items' => $this->presenter->items()]);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }
}
