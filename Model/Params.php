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

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Query parameters (:name). Dates take a preset ("30 days ago") or a date in the store timezone and become a
 * UTC date-time, the start of the day for :from-like names and the end of the day for :to-like names.
 * :store / :store_id is a store view id. Everything else is a value the user types. Values are always bound.
 */
class Params
{
    public const PRESETS = [
        '@today' => 'Today',
        '@yesterday' => 'Yesterday',
        '@days_ago_7' => '7 days ago',
        '@days_ago_30' => '30 days ago',
        '@days_ago_90' => '90 days ago',
        '@week_start' => 'Start of this week',
        '@prev_week_start' => 'Start of previous week',
        '@prev_week_end' => 'End of previous week',
        '@month_start' => 'Start of this month',
        '@prev_month_start' => 'Start of previous month',
        '@prev_month_end' => 'End of previous month',
        '@year_start' => 'Start of this year',
    ];

    public const RANGES = [
        'saved' => 'The dates in the parameters',
        'prev_day' => 'Previous day',
        'prev_week' => 'Previous week (Mon–Sun)',
        'prev_month' => 'Previous month',
        'last_7' => 'Last 7 days',
        'last_30' => 'Last 30 days',
        'week_to_date' => 'This week so far',
        'month_to_date' => 'This month so far',
    ];

    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(private Config $config, private StoreManagerInterface $storeManager)
    {
    }

    /**
     * Kind of a parameter: date, store or text
     *
     * @param string $name
     * @return string
     */
    public function kind(string $name): string
    {
        $n = strtolower($name);
        if ($n === 'store' || $n === 'store_id') {
            return 'store';
        }
        if (preg_match('/^(from|to|start|end|since|until|date|day)$|_(from|to|date|start|end|since|until|day)$|^(from|to|date|start|end)_/', $n)) {
            return 'date';
        }
        return 'text';
    }

    /**
     * Is this a date that ends a period (end of day)?
     *
     * @param string $name
     * @return bool
     */
    public function isEnd(string $name): bool
    {
        return (bool)preg_match('/^(to|end|until)$|_(to|end|until)$|^(to|end)_/', strtolower($name));
    }

    /**
     * Default value for a new parameter
     *
     * @param string $name
     * @return string
     */
    public function defaultValue(string $name): string
    {
        switch ($this->kind($name)) {
            case 'date':
                return $this->isEnd($name) ? '@today' : '@days_ago_30';
            case 'store':
                $store = $this->storeManager->getDefaultStoreView();
                return $store ? (string)$store->getId() : '1';
            default:
                return '';
        }
    }

    /**
     * Values to bind, by name
     *
     * @param string[] $names parameters used in the query
     * @param array<string,mixed> $raw saved or typed values
     * @param string $range schedule date range, 'saved' keeps the parameter dates
     * @param \DateTimeImmutable|null $now run time
     * @return array<string,string>
     * @throws LocalizedException
     */
    public function resolve(array $names, array $raw, string $range = 'saved', ?\DateTimeImmutable $now = null): array
    {
        $zone = new \DateTimeZone($this->config->timezone());
        $now = ($now ?? new \DateTimeImmutable('now'))->setTimezone($zone);
        $period = $range !== 'saved' ? $this->range($range, $now) : null;
        $out = [];
        foreach ($names as $name) {
            $value = trim((string)($raw[$name] ?? ''));
            $kind = $this->kind($name);
            if ($kind === 'date') {
                $end = $this->isEnd($name);
                if ($period !== null && ($end || preg_match('/(^|_)(from|start|since)($|_)/', $name))) {
                    $day = $end ? $period[1] : $period[0];
                } else {
                    $day = $this->day($value === '' ? $this->defaultValue($name) : $value, $now, $name);
                }
                $local = $day->setTime($end ? 23 : 0, $end ? 59 : 0, $end ? 59 : 0);
                $out[$name] = $local->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            } elseif ($kind === 'store') {
                if ($value === '') {
                    $value = $this->defaultValue($name);
                }
                if (!ctype_digit($value)) {
                    throw new LocalizedException(__('Pick a store view for :%1.', $name));
                }
                $out[$name] = $value;
            } else {
                if (mb_strlen($value) > 500) {
                    throw new LocalizedException(__('The value for :%1 is too long.', $name));
                }
                $out[$name] = $value;
            }
        }
        return $out;
    }

    /**
     * First and last day of a schedule range, in the store timezone
     *
     * @param string $range
     * @param \DateTimeImmutable $now
     * @return array{0:\DateTimeImmutable,1:\DateTimeImmutable}
     */
    public function range(string $range, \DateTimeImmutable $now): array
    {
        $today = $now->setTime(0, 0);
        $weekStart = $today->modify('-' . ((int)$today->format('N') - 1) . ' days');
        $monthStart = $today->modify('first day of this month');
        switch ($range) {
            case 'prev_day':
                return [$today->modify('-1 day'), $today->modify('-1 day')];
            case 'prev_week':
                return [$weekStart->modify('-7 days'), $weekStart->modify('-1 day')];
            case 'prev_month':
                return [$monthStart->modify('-1 month'), $monthStart->modify('-1 day')];
            case 'last_7':
                return [$today->modify('-7 days'), $today->modify('-1 day')];
            case 'last_30':
                return [$today->modify('-30 days'), $today->modify('-1 day')];
            case 'week_to_date':
                return [$weekStart, $today];
            case 'month_to_date':
                return [$monthStart, $today];
            default:
                return [$today, $today];
        }
    }

    /**
     * Short label for a range, e.g. "17–23 Nov"
     *
     * @param array{0:\DateTimeImmutable,1:\DateTimeImmutable} $period
     * @return string
     */
    public function rangeLabel(array $period): string
    {
        [$a, $b] = $period;
        if ($a->format('Y-m-d') === $b->format('Y-m-d')) {
            return $a->format('j M');
        }
        if ($a->format('Y-m') === $b->format('Y-m')) {
            return $a->format('j') . '–' . $b->format('j M');
        }
        if ($a->format('Y') === $b->format('Y')) {
            return $a->format('j M') . ' – ' . $b->format('j M');
        }
        return $a->format('j M Y') . ' – ' . $b->format('j M Y');
    }

    /**
     * Store views for the :store field
     *
     * @return array<int,array{value:string,label:string}>
     */
    public function storeOptions(): array
    {
        $out = [];
        foreach ($this->storeManager->getStores(false) as $store) {
            $website = $this->storeManager->getWebsite($store->getWebsiteId());
            $out[] = ['value' => (string)$store->getId(), 'label' => $website->getName() . ' / ' . $store->getName()];
        }
        return $out;
    }

    /**
     * Local day for a preset or a typed date
     *
     * @param string $value
     * @param \DateTimeImmutable $now
     * @param string $name
     * @return \DateTimeImmutable
     * @throws LocalizedException
     */
    private function day(string $value, \DateTimeImmutable $now, string $name): \DateTimeImmutable
    {
        $today = $now->setTime(0, 0);
        $weekStart = $today->modify('-' . ((int)$today->format('N') - 1) . ' days');
        $monthStart = $today->modify('first day of this month');
        switch ($value) {
            case '@today':
                return $today;
            case '@yesterday':
                return $today->modify('-1 day');
            case '@days_ago_7':
                return $today->modify('-7 days');
            case '@days_ago_30':
                return $today->modify('-30 days');
            case '@days_ago_90':
                return $today->modify('-90 days');
            case '@week_start':
                return $weekStart;
            case '@prev_week_start':
                return $weekStart->modify('-7 days');
            case '@prev_week_end':
                return $weekStart->modify('-1 day');
            case '@month_start':
                return $monthStart;
            case '@prev_month_start':
                return $monthStart->modify('-1 month');
            case '@prev_month_end':
                return $monthStart->modify('-1 day');
            case '@year_start':
                return $today->setDate((int)$today->format('Y'), 1, 1);
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10), $now->getTimezone());
        if (!$d || $d->format('Y-m-d') !== substr($value, 0, 10)) {
            throw new LocalizedException(__('Enter a date (YYYY-MM-DD) for :%1.', $name));
        }
        return $d;
    }
}
