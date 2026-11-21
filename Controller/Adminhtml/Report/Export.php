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

use MageBooster\ReporterLite\Model\Export as Exporter;
use MageBooster\ReporterLite\Model\Params;
use MageBooster\ReporterLite\Model\Repository;
use MageBooster\ReporterLite\Model\Sql\Runner;
use MageBooster\ReporterLite\Model\Sql\Validator;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

/**
 * Runs the query and returns the whole result as a file (CSV, Excel, JSON, HTML or PDF).
 */
class Export extends AbstractRun
{
    /**
     * @param Context $context
     * @param Repository $repository
     * @param Validator $validator
     * @param Params $params
     * @param Runner $runner
     * @param Exporter $exporter
     */
    public function __construct(
        Context $context,
        Repository $repository,
        Validator $validator,
        Params $params,
        Runner $runner,
        private Exporter $exporter
    ) {
        parent::__construct($context, $repository, $validator, $params, $runner);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        try {
            $in = $this->input();
            $run = $this->runInput($in);
            $note = (string)__('Generated %1 UTC', gmdate('Y-m-d H:i'));
            $file = $this->exporter->build($run['name'], $run['result'], (string)($in['format'] ?? 'csv'), $note);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
        /** @var \Magento\Framework\Controller\Result\Raw $raw */
        $raw = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $raw->setHeader('Content-Type', $file['type'], true)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $file['name'] . '"', true)
            ->setHeader('Content-Length', (string)strlen($file['body']), true)
            ->setHeader('Cache-Control', 'no-store', true)
            ->setHeader('X-Content-Type-Options', 'nosniff', true)
            ->setHeader('X-Row-Count', (string)$run['result']['count'], true);
        return $raw->setContents($file['body']);
    }
}
