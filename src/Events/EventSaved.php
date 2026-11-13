<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Events;

use ErnestDefoe\Calendar\Event;
use Flarum\User\User;

/** An event was created or edited, by its organizer or a manager. */
class EventSaved
{
    public function __construct(
        public Event $event,
        public User $actor,
    ) {
    }
}
