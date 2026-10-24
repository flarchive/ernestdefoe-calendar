<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar;

/**
 * The ids in a "these groups" / "these tags" setting. The admin saves a JSON
 * list; a single id ("4") or a comma-separated string ("4, 5") — saved by
 * hand, by a script or by an older build — reads as the same list instead of
 * as nothing.
 */
final class SettingIds
{
    /** @return int[] */
    public static function from(mixed $raw): array
    {
        $value = json_decode((string) $raw, true);

        $list = is_array($value) ? $value : explode(',', (string) $raw);

        return array_values(array_unique(array_filter(array_map('intval', $list), fn (int $id) => $id > 0)));
    }
}
