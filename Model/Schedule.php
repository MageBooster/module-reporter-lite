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

/**
 * Schedules: daily, weekly (one weekday), monthly (one date) or a cron expression, all in store time.
 * Every schedule is stored as a five-field cron expression; next() finds the next send time.
 */
class Schedule
{
    public const FREQUENCIES = ['daily', 'weekly', 'monthly', 'custom'];

    private const LIMITS = [[0, 59], [0, 23], [1, 31], [1, 12], [0, 7]];

    /**
     * @param Config $config
     */
    public function __construct(private Config $config)
    {
    }

    /**
     * Cleans the schedule sent by the page
     *
     * @param array $in
     * @return array{frequency:string,day:int,time:string,range:string,cron:string}
     * @throws LocalizedException
     */
    public function normalise(array $in): array
    {
        $frequency = in_array($in['frequency'] ?? '', self::FREQUENCIES, true) ? $in['frequency'] : 'weekly';
        $time = (string)($in['time'] ?? '08:00');
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time)) {
            throw new LocalizedException(__('Enter the time as HH:MM, for example 08:00.'));
        }
        [$h, $m] = array_map('intval', explode(':', $time));
        $time = sprintf('%02d:%02d', $h, $m);
        $day = (int)($in['day'] ?? 1);
        $range = array_key_exists((string)($in['range'] ?? ''), Params::RANGES) ? (string)$in['range'] : 'saved';
        switch ($frequency) {
            case 'daily':
                $cron = sprintf('%d %d * * *', $m, $h);
                break;
            case 'weekly':
                $day = max(1, min(7, $day));
                $cron = sprintf('%d %d * * %d', $m, $h, $day % 7);
                break;
            case 'monthly':
                $day = max(1, min(28, $day));
                $cron = sprintf('%d %d %d * *', $m, $h, $day);
                break;
            default:
                $cron = trim(preg_replace('/\s+/', ' ', (string)($in['cron'] ?? '')) ?? '');
                $this->parse($cron);
        }
        return ['frequency' => $frequency, 'day' => $day, 'time' => $time, 'range' => $range, 'cron' => $cron];
    }

    /**
     * Next send time after $after (UTC), or null if the expression never matches within two years
     *
     * @param string $cron
     * @param \DateTimeImmutable|null $after
     * @return \DateTimeImmutable|null in store time
     */
    public function next(string $cron, ?\DateTimeImmutable $after = null): ?\DateTimeImmutable
    {
        try {
            [$min, $hour, $dom, $mon, $dow, $domAny, $dowAny] = $this->parse($cron);
        } catch (LocalizedException $e) {
            return null;
        }
        $zone = new \DateTimeZone($this->config->timezone());
        $start = ($after ?? new \DateTimeImmutable('now'))->setTimezone($zone);
        $start = $start->setTime((int)$start->format('H'), (int)$start->format('i'))->modify('+1 minute');
        $day = $start->setTime(0, 0);
        for ($d = 0; $d < 800; $d++, $day = $day->modify('+1 day')) {
            if (!isset($mon[(int)$day->format('n')])) {
                continue;
            }
            $domOk = isset($dom[(int)$day->format('j')]);
            $dowOk = isset($dow[(int)$day->format('w')]);
            $match = $domAny && $dowAny ? true : ($domAny ? $dowOk : ($dowAny ? $domOk : ($domOk || $dowOk)));
            if (!$match) {
                continue;
            }
            foreach (array_keys($hour) as $h) {
                foreach (array_keys($min) as $m) {
                    $t = $day->setTime($h, $m);
                    // a time skipped by a DST change lands on another hour: keep only real matches
                    if ((int)$t->format('G') !== $h || $t < $start) {
                        continue;
                    }
                    return $t;
                }
            }
        }
        return null;
    }

    /**
     * Short text for the report list, e.g. "Mondays 08:00"
     *
     * @param array $schedule
     * @return string
     */
    public function describe(array $schedule): string
    {
        $time = (string)($schedule['time'] ?? '');
        $day = (int)($schedule['day'] ?? 1);
        switch ($schedule['frequency'] ?? '') {
            case 'daily':
                return (string)__('Daily %1', $time);
            case 'weekly':
                $names = [1 => __('Mondays'), __('Tuesdays'), __('Wednesdays'), __('Thursdays'), __('Fridays'),
                    __('Saturdays'), __('Sundays')];
                return (string)__('%1 %2', $names[max(1, min(7, $day))], $time);
            case 'monthly':
                return (string)__('Day %1 of the month · %2', $day, $time);
            default:
                return (string)__('Cron %1', (string)($schedule['cron'] ?? ''));
        }
    }

    /**
     * Parses a five-field cron expression
     *
     * @param string $cron
     * @return array{0:array,1:array,2:array,3:array,4:array,5:bool,6:bool}
     * @throws LocalizedException
     */
    public function parse(string $cron): array
    {
        $parts = preg_split('/\s+/', trim($cron)) ?: [];
        if (count($parts) !== 5) {
            throw new LocalizedException(__(
                'A cron expression has five fields: minute hour day-of-month month weekday, e.g. "0 8 * * 1".'
            ));
        }
        $sets = [];
        foreach ($parts as $i => $field) {
            $sets[$i] = $this->field($field, self::LIMITS[$i][0], self::LIMITS[$i][1]);
        }
        if (isset($sets[4][7])) {
            $sets[4][0] = true;
            unset($sets[4][7]);
        }
        return [$sets[0], $sets[1], $sets[2], $sets[3], $sets[4], $parts[2] === '*', $parts[4] === '*'];
    }

    /**
     * One cron field (*, lists, ranges, steps) as a set of numbers
     *
     * @param string $field
     * @param int $lo
     * @param int $hi
     * @return array<int,bool>
     * @throws LocalizedException
     */
    private function field(string $field, int $lo, int $hi): array
    {
        $set = [];
        foreach (explode(',', $field) as $part) {
            if (!preg_match('/^(\*|\d{1,2}(?:-\d{1,2})?)(?:\/(\d{1,2}))?$/', $part, $m)) {
                throw new LocalizedException(__('"%1" is not a valid cron field.', $field));
            }
            $step = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : 1;
            if ($m[1] === '*') {
                [$a, $b] = [$lo, $hi];
            } elseif (str_contains($m[1], '-')) {
                [$a, $b] = array_map('intval', explode('-', $m[1]));
            } else {
                $a = $b = (int)$m[1];
                if ($step > 1) {
                    $b = $hi;
                }
            }
            if ($step < 1 || $a < $lo || $b > $hi || $a > $b) {
                throw new LocalizedException(__('"%1" is not a valid cron field.', $field));
            }
            for ($v = $a; $v <= $b; $v += $step) {
                $set[$v] = true;
            }
        }
        ksort($set);
        return $set;
    }
}
