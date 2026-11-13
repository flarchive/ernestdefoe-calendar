<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('calendar_event_rsvps')) {
            return;
        }

        $schema->create('calendar_event_rsvps', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('event_id');
            $table->unsignedInteger('user_id');
            $table->string('status', 20)->default('going'); // going | interested
            $table->timestamps();

            $table->unique(['event_id', 'user_id'], 'cal_rsvp_event_user');
            $table->index(['event_id', 'status'], 'cal_rsvp_event_status');

            $table->foreign('event_id')->references('id')->on('calendar_events')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('calendar_event_rsvps');
    },
];
