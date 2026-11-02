<?php

use Flarum\Group\Group;
use Illuminate\Database\Schema\Builder;

/*
 * Members can create events out of the box. Managing everyone's events stays
 * with admins until it is granted in the Permissions grid.
 *
 * 🚨 Dated BEFORE every other Calendar migration on purpose. Migrations run in
 * filename order, so on a fresh install this one runs first and finds no
 * Calendar migration recorded yet. On a forum that already runs Calendar the
 * others are recorded, and it grants nothing: there an empty permission is a
 * choice the admin made (or a grant they removed), not a missing default.
 *
 * The grant is also skipped if any group already holds the permission, and
 * what it grants is recorded so rolling back removes that and nothing else.
 */

$seededKey = 'ernestdefoe-calendar.seeded_permissions';

return [
    'up' => function (Builder $schema) use ($seededKey) {
        $db = $schema->getConnection();

        if ($db->table('migrations')->where('extension', 'ernestdefoe-calendar')->exists()) {
            return;
        }

        if ($db->table('group_permission')->where('permission', 'calendar.create')->exists()
            || $db->table('groups')->where('id', Group::MEMBER_ID)->doesntExist()) {
            return;
        }

        $db->table('group_permission')->insert(['group_id' => Group::MEMBER_ID, 'permission' => 'calendar.create']);
        $db->table('settings')->updateOrInsert(['key' => $seededKey], ['value' => json_encode(['calendar.create'])]);
    },

    'down' => function (Builder $schema) use ($seededKey) {
        $db = $schema->getConnection();

        $seeded = json_decode((string) $db->table('settings')->where('key', $seededKey)->value('value'), true) ?: [];

        if ($seeded) {
            $db->table('group_permission')->where('group_id', Group::MEMBER_ID)->whereIn('permission', $seeded)->delete();
        }

        $db->table('settings')->where('key', $seededKey)->delete();
    },
];
