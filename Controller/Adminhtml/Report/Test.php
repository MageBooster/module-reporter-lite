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
use MageBooster\ReporterLite\Model\Delivery;
use MageBooster\ReporterLite\Model\Presenter;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * "Send a test to me": runs the saved report and mails it to the signed-in admin.
 */
class Test extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageBooster_ReporterLite::schedule';

    /**
     * @param Context $context
     * @param Delivery $delivery
     * @param Presenter $presenter
     */
    public function __construct(Context $context, private Delivery $delivery, private Presenter $presenter)
    {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        try {
            $id = (int)($this->input()['id'] ?? 0);
            $email = $this->userEmail();
            if (!$email) {
                throw new LocalizedException(__('Your admin account has no e-mail address.'));
            }
            $r = $this->delivery->run($id, 'test', $email, null, $this->userName());
            return $this->json([
                'ok' => $r['result'] !== 'failed',
                'error' => $r['result'] === 'failed' ? $r['message'] : null,
                'message' => $r['message'],
                'report' => $this->presenter->full($id),
            ]);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }
}
