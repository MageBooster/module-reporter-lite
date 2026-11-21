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
use MageBooster\ReporterLite\Model\Export;
use MageBooster\ReporterLite\Model\Files;
use MageBooster\ReporterLite\Model\Repository;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * File of a past run (from the history tab), while it is kept.
 */
class Download extends AbstractAction implements HttpGetActionInterface
{
    /**
     * @param Context $context
     * @param Repository $repository
     * @param Files $files
     * @param Export $export
     */
    public function __construct(
        Context $context,
        private Repository $repository,
        private Files $files,
        private Export $export
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $run = $this->repository->run((int)$this->getRequest()->getParam('run'));
        $body = $run && $run['file'] && (string)$run['expires_at'] > gmdate('Y-m-d H:i:s')
            ? $this->files->read((string)$run['file']) : null;
        if ($body === null) {
            $this->messageManager->addErrorMessage(__('This file is no longer kept.'));
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/index');
        }
        $report = $this->repository->get((int)$run['report_id']);
        $ext = pathinfo((string)$run['file'], PATHINFO_EXTENSION);
        $name = $this->export->fileBase((string)$report['name']) . '.' . $ext;
        return $this->send($name, Export::TYPES[$ext] ?? 'application/octet-stream', $body);
    }

    /**
     * Raw file response
     *
     * @param string $name
     * @param string $type
     * @param string $body
     * @return \Magento\Framework\Controller\Result\Raw
     */
    private function send(string $name, string $type, string $body)
    {
        /** @var \Magento\Framework\Controller\Result\Raw $raw */
        $raw = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $raw->setHeader('Content-Type', $type, true)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"', true)
            ->setHeader('Content-Length', (string)strlen($body), true)
            ->setHeader('Cache-Control', 'no-store', true)
            ->setHeader('X-Content-Type-Options', 'nosniff', true);
        return $raw->setContents($body);
    }
}
