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
use MageBooster\ReporterLite\Model\Repository;
use MageBooster\ReporterLite\Model\Schedule;
use MageBooster\ReporterLite\Model\Sql\Validator;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Creates or updates a report. Schedule fields need the "Schedule and send" permission.
 */
class Save extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageBooster_ReporterLite::edit';

    /**
     * @param Context $context
     * @param Repository $repository
     * @param Validator $validator
     * @param Schedule $schedule
     * @param Delivery $delivery
     * @param Presenter $presenter
     */
    public function __construct(
        Context $context,
        private Repository $repository,
        private Validator $validator,
        private Schedule $schedule,
        private Delivery $delivery,
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
            $in = $this->input();
            $id = (int)($in['id'] ?? 0) ?: null;
            $current = $id ? $this->repository->get($id) : null;
            $name = trim((string)($in['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 255) {
                throw new LocalizedException(__('Give the report a name.'));
            }
            $sql = (string)($in['query'] ?? '');
            $check = $this->validator->check($sql);
            $params = [];
            foreach ($check['params'] as $p) {
                $params[$p] = mb_substr(trim((string)($in['params'][$p] ?? '')), 0, 500);
            }
            $data = [
                'name' => $name,
                'description' => mb_substr(trim((string)($in['description'] ?? '')), 0, 2000),
                'query' => $sql,
                'query_parameters' => $params ?: new \stdClass(),
            ];
            if (is_array($data['query_parameters'])) {
                $data['query_parameters'] = json_encode($params, JSON_UNESCAPED_UNICODE);
            } else {
                $data['query_parameters'] = '{}';
            }
            if ($this->allowed('MageBooster_ReporterLite::schedule')) {
                $data += $this->scheduleFields($in);
            } elseif (!$current) {
                $data['cron_status'] = 0;
            }
            $id = $this->repository->save($data, $id);
            $saved = $this->repository->get($id);
            $this->repository->touch($id, ['next_run_at' => $this->delivery->nextUtc($saved)]);
            return $this->json([
                'ok' => true,
                'report' => $this->presenter->full($id),
                'items' => $this->presenter->items(),
            ]);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    /**
     * Schedule columns from the page
     *
     * @param array $in
     * @return array
     * @throws LocalizedException
     */
    private function scheduleFields(array $in): array
    {
        $on = !empty($in['cron_status']);
        $schedule = $this->schedule->normalise(is_array($in['schedule'] ?? null) ? $in['schedule'] : []);
        $recipients = [];
        foreach ((array)($in['recipients'] ?? []) as $email) {
            $email = trim((string)$email);
            if ($email === '') {
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new LocalizedException(__('"%1" is not a valid e-mail address.', $email));
            }
            $recipients[strtolower($email)] = $email;
        }
        if (count($recipients) > 50) {
            throw new LocalizedException(__('Up to 50 recipients.'));
        }
        if ($on && !$recipients) {
            throw new LocalizedException(__('Add at least one recipient to send automatically.'));
        }
        $type = (string)($in['export_type'] ?? 'xlsx');
        $delivery = (string)($in['delivery'] ?? 'attach');
        $this->repository->assertOptions($type, $delivery);
        return [
            'cron_status' => $on ? 1 : 0,
            'is_active' => 1,
            'schedule' => json_encode($schedule),
            'cron_expression' => $schedule['cron'],
            'recipients' => implode(',', array_values($recipients)),
            'export_type' => $type,
            'delivery' => $delivery,
            'skip_if_empty' => !empty($in['skip_if_empty']) ? 1 : 0,
        ];
    }
}
