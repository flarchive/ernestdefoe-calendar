<?php

use Flarum\Database\Migration;

return Migration::addSettings([
    'ernestdefoe-calendar.default_view'       => 'month',  // month | list
    'ernestdefoe-calendar.week_starts_on'     => '0',      // 0 = Sunday, 1 = Monday
    'ernestdefoe-calendar.show_index_widget'  => true,     // mount Upcoming widget on the index (works on any theme)
    'ernestdefoe-calendar.index_widget_count' => '5',
]);
