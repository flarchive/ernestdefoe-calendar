<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * Index users.cal_birthday. CelebrationsController filters
 * `WHERE cal_birthday = :mmdd` on every celebrations request; without an index
 * that is a full users-table scan (hundreds of ms on a large forum). Raw closure
 * because the Migration helpers don't cover index-only changes; try/catch keeps
 * it idempotent against re-application.
 */
return [
    'up' => function (Builder $schema) {
        if (! $schema->hasColumn('users', 'cal_birthday')) {
            return;
        }
        try {
            $schema->table('users', function (Blueprint $table) {
                $table->index('cal_birthday', 'users_cal_birthday_index');
            });
        } catch (\Throwable $e) {
            // index already present — safe to ignore
        }
    },
    'down' => function (Builder $schema) {
        try {
            $schema->table('users', function (Blueprint $table) {
                $table->dropIndex('users_cal_birthday_index');
            });
        } catch (\Throwable $e) {
        }
    },
];
