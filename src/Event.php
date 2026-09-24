<?php

namespace ErnestDefoe\Calendar;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A calendar event.
 *
 * Hybrid model: an Event is its own first-class record (so the calendar and the
 * iCal feed stay clean and self-contained), but may optionally link to a
 * Discussion (`discussion_id`) that hosts comments/notifications for it.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $category_id
 * @property int|null $discussion_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property \Carbon\Carbon $start_at
 * @property \Carbon\Carbon|null $end_at
 * @property bool $all_day
 * @property string $timezone
 * @property string|null $location
 * @property string|null $url
 * @property string|null $cover_url
 * @property string|null $rrule
 * @property bool $is_published
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Event extends AbstractModel
{
    protected $table = 'calendar_events';

    protected $casts = [
        'start_at'     => 'datetime',
        'end_at'       => 'datetime',
        'all_day'      => 'boolean',
        'is_published' => 'boolean',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    protected $attributes = [
        'all_day'      => false,
        'timezone'     => 'UTC',
        'is_published' => true,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class, 'discussion_id');
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class, 'event_id');
    }

    public function isRecurring(): bool
    {
        return ! empty($this->rrule);
    }

    /**
     * Events that overlap the [$from, $to] window. For recurring events this only
     * matches the SERIES anchor by its first occurrence window being before $to;
     * occurrence expansion within the range is done in the recurrence layer.
     */
    public function scopeInWindow(Builder $query, Carbon $from, Carbon $to): Builder
    {
        // A series anchor that starts after the window end can't produce any
        // occurrence inside [$from, $to], so we only need rows whose start_at is
        // on/before $to. The inner orWhereNotNull('rrule') already pulls in
        // recurring series that started before the window (their occurrences are
        // expanded in the recurrence layer) — so the old standalone
        // `orWhere(whereNotNull('rrule'))` was a strict superset that loaded
        // EVERY recurring event ever created on every window query. Dropped.
        return $query->where('start_at', '<=', $to)
            // …and either ends after the window starts, is open-ended, or recurs.
            ->where(function (Builder $q2) use ($from) {
                $q2->whereNull('end_at')
                    ->orWhere('end_at', '>=', $from)
                    ->orWhereNotNull('rrule');
            });
    }
}
