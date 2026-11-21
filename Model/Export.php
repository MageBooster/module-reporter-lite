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

use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;

/**
 * Report result as a file in memory: CSV, Excel (PhpSpreadsheet, if installed), JSON, HTML or PDF (dompdf,
 * if installed). Text cells that a spreadsheet would read as a formula get a leading apostrophe.
 */
class Export
{
    public const TYPES = [
        'csv' => 'text/csv; charset=utf-8',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'json' => 'application/json; charset=utf-8',
        'html' => 'text/html; charset=utf-8',
        'pdf' => 'application/pdf',
    ];

    /**
     * @param Escaper $escaper
     */
    public function __construct(private Escaper $escaper)
    {
    }

    /**
     * Formats this installation can write
     *
     * @return array<string,bool>
     */
    public function available(): array
    {
        return [
            'csv' => true,
            'xlsx' => class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class),
            'json' => true,
            'html' => true,
            'pdf' => class_exists(\Dompdf\Dompdf::class),
        ];
    }

    /**
     * Builds the file
     *
     * @param string $title
     * @param array $result Runner result (columns, rows)
     * @param string $format
     * @param string $note generated-at / period line
     * @return array{name:string,type:string,body:string}
     * @throws LocalizedException
     */
    public function build(string $title, array $result, string $format, string $note = ''): array
    {
        if (empty($this->available()[$format])) {
            throw new LocalizedException(__('This file format is not available on this store.'));
        }
        $keys = array_map(static function ($c) {
            return (string)$c['key'];
        }, $result['columns'] ?? []);
        $rows = $result['rows'] ?? [];
        switch ($format) {
            case 'csv':
                $body = $this->csv($keys, $rows);
                break;
            case 'xlsx':
                $body = $this->xlsx($title, $keys, $rows, $result['columns'] ?? []);
                break;
            case 'json':
                $body = $this->json($keys, $rows);
                break;
            case 'pdf':
                $body = $this->pdf($title, $keys, $rows, $note);
                break;
            default:
                $body = $this->html($title, $keys, $rows, $note);
        }
        return ['name' => $this->fileBase($title) . '.' . $format, 'type' => self::TYPES[$format], 'body' => $body];
    }

    /**
     * File name base from the report name, e.g. "weekly-sales-2026-11-21"
     *
     * @param string $title
     * @return string
     */
    public function fileBase(string $title): string
    {
        $slug = strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
        return substr($slug !== '' ? $slug : 'report', 0, 60) . '-' . gmdate('Y-m-d');
    }

    /**
     * CSV with a UTF-8 BOM so Excel reads accents correctly
     *
     * @param string[] $keys
     * @param array $rows
     * @return string
     */
    private function csv(array $keys, array $rows): string
    {
        $lines = [$this->csvLine(array_map([$this, 'safeCell'], $keys))];
        foreach ($rows as $r) {
            $lines[] = $this->csvLine(array_map([$this, 'safeCell'], $r));
        }
        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    /**
     * One CSV line
     *
     * @param array $cells
     * @return string
     */
    private function csvLine(array $cells): string
    {
        return implode(',', array_map(static function ($v) {
            $v = $v === null ? '' : (string)$v;
            return preg_match('/[",\r\n]/', $v) ? '"' . str_replace('"', '""', $v) . '"' : $v;
        }, $cells));
    }

    /**
     * Excel workbook; numbers stay numbers, text is always stored as text
     *
     * @param string $title
     * @param string[] $keys
     * @param array $rows
     * @param array $columns
     * @return string
     */
    private function xlsx(string $title, array $keys, array $rows, array $columns): string
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(substr((string)preg_replace('#[\\\\/?*\[\]:]#', ' ', $title), 0, 31) ?: 'Report');
        $text = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
        foreach ($keys as $i => $k) {
            $sheet->setCellValueExplicit([$i + 1, 1], $k, $text);
        }
        $sheet->getStyle([1, 1, max(1, count($keys)), 1])->getFont()->setBold(true);
        foreach ($rows as $n => $r) {
            foreach ($r as $i => $v) {
                if ($v === null) {
                    continue;
                }
                if (!empty($columns[$i]['numeric']) && is_numeric($v)) {
                    $sheet->setCellValue([$i + 1, $n + 2], $v + 0);
                } else {
                    $sheet->setCellValueExplicit([$i + 1, $n + 2], (string)$v, $text);
                }
            }
        }
        foreach (array_keys($keys) as $i) {
            $sheet->getColumnDimensionByColumn($i + 1)->setAutoSize(true);
        }
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);
        $stream = fopen('php://memory', 'w+b');
        $writer->save($stream);
        rewind($stream);
        $body = (string)stream_get_contents($stream);
        fclose($stream);
        return $body;
    }

    /**
     * JSON array of objects (column => value)
     *
     * @param string[] $keys
     * @param array $rows
     * @return string
     */
    private function json(array $keys, array $rows): string
    {
        $out = [];
        foreach ($rows as $r) {
            $o = [];
            foreach ($keys as $i => $k) {
                $o[$k] = $r[$i] ?? null;
            }
            $out[] = $o;
        }
        return (string)json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Stand-alone HTML table
     *
     * @param string $title
     * @param string[] $keys
     * @param array $rows
     * @param string $note
     * @return string
     */
    public function html(string $title, array $keys, array $rows, string $note = ''): string
    {
        $e = function ($v): string {
            return $this->escaper->escapeHtml($v === null ? '' : (string)$v);
        };
        $head = implode('', array_map(static function ($k) use ($e) {
            return '<th>' . $e($k) . '</th>';
        }, $keys));
        $body = '';
        foreach ($rows as $r) {
            $body .= '<tr>' . implode('', array_map(static function ($v) use ($e) {
                return '<td>' . $e($v) . '</td>';
            }, $r)) . '</tr>';
        }
        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $e($title) . '</title>'
            . '<style>body{font-family:Arial,Helvetica,sans-serif;color:#17111f;margin:24px}'
            . 'h1{font-size:20px;margin:0 0 4px}p{color:#5c5170;font-size:12px;margin:0 0 16px}'
            . 'table{border-collapse:collapse;font-size:12px}th{background:#160040;color:#fff;text-align:left}'
            . 'th,td{padding:6px 10px;border-bottom:1px solid #e9e3f1}tr:nth-child(even) td{background:#f8f5fb}'
            . '</style></head><body><h1>' . $e($title) . '</h1><p>' . $e($note) . '</p>'
            . '<table><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table></body></html>';
    }

    /**
     * PDF from the HTML table (dompdf, remote resources off)
     *
     * @param string $title
     * @param string[] $keys
     * @param array $rows
     * @param string $note
     * @return string
     */
    private function pdf(string $title, array $keys, array $rows, string $note): string
    {
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new \Dompdf\Dompdf($options);
        $pdf->loadHtml($this->html($title, $keys, $rows, $note), 'UTF-8');
        $pdf->setPaper('A4', count($keys) > 6 ? 'landscape' : 'portrait');
        $pdf->render();
        return (string)$pdf->output();
    }

    /**
     * Guards against spreadsheet formula injection
     *
     * @param mixed $v
     * @return mixed
     */
    public function safeCell($v)
    {
        if (is_string($v) && $v !== '' && !is_numeric($v) && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $v;
        }
        return $v;
    }
}
