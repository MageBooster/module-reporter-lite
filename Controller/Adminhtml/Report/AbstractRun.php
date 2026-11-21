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
use MageBooster\ReporterLite\Model\Params;
use MageBooster\ReporterLite\Model\Repository;
use MageBooster\ReporterLite\Model\Sql\Runner;
use MageBooster\ReporterLite\Model\Sql\Validator;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;

/**
 * Runs the query on the page (saved or not). A changed or new query needs the "Create and edit" permission.
 */
abstract class AbstractRun extends AbstractAction implements HttpPostActionInterface
{
    /**
     * @param Context $context
     * @param Repository $repository
     * @param Validator $validator
     * @param Params $params
     * @param Runner $runner
     */
    public function __construct(
        Context $context,
        protected Repository $repository,
        protected Validator $validator,
        protected Params $params,
        protected Runner $runner
    ) {
        parent::__construct($context);
    }

    /**
     * Runs the query from the page input
     *
     * @param array $in
     * @return array{name:string,result:array}
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function runInput(array $in): array
    {
        $sql = (string)($in['query'] ?? '');
        $id = (int)($in['id'] ?? 0);
        $saved = $id ? $this->repository->get($id) : null;
        if (!$saved || trim((string)$saved['query']) !== trim($sql)) {
            $this->require('MageBooster_ReporterLite::edit');
        }
        $names = $this->validator->check($sql)['params'];
        $raw = is_array($in['params'] ?? null) ? $in['params'] : [];
        $values = $this->params->resolve($names, $raw);
        $name = trim((string)($in['name'] ?? '')) ?: ($saved ? (string)$saved['name'] : (string)__('Report'));
        return ['name' => $name, 'result' => $this->runner->run($sql, $values)];
    }
}
