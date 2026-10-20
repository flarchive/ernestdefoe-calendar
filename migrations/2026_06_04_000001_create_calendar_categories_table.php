<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('calendar_categories')) {
            return;
        }

        $schema->create('calendar_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('color', 20)->default('#3b5bdb');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique('slug');
            $table->index('position');
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('calendar_categories');
    },
];
