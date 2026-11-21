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

use Magento\Backend\Model\UrlInterface;

/**
 * Report data for the admin page (list items, the open report, history).
 */
class Presenter
{
    /**
     * @param Repository $repository
     * @param Schedule $schedule
     * @param Delivery $delivery
     * @param Config $config
     * @param UrlInterface $url admin URL builder
     */
    public function __construct(
        private Repository $repository,
        private Schedule $schedule,
        private Delivery $delivery,
        private Config $config,
        private UrlInterface $url
    ) {
    }

    /**
     * All reports for the left column
     *
     * @return array[]
     */
    public function items(): array
    {
        return array_map([$this, 'item'], $this->repository->all());
    }

    /**
     * One list item
     *
     * @param array $r
     * @return array
     */
    public function item(array $r): array
    {
        $scheduled = $r['cron_status'] && $r['is_active'] && !empty($r['cron_expression']);
        $paused = !$scheduled && !empty($r['cron_expression']);
        if ($scheduled) {
            $meta = $this->schedule->describe($r['schedule']);
            $n = count($r['recipients']);
            $meta .= ' · ' . ($r['skip_if_empty'] ? __('only if rows') : ($n === 1 ? __('1 person') : __('%1 people', $n)));
        } elseif ($paused) {
            $meta = (string)__('Paused');
        } else {
            $meta = (string)__('Run by hand');
        }
        return [
            'id' => (int)$r['report_id'],
            'name' => (string)$r['name'],
            'meta' => (string)$meta,
            'state' => $scheduled ? 'scheduled' : ($paused ? 'paused' : 'manual'),
            'icon' => $this->icon((string)$r['query']),
        ];
    }

    /**
     * The open report with stats and history
     *
     * @param int $id
     * @return array
     */
    public function full(int $id): array
    {
        $r = $this->repository->get($id);
        $schedule = $r['schedule'] + ['frequency' => 'weekly', 'day' => 1, 'time' => '08:00', 'range' => 'saved',
            'cron' => (string)$r['cron_expression']];
        return [
            'id' => (int)$r['report_id'],
            'name' => (string)$r['name'],
            'description' => (string)$r['description'],
            'query' => (string)$r['query'],
            'params' => (object)$r['query_parameters'],
            'cron_status' => (int)$r['cron_status'],
            'schedule' => $schedule,
            'recipients' => $r['recipients'],
            'export_type' => (string)$r['export_type'],
            'delivery' => (string)$r['delivery'],
            'skip_if_empty' => (int)$r['skip_if_empty'],
            'stats' => [
                'last_run' => $this->local($r['last_run_at']),
                'next_run' => $r['cron_status'] && $r['next_run_at'] ? $this->local($r['next_run_at']) : null,
                'recipients' => count($r['recipients']),
                'last_result' => $r['last_result'],
                'last_rows' => $r['last_rows'],
            ],
            'next' => $r['cron_status'] && !empty($r['cron_expression'])
                ? $this->delivery->preview((string)$r['cron_expression'], (string)$schedule['range']) : null,
            'item' => $this->item($r),
            'history' => $this->history((int)$r['report_id']),
        ];
    }

    /**
     * Run history rows
     *
     * @param int $reportId
     * @return array[]
     */
    public function history(int $reportId): array
    {
        $out = [];
        $now = gmdate('Y-m-d H:i:s');
        foreach ($this->repository->history($reportId) as $h) {
            $hasFile = !empty($h['file']) && (string)$h['expires_at'] > $now;
            $out[] = [
                'id' => (int)$h['run_id'],
                'when' => $this->local($h['run_at']),
                'trigger' => (string)$h['run_trigger'],
                'rows' => (int)$h['row_count'],
                'result' => (string)$h['result'],
                'message' => (string)$h['message'],
                'file' => $hasFile ? pathinfo((string)$h['file'], PATHINFO_EXTENSION) : null,
                'download' => $hasFile ? $this->url->getUrl('mbreporterlite/report/download', ['run' => $h['run_id']]) : null,
                'by' => $h['admin_user'],
            ];
        }
        return $out;
    }

    /**
     * UTC date-time as "17 Nov, 08:00" in store time
     *
     * @param string|null $utc
     * @return string|null
     */
    public function local(?string $utc): ?string
    {
        if (!$utc) {
            return null;
        }
        $d = new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
        $d = $d->setTimezone(new \DateTimeZone($this->config->timezone()));
        $fmt = $d->format('Y') === gmdate('Y') ? 'j M, H:i' : 'j M Y, H:i';
        return $d->format($fmt);
    }

    /**
     * Icon name from the main table of the query
     *
     * @param string $sql
     * @return string
     */
    private function icon(string $sql): string
    {
        $s = strtolower($sql);
        $map = ['coupon' => 'tag', 'quote' => 'cart', 'stock' => 'box', 'inventory' => 'box',
            'customer_entity' => 'user', 'sales_order_item' => 'star', 'sales_order' => 'chart', 'creditmemo' => 'refund'];
        foreach ($map as $needle => $icon) {
            if (str_contains($s, $needle)) {
                return $icon;
            }
        }
        return 'table';
    }
}
