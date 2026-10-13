<?php

namespace ErnestDefoe\Calendar;

use Flarum\User\User;
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

        $rsvps = $db->table('calendar_event_rsvps')
            ->where('event_id', $eventId)
            ->orderBy('created_at')
            ->limit(self::LIMIT)
            ->get(['user_id', 'status']);

        $userIds = $rsvps->pluck('user_id')->all();

        // 🚨 The users have to come back as MODELS, not as joined columns. Both
        // fields we want are driver-computed accessors: users.avatar_url stores a
        // bare filename ("MkvOdb….webp") that only User::getAvatarUrlAttribute()
        // turns into a URL — and which the avatar driver also fills in for members
        // who never uploaded one — while display_name is not a column at all.
        // Reading the raw columns shipped filenames to the browser as <img src>,
        // so every attendee rendered as a broken image.
        $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');

        $characters = self::armoryCharacters($db, $userIds);

        $out = ['going' => [], 'interested' => []];
        foreach ($rsvps as $row) {
            $user = $users->get($row->user_id);

            if (! $user) {
                continue; // the member was deleted between the RSVP and now
            }

            $bucket = $row->status === 'interested' ? 'interested' : 'going';
            $out[$bucket][] = [
                'id' => (int) $user->id,
                'username' => (string) $user->username,
                'displayName' => (string) $user->display_name,
                'avatarUrl' => $user->avatar_url,
                'character' => $characters[$user->id] ?? null,
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
