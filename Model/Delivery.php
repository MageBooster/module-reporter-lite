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

use MageBooster\ReporterLite\Model\Sql\Runner;
use MageBooster\ReporterLite\Model\Sql\Validator;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Url;
use Psr\Log\LoggerInterface;

/**
 * Runs a saved report and e-mails the file (attached, or as a download link valid for some days).
 * Used by the schedule (cron), "Send a test to me" and the console command. Every run is written to history.
 */
class Delivery
{
    /**
     * @param Repository $repository
     * @param Runner $runner
     * @param Validator $validator
     * @param Params $params
     * @param Schedule $schedule
     * @param Export $export
     * @param Files $files
     * @param Mailer $mailer
     * @param Config $config
     * @param Url $url frontend URL builder (links work for recipients without an admin login)
     * @param Escaper $escaper
     * @param LoggerInterface $logger
     */
    public function __construct(
        private Repository $repository,
        private Runner $runner,
        private Validator $validator,
        private Params $params,
        private Schedule $schedule,
        private Export $export,
        private Files $files,
        private Mailer $mailer,
        private Config $config,
        private Url $url,
        private Escaper $escaper,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Runs and sends a report
     *
     * @param int $reportId
     * @param string $trigger schedule or test
     * @param string|null $testTo address for a test send
     * @param string|null $emlDir write the e-mail as a .eml file into this folder instead of sending
     * @param string|null $adminUser
     * @return array{result:string,message:string,rows:int,run_id:int}
     */
    public function run(
        int $reportId,
        string $trigger,
        ?string $testTo = null,
        ?string $emlDir = null,
        ?string $adminUser = null
    ): array {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $report = $this->repository->get($reportId);
        $rows = 0;
        $file = null;
        $hash = null;
        $expires = null;
        try {
            $range = (string)($report['schedule']['range'] ?? 'saved');
            $names = $this->validator->check((string)$report['query'])['params'];
            $values = $this->params->resolve($names, $report['query_parameters'], $range, $now);
            $result = $this->runner->run((string)$report['query'], $values);
            $rows = (int)$result['count'];
            $period = $this->periodText($names, $range, $now);
            if ($rows === 0 && (int)$report['skip_if_empty'] === 1 && $trigger === 'schedule') {
                $out = ['skipped', (string)__('Skipped, no rows')];
            } else {
                $to = $trigger === 'test' ? [(string)$testTo] : $report['recipients'];
                $to = array_values(array_filter($to, static function ($e) {
                    return (bool)filter_var($e, FILTER_VALIDATE_EMAIL);
                }));
                if (!$to) {
                    throw new LocalizedException(__('There is no valid recipient.'));
                }
                $format = (string)$report['export_type'];
                if (empty($this->export->available()[$format])) {
                    $format = 'csv';
                }
                $note = (string)__('Generated %1 UTC', $now->format('Y-m-d H:i')) . ($period ? ' · ' . $period : '');
                $doc = $this->export->build((string)$report['name'], $result, $format, $note);
                $file = $this->files->save($doc['body'], $format);
                [$token, $hash] = $this->files->newToken();
                $expires = $now->modify('+' . $this->config->linkDays() . ' days')->format('Y-m-d H:i:s');
                $asLink = $report['delivery'] === 'link' || strlen($doc['body']) > $this->config->maxAttachBytes();
                $link = $asLink ? $this->link($token) : null;
                [$html, $text] = $this->body($report, $result, $period, $link, $trigger === 'test');
                $subject = ($trigger === 'test' ? '[' . __('Test') . '] ' : '') . $report['name']
                    . ($period ? ' · ' . $period : '');
                $eml = $emlDir !== null
                    ? rtrim($emlDir, '/') . '/report-' . $reportId . '-' . $now->format('Ymd-His') . '-' . $trigger . '-' . bin2hex(random_bytes(3)) . '.eml'
                    : null;
                $this->mailer->send($to, $subject, $html, $text, $asLink ? null : $doc, $eml);
                $out = $trigger === 'test'
                    ? ['sent', (string)__('Test sent to %1', $to[0])]
                    : ['sent', (string)__('Sent to %1 people', count($to))];
                if (count($to) === 1 && $trigger !== 'test') {
                    $out[1] = (string)__('Sent to 1 person');
                }
            }
        } catch (\Throwable $e) {
            if (!$e instanceof LocalizedException) {
                $this->logger->error('MageBooster_ReporterLite: ' . $e->getMessage(), ['exception' => $e]);
            }
            $msg = $e instanceof LocalizedException ? $e->getMessage() : (string)__('Something went wrong. The error was logged.');
            $out = ['failed', mb_substr($msg, 0, 480)];
            if ($file !== null) {
                $this->files->delete($file);
                $file = $hash = $expires = null;
            }
        }
        $runId = $this->repository->addRun([
            'report_id' => $reportId,
            'run_at' => $now->format('Y-m-d H:i:s'),
            'run_trigger' => $trigger,
            'row_count' => $rows,
            'result' => $out[0],
            'message' => $out[1],
            'file' => $file,
            'token_hash' => $hash,
            'expires_at' => $expires,
            'admin_user' => $adminUser,
        ]);
        $touch = ['last_run_at' => $now->format('Y-m-d H:i:s'), 'last_result' => $out[1], 'last_rows' => $rows];
        if ($trigger === 'schedule') {
            $touch['next_run_at'] = $this->nextUtc($report, $now);
        }
        $this->repository->touch($reportId, $touch);
        return ['result' => $out[0], 'message' => $out[1], 'rows' => $rows, 'run_id' => $runId];
    }

    /**
     * Next send in UTC (null when the schedule is off)
     *
     * @param array $report
     * @param \DateTimeImmutable|null $after
     * @return string|null
     */
    public function nextUtc(array $report, ?\DateTimeImmutable $after = null): ?string
    {
        if (empty($report['cron_status']) || empty($report['is_active']) || empty($report['cron_expression'])) {
            return null;
        }
        $next = $this->schedule->next((string)$report['cron_expression'], $after);
        return $next ? $next->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s') : null;
    }

    /**
     * "Next send" line for the schedule tab
     *
     * @param string $cron
     * @param string $range
     * @return array{next:string|null,covering:string|null}
     */
    public function preview(string $cron, string $range): array
    {
        $next = $this->schedule->next($cron);
        if (!$next) {
            return ['next' => null, 'covering' => null];
        }
        $covering = $range !== 'saved' ? $this->params->rangeLabel($this->params->range($range, $next)) : null;
        return ['next' => $next->format('l j M, H:i'), 'covering' => $covering];
    }

    /**
     * Period the dates cover, e.g. "17–23 Nov"
     *
     * @param string[] $names
     * @param string $range
     * @param \DateTimeImmutable $now
     * @return string
     */
    private function periodText(array $names, string $range, \DateTimeImmutable $now): string
    {
        $hasDates = false;
        foreach ($names as $n) {
            if ($this->params->kind($n) === 'date') {
                $hasDates = true;
            }
        }
        if (!$hasDates || $range === 'saved') {
            return '';
        }
        $local = $now->setTimezone(new \DateTimeZone($this->config->timezone()));
        return $this->params->rangeLabel($this->params->range($range, $local));
    }

    /**
     * Public download link for a token
     *
     * @param string $token
     * @return string
     */
    private function link(string $token): string
    {
        return $this->url->getUrl('mbreporterlite/file/get', ['t' => $token, '_nosid' => true]);
    }

    /**
     * HTML and text body: summary and the first rows
     *
     * @param array $report
     * @param array $result
     * @param string $period
     * @param string|null $link
     * @param bool $test
     * @return array{0:string,1:string}
     */
    private function body(array $report, array $result, string $period, ?string $link, bool $test): array
    {
        $e = function ($v): string {
            return $this->escaper->escapeHtml($v === null ? '' : (string)$v);
        };
        $count = (int)$result['count'];
        $summary = $count === 0 ? (string)__('No rows this time.')
            : (string)__('%1 rows%2.', $count, $result['truncated'] ? ' (' . __('limit reached') . ')' : '');
        $fileLine = $link
            ? (string)__('Download the file (the link works for %1 days):', $this->config->linkDays())
            : (string)__('The file is attached.');
        $keys = array_map(static function ($c) {
            return $c['key'];
        }, $result['columns']);
        $preview = array_slice($result['rows'], 0, 10);
        $table = '';
        if ($preview) {
            $table = '<table style="border-collapse:collapse;font-size:13px;margin:12px 0"><tr>';
            foreach ($keys as $k) {
                $table .= '<th style="background:#160040;color:#fff;text-align:left;padding:6px 10px">' . $e($k) . '</th>';
            }
            $table .= '</tr>';
            foreach ($preview as $r) {
                $table .= '<tr>';
                foreach ($r as $v) {
                    $table .= '<td style="padding:6px 10px;border-bottom:1px solid #e9e3f1">' . $e($v) . '</td>';
                }
                $table .= '</tr>';
            }
            $table .= '</table>';
            if ($count > 10) {
                $table .= '<p style="color:#5c5170;font-size:12px">' . $e(__('First 10 rows shown.')) . '</p>';
            }
        }
        $html = '<div style="font-family:Arial,Helvetica,sans-serif;color:#17111f;max-width:720px">'
            . ($test ? '<p style="background:#e9e3f1;padding:8px 12px;border-radius:8px">'
                . $e(__('This is a test send. The scheduled e-mail goes to the report recipients.')) . '</p>' : '')
            . '<h2 style="margin:0 0 4px;font-size:20px">' . $e($report['name']) . '</h2>'
            . ($period ? '<p style="color:#5c5170;margin:0 0 8px">' . $e(__('Covering %1', $period)) . '</p>' : '')
            . (trim((string)$report['description']) !== '' ? '<p>' . $e($report['description']) . '</p>' : '')
            . '<p><b>' . $e($summary) . '</b> ' . $e($fileLine)
            . ($link ? ' <a href="' . $e($link) . '" style="color:#7a0d8e">' . $e(__('Download')) . '</a>' : '') . '</p>'
            . $table
            . '<p style="color:#94a3b8;font-size:11px;margin-top:24px">' . $e(__('Sent by MageBooster Reporter Lite.')) . '</p>'
            . '</div>';
        $text = $report['name'] . ($period ? ' — ' . $period : '') . "\n\n" . $summary . ' ' . $fileLine
            . ($link ? "\n" . $link : '') . "\n";
        return [$html, $text];
    }
}
