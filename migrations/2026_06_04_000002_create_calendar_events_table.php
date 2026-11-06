<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('calendar_events')) {
            return;
        }

        $schema->create('calendar_events', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();        // creator
            $table->unsignedInteger('category_id')->nullable();
            $table->unsignedInteger('discussion_id')->nullable();  // optional linked thread (hybrid)
            $table->string('title', 255);
            $table->string('slug', 255);
            $table->text('description')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('timezone', 64)->default('UTC');
            $table->string('location', 255)->nullable();
            $table->string('url', 600)->nullable();
            $table->string('cover_url', 600)->nullable();
            $table->string('rrule', 600)->nullable();              // RFC 5545 recurrence rule
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->unique('slug');
            $table->index('start_at');
            $table->index(['is_published', 'start_at'], 'cal_pub_start');
            $table->index('category_id');

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('category_id')->references('id')->on('calendar_categories')->nullOnDelete();
            $table->foreign('discussion_id')->references('id')->on('discussions')->nullOnDelete();
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('calendar_events');
    },
];
