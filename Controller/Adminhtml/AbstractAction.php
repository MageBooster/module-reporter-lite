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

namespace MageBooster\ReporterLite\Controller\Adminhtml;

use MageBooster\ReporterLite\Model\Licence;
use Magento\Backend\App\Action;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Exception\LocalizedException;

/**
 * Shared helpers for the Reporter Lite admin controllers.
 */
abstract class AbstractAction extends Action
{
    public const ADMIN_RESOURCE = 'MageBooster_ReporterLite::view';

    /**
     * Without an active licence (no valid serial, 30-day grace period over) the pages answer "licence
     * required": JSON 403 for the page script, a redirect to the licence settings otherwise.
     *
     * @param RequestInterface $request
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function dispatch(RequestInterface $request)
    {
        if (!$this->_isAllowed()) {
            return parent::dispatch($request);
        }
        $licence = $this->_objectManager->get(Licence::class);
        if ($licence->isActive()) {
            return parent::dispatch($request);
        }
        $this->_request = $request;
        if ($request->isPost() || $request->getParam('isAjax') || $request->isXmlHttpRequest()) {
            return $this->json(['ok' => false, 'error' => $licence->message(), 'license_required' => true], 403);
        }
        $this->messageManager->addErrorMessage($licence->message());
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $redirect->setPath('adminhtml/system_config/edit', ['section' => 'magebooster_license']);
    }

    /**
     * ACL check
     *
     * @param string $resource
     * @return bool
     */
    protected function allowed(string $resource): bool
    {
        return $this->_authorization->isAllowed($resource);
    }

    /**
     * Throws unless the admin has the resource
     *
     * @param string $resource
     * @return void
     * @throws LocalizedException
     */
    protected function require(string $resource): void
    {
        if (!$this->allowed($resource)) {
            throw new LocalizedException(__('Your admin role does not allow this.'));
        }
    }

    /**
     * Current admin user name
     *
     * @return string|null
     */
    protected function userName(): ?string
    {
        $user = $this->_auth->getUser();
        return $user ? (string)$user->getUserName() : null;
    }

    /**
     * Current admin e-mail
     *
     * @return string|null
     */
    protected function userEmail(): ?string
    {
        $user = $this->_auth->getUser();
        return $user ? (string)$user->getEmail() : null;
    }

    /**
     * JSON response, never cached
     *
     * @param array $data
     * @param int $code
     * @return Json
     */
    protected function json(array $data, int $code = 200): Json
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setHttpResponseCode($code);
        $result->setHeader('Cache-Control', 'no-store', true);
        return $result->setData($data);
    }

    /**
     * Error response: the message for expected errors, a generic text (and a log entry) otherwise
     *
     * @param \Throwable $e
     * @return Json
     */
    protected function fail(\Throwable $e): Json
    {
        if ($e instanceof LocalizedException) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
        $this->_objectManager->get(\Psr\Log\LoggerInterface::class)
            ->error('MageBooster_ReporterLite: ' . $e->getMessage(), ['exception' => $e]);
        return $this->json(['ok' => false, 'error' => (string)__('Something went wrong. The error was logged.')], 500);
    }

    /**
     * The "data" field (JSON) sent by the page
     *
     * @return array
     */
    protected function input(): array
    {
        $raw = $this->getRequest()->getParam('data');
        $data = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($data) ? $data : [];
    }
}
