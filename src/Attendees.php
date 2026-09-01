<?php

namespace ErnestDefoe\Calendar;

use Illuminate\Database\ConnectionInterface;

/**
 * The attendee lists for an event's RSVP panel: who's going / interested,
 * enriched with each member's main WoW character (class, spec, item level)
 * when the ernestdefoe/armory extension is installed — so a raid signup
 * doubles as a composition sheet. Armory is a soft dependency: without its
 * table the lists are plain avatars + names.
 */
class Attendees
{
    public const LIMIT = 60;

    public static function build(int $eventId): array
    {
        /** @var ConnectionInterface $db */
        $db = resolve(ConnectionInterface::class);

        // display_name isn't a core column (it's driver-computed); nickname is
        // only present when fof/nicknames is installed.
        $cols = ['r.status', 'u.id', 'u.username', 'u.avatar_url'];
        if ($db->getSchemaBuilder()->hasColumn('users', 'nickname')) {
            $cols[] = 'u.nickname';
        }

        $rows = $db->table('calendar_event_rsvps as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.event_id', $eventId)
            ->orderBy('r.created_at')
            ->limit(self::LIMIT)
            ->get($cols);

        $characters = self::armoryCharacters($db, $rows->pluck('id')->all());

        $out = ['going' => [], 'interested' => []];
        foreach ($rows as $row) {
            $bucket = $row->status === 'interested' ? 'interested' : 'going';
            $out[$bucket][] = [
                'id' => (int) $row->id,
                'username' => (string) $row->username,
                'displayName' => (string) (($row->nickname ?? null) ?: $row->username),
                'avatarUrl' => $row->avatar_url ? (string) $row->avatar_url : null,
                'character' => $characters[$row->id] ?? null,
            ];
        }

        return $out;
    }

    /** Best visible character per user from armory's table, or [] without armory. */
    protected static function armoryCharacters(ConnectionInterface $db, array $userIds): array
    {
        if (! $userIds || ! $db->getSchemaBuilder()->hasTable('armory_characters')) {
            return [];
        }

        try {
            $rows = $db->table('armory_characters')
                ->whereIn('user_id', $userIds)
                ->where('is_visible', true)
                ->orderByDesc('is_main')
                ->orderByDesc('item_level')
                ->get(['user_id', 'name', 'class', 'spec', 'item_level', 'level']);
        } catch (\Throwable $e) {
            return [];
        }

        $best = [];
        foreach ($rows as $c) {
            if (isset($best[$c->user_id])) {
                continue; // rows are ordered best-first per the sort above
            }
            $best[$c->user_id] = [
                'name' => (string) $c->name,
                'class' => $c->class ? (string) $c->class : null,
                'spec' => $c->spec ? (string) $c->spec : null,
                'itemLevel' => $c->item_level ? (int) $c->item_level : null,
                'level' => $c->level ? (int) $c->level : null,
            ];
        }

        return $best;
    }
}
