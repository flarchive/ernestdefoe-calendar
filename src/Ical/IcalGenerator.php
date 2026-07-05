<?php

namespace ErnestDefoe\Calendar\Ical;

use Carbon\Carbon;
use ErnestDefoe\Calendar\Event;

/**
 * Builds RFC 5545 (.ics) output for a single event or a whole calendar feed.
 *
 * Timed events are emitted in UTC (…Z); all-day events use VALUE=DATE with the
 * exclusive end-date convention. Recurring events pass their RRULE straight
 * through so the subscriber's calendar app expands the series natively. Text is
 * escaped and lines are folded at 75 octets per the spec.
 */
class IcalGenerator
{
    public function __construct(
        protected string $domain = 'localhost'
    ) {}

    /** @param iterable<Event> $events */
    public function calendar(iterable $events, string $name): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ernestdefoe//calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escape($name),
        ];

        foreach ($events as $event) {
            foreach ($this->vevent($event) as $line) {
                $lines[] = $line;
            }
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([$this, 'fold'], $lines)) . "\r\n";
    }

    public function single(Event $event, string $name): string
    {
        $lines = array_merge(
            ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//ernestdefoe//calendar//EN', 'CALSCALE:GREGORIAN'],
            $this->vevent($event),
            ['END:VCALENDAR']
        );

        return implode("\r\n", array_map([$this, 'fold'], $lines)) . "\r\n";
    }

    /** @return string[] the VEVENT block lines (unfolded) */
    private function vevent(Event $event): array
    {
        $start = $event->start_at instanceof Carbon ? $event->start_at->copy() : Carbon::parse($event->start_at);
        $end   = $event->end_at ? ($event->end_at instanceof Carbon ? $event->end_at->copy() : Carbon::parse($event->end_at)) : null;

        $lines = ['BEGIN:VEVENT'];
        $lines[] = 'UID:event-' . $event->id . '@' . $this->domain;
        $lines[] = 'DTSTAMP:' . Carbon::now('UTC')->format('Ymd\THis\Z');

        if ($event->all_day) {
            $lines[] = 'DTSTART;VALUE=DATE:' . $start->format('Ymd');
            // iCal all-day DTEND is exclusive → the day after the last day.
            $lastDay = ($end ?: $start)->copy();
            $lines[] = 'DTEND;VALUE=DATE:' . $lastDay->addDay()->format('Ymd');
        } else {
            $lines[] = 'DTSTART:' . $start->utc()->format('Ymd\THis\Z');
            if ($end) {
                $lines[] = 'DTEND:' . $end->utc()->format('Ymd\THis\Z');
            }
        }

        if ($event->isRecurring()) {
            $lines[] = 'RRULE:' . preg_replace('/^RRULE:/i', '', trim((string) $event->rrule));
        }

        $lines[] = 'SUMMARY:' . $this->escape($event->title);

        $description = trim(strip_tags((string) $event->description));
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . $this->escape($description);
        }
        if ($event->location) {
            $lines[] = 'LOCATION:' . $this->escape($event->location);
        }
        if ($event->url) {
            $lines[] = 'URL:' . $this->escape($event->url);
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function escape(string $text): string
    {
        // Order matters: backslash first.
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace([',', ';'], ['\,', '\;'], $text);
        $text = preg_replace('/\r\n|\r|\n/', '\\n', $text);
        return $text;
    }

    /** Fold a content line to <= 75 octets, continuation lines prefixed with a space. */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        $remaining = $line;
        $first = true;
        while (strlen($remaining) > 0) {
            $limit = $first ? 75 : 74; // continuation lines start with a leading space
            $chunk = mb_strcut($remaining, 0, $limit, 'UTF-8');
            $out .= ($first ? '' : "\r\n ") . $chunk;
            $remaining = substr($remaining, strlen($chunk));
            $first = false;
        }
        return $out;
    }
}
