<?php

namespace ErnestDefoe\Calendar;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's RSVP to an event.
 *
 * @property int $id
 * @property int $event_id
 * @property int $user_id
 * @property string $status  going | interested
 */
class EventRsvp extends AbstractModel
{
    public const GOING = 'going';
    public const INTERESTED = 'interested';

    protected $table = 'calendar_event_rsvps';

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
