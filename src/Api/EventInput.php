<?php

namespace ErnestDefoe\Calendar\Api;

use Carbon\Carbon;
use ErnestDefoe\Calendar\Event;
use Flarum\Foundation\ValidationException;
use Illuminate\Support\Str;

/**
 * Validates and applies incoming event attributes onto an Event model. Datetimes
 * arrive as ISO strings (the client sends UTC); we store them in UTC and keep
 * the chosen display timezone in `timezone`.
 */
class EventInput
{
    private const VALID_TZ = true;

    public static function apply(Event $event, array $attrs, bool $creating): void
    {
        $errors = [];

        if (array_key_exists('title', $attrs) || $creating) {
            $title = trim((string) ($attrs['title'] ?? ''));
            if ($title === '') {
                $errors['title'] = 'The title is required.';
            } else {
                $event->title = mb_substr($title, 0, 255);
            }
        }

        if (array_key_exists('start', $attrs) || $creating) {
            $start = self::parseDate($attrs['start'] ?? null);
            if (! $start) {
                $errors['start'] = 'A valid start date/time is required.';
            } else {
                $event->start_at = $start;
            }
        }

        if (array_key_exists('end', $attrs)) {
            $end = self::parseDate($attrs['end']);
            $event->end_at = $end; // null clears it
            if ($end && isset($event->start_at) && $end->lt($event->start_at)) {
                $errors['end'] = 'The end must be after the start.';
            }
        }

        if (array_key_exists('allDay', $attrs))   $event->all_day = (bool) $attrs['allDay'];
        if (array_key_exists('timezone', $attrs)) $event->timezone = self::safeTimezone((string) $attrs['timezone']);
        if (array_key_exists('description', $attrs)) $event->description = mb_substr((string) $attrs['description'], 0, 20000);
        if (array_key_exists('location', $attrs)) $event->location = self::str($attrs['location'], 255);
        if (array_key_exists('url', $attrs))      $event->url = self::url($attrs['url']);
        if (array_key_exists('coverUrl', $attrs)) $event->cover_url = self::url($attrs['coverUrl']);
        if (array_key_exists('rrule', $attrs))    $event->rrule = self::rrule($attrs['rrule']);
        if (array_key_exists('categoryId', $attrs)) {
            $event->category_id = $attrs['categoryId'] ? (int) $attrs['categoryId'] : null;
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        if ($creating || ! $event->slug) {
            $event->slug = self::uniqueSlug($event->title, $event->id);
        }
    }

    private static function parseDate($value): ?Carbon
    {
        if ($value === null || $value === '') return null;
        try {
            return Carbon::parse((string) $value)->utc();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function safeTimezone(string $tz): string
    {
        return in_array($tz, timezone_identifiers_list(), true) ? $tz : 'UTC';
    }

    private static function str($v, int $max): ?string
    {
        $v = trim((string) $v);
        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private static function url($v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') return null;
        // Accept absolute URLs, plus root-relative paths (e.g. FoF Upload's local
        // file URLs) — but never protocol-relative "//host" which could point off-site.
        $ok = filter_var($v, FILTER_VALIDATE_URL)
            || (str_starts_with($v, '/') && !str_starts_with($v, '//'));
        return $ok ? mb_substr($v, 0, 600) : null;
    }

    /** Keep only a safe RRULE-ish charset; the expander/iCal tolerate the rest. */
    private static function rrule($v): ?string
    {
        $v = strtoupper(trim((string) $v));
        if ($v === '') return null;
        $v = preg_replace('/[^A-Z0-9;:=,\-]/', '', $v);
        return mb_substr($v, 0, 600);
    }

    private static function uniqueSlug(string $title, $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'event';
        $slug = $base;
        $i = 2;
        while (Event::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
