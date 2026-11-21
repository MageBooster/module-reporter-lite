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

namespace MageBooster\ReporterLite\Controller\File;

use MageBooster\ReporterLite\Model\Export;
use MageBooster\ReporterLite\Model\Files;
use MageBooster\ReporterLite\Model\Licence;
use MageBooster\ReporterLite\Model\Repository;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Download link from a report e-mail: /mbreporterlite/file/get?t=<token>. The token is random, only its
 * hash is stored, and it stops working when the link expires.
 */
class Get implements HttpGetActionInterface
{
    /**
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param Repository $repository
     * @param Files $files
     * @param Export $export
     * @param Licence $licence
     */
    public function __construct(
        private RequestInterface $request,
        private ResultFactory $resultFactory,
        private Repository $repository,
        private Files $files,
        private Export $export,
        private Licence $licence
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Raw $raw */
        $raw = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $raw->setHeader('Cache-Control', 'no-store', true)
            ->setHeader('X-Robots-Tag', 'noindex, nofollow', true)
            ->setHeader('X-Content-Type-Options', 'nosniff', true)
            ->setHeader('Referrer-Policy', 'no-referrer', true);
        $hash = $this->files->hashToken((string)$this->request->getParam('t'));
        $run = $hash && $this->licence->isActive() ? $this->repository->runByToken($hash, gmdate('Y-m-d H:i:s')) : null;
        $body = $run && $run['file'] ? $this->files->read((string)$run['file']) : null;
        if ($body === null) {
            $raw->setHttpResponseCode(404)->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
            return $raw->setContents((string)__('This link has expired or is not valid.'));
        }
        $ext = pathinfo((string)$run['file'], PATHINFO_EXTENSION);
        $name = $this->export->fileBase((string)$run['name']) . '.' . $ext;
        return $raw->setHeader('Content-Type', Export::TYPES[$ext] ?? 'application/octet-stream', true)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"', true)
            ->setHeader('Content-Length', (string)strlen($body), true)
            ->setContents($body);
    }
}
