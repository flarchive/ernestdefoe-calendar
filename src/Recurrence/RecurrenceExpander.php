<?php

namespace ErnestDefoe\Calendar\Recurrence;

use Carbon\Carbon;

/**
 * Minimal but practical RFC 5545 RRULE expander. Supports the rules that cover
 * the vast majority of real calendars:
 *
 *   FREQ = DAILY | WEEKLY | MONTHLY | YEARLY
 *   INTERVAL, COUNT, UNTIL, and BYDAY (for weekly, e.g. MO,WE,FR)
 *
 * It returns the occurrence START times that fall within [$from, $to]. iCal
 * export passes the raw RRULE straight through (calendar clients expand it
 * themselves); this expander is only for rendering the in-app calendar views,
 * so it is deliberately bounded (hard caps prevent any runaway loop).
 *
 * Unsupported parts (BYMONTHDAY, BYSETPOS, etc.) are simply ignored — the series
 * still renders via its base frequency, never errors.
 */
class RecurrenceExpander
{
    private const DAY_MAP = ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6];
    private const MAX_ITERATIONS = 5000;

    /** @return Carbon[] occurrence start times within [$from, $to], in ascending order. */
    public static function occurrences(string $rrule, Carbon $start, Carbon $from, Carbon $to, int $cap = 400): array
    {
        $rule = self::parse($rrule);
        $freq = strtoupper($rule['FREQ'] ?? '');
        if (! in_array($freq, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)) {
            return [];
        }

        $interval = max(1, (int) ($rule['INTERVAL'] ?? 1));
        $count    = isset($rule['COUNT']) ? max(0, (int) $rule['COUNT']) : null;
        $until     = isset($rule['UNTIL']) ? self::parseUntil((string) $rule['UNTIL'], $start) : null;
        $byday    = isset($rule['BYDAY']) ? self::parseByday((string) $rule['BYDAY']) : [];

        if ($freq === 'WEEKLY' && $byday) {
            return self::expandWeeklyByday($start, $from, $to, $interval, $count, $until, $byday, $cap);
        }

        return self::expandSimple($freq, $start, $from, $to, $interval, $count, $until, $cap);
    }

    private static function expandSimple(string $freq, Carbon $start, Carbon $from, Carbon $to, int $interval, ?int $count, ?Carbon $until, int $cap): array
    {
        $out = [];
        $occ = $start->copy();
        $emitted = 0;
        $i = 0;

        while ($i++ < self::MAX_ITERATIONS) {
            if ($until && $occ->gt($until)) break;
            if ($count !== null && $emitted >= $count) break;
            if ($occ->gt($to)) break;

            if ($occ->gte($from)) {
                $out[] = $occ->copy();
                if (count($out) >= $cap) break;
            }
            $emitted++;

            switch ($freq) {
                case 'DAILY':   $occ->addDays($interval); break;
                case 'WEEKLY':  $occ->addWeeks($interval); break;
                case 'MONTHLY': $occ->addMonthsNoOverflow($interval); break;
                case 'YEARLY':  $occ->addYearsNoOverflow($interval); break;
            }
        }

        return $out;
    }

    /** @param int[] $days Carbon dayOfWeek values (0=Sun..6=Sat) */
    private static function expandWeeklyByday(Carbon $start, Carbon $from, Carbon $to, int $interval, ?int $count, ?Carbon $until, array $days, int $cap): array
    {
        $out = [];
        $emitted = 0;
        $i = 0;
        // Anchor on the Sunday of the series-start week; step whole weeks by $interval.
        $weekStart = $start->copy()->startOfWeek(Carbon::SUNDAY);

        while ($i++ < self::MAX_ITERATIONS) {
            foreach ($days as $dow) {
                $occ = $weekStart->copy()->addDays($dow)->setTime($start->hour, $start->minute, $start->second);
                if ($occ->lt($start)) continue;                 // never before the series anchor
                if ($until && $occ->gt($until)) return $out;
                if ($count !== null && $emitted >= $count) return $out;
                $emitted++;
                if ($occ->gte($from) && $occ->lte($to)) {
                    $out[] = $occ;
                    if (count($out) >= $cap) return $out;
                }
            }
            $weekStart->addWeeks($interval);
            if ($weekStart->gt($to)) break;
        }

        return $out;
    }

    /** Parse "FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE" into an upper-cased key map. */
    private static function parse(string $rrule): array
    {
        $rrule = trim(preg_replace('/^RRULE:/i', '', trim($rrule)));
        $out = [];
        foreach (explode(';', $rrule) as $part) {
            if (strpos($part, '=') === false) continue;
            [$k, $v] = explode('=', $part, 2);
            $out[strtoupper(trim($k))] = trim($v);
        }
        return $out;
    }

    /** @return int[] */
    private static function parseByday(string $byday): array
    {
        $days = [];
        foreach (explode(',', strtoupper($byday)) as $code) {
            $code = preg_replace('/[^A-Z]/', '', $code); // strip any ordinal prefix like "2MO"
            $two = substr($code, -2);
            if (isset(self::DAY_MAP[$two])) {
                $days[self::DAY_MAP[$two]] = self::DAY_MAP[$two];
            }
        }
        $days = array_values($days);
        sort($days);
        return $days;
    }

    private static function parseUntil(string $until, Carbon $start): ?Carbon
    {
        try {
            // UNTIL may be a DATE (YYYYMMDD) or DATE-TIME (YYYYMMDDTHHMMSSZ).
            $clean = preg_replace('/[^0-9TZ]/', '', strtoupper($until));
            if (preg_match('/^\d{8}$/', $clean)) {
                return Carbon::createFromFormat('Ymd', $clean, $start->getTimezone())->endOfDay();
            }
            if (preg_match('/^\d{8}T\d{6}Z?$/', $clean)) {
                $tz = str_ends_with($clean, 'Z') ? 'UTC' : $start->getTimezone();
                return Carbon::createFromFormat('Ymd\THis', rtrim($clean, 'Z'), $tz);
            }
        } catch (\Throwable $e) {
            // bad UNTIL → treat as open-ended
        }
        return null;
    }
}
