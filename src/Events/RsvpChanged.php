<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Events;

use ErnestDefoe\Calendar\Event;
use Flarum\User\User;

/** A member set, changed or withdrew their RSVP (`status` null when withdrawn). */
class RsvpChanged
{
    public function __construct(
        public Event $event,
        public User $actor,
        public ?string $status,
    ) {
    }
}
