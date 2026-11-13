<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

// Per category: RSVP buttons on or off, and the wording on them (blank = the
// language's own "Going" / "Interested").
return [
    'up' => function (Builder $schema) {
        if ($schema->hasColumn('calendar_categories', 'rsvp_enabled')) {
            return;
        }

        $schema->table('calendar_categories', function (Blueprint $table) {
            $table->boolean('rsvp_enabled')->default(true);
            $table->string('going_label', 50)->nullable();
            $table->string('interested_label', 50)->nullable();
        });
    },

    'down' => function (Builder $schema) {
        $schema->table('calendar_categories', function (Blueprint $table) {
            $table->dropColumn(['rsvp_enabled', 'going_label', 'interested_label']);
        });
    },
];
