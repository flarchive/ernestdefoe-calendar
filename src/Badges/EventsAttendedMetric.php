<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Badges;

use Carbon\Carbon;
use ErnestDefoe\Calendar\EventRsvp;
use ErnestDefoe\Calendar\Events\RsvpChanged;
use Flarum\Post\Event\Posted;
use Flarum\User\Event\LoggedIn;
use Flarum\User\User;
use FoF\Badges\Trigger\MetricInterface;
use Illuminate\Database\ConnectionInterface;

/**
 * FoF Badges metric: events a member said they were going to, once each has
 * ended. Only loaded when fof/badges registers its metrics.
 *
 * A recurring event is one RSVP for the whole series, so it counts once,
 * after its first occurrence ends. An event with no end counts an hour
 * after it starts (a day, all-day). Drafts never count.
 *
 * An event ends with nothing happening, so besides an RSVP the member's
 * next post or sign-in re-checks it: fof/badges evaluates those itself.
 */
class EventsAttendedMetric implements MetricInterface
{
    public function __construct(protected ConnectionInterface $db)
    {
    }

    public function getType(): string
    {
        return 'calendar_events_attended';
    }

    public function getTranslationKey(): string
    {
        return 'ernestdefoe-calendar.admin.badges.events_attended';
    }

    public function getEventTriggers(): array
    {
        return [
            RsvpChanged::class => fn (RsvpChanged $event) => $event->actor,
            Posted::class => fn (Posted $event) => $event->post->user,
            LoggedIn::class => fn (LoggedIn $event) => $event->user,
        ];
    }

    public function getValue(User $user, array $config = []): int
    {
        $now = Carbon::now();

        $query = $this->db->table('calendar_event_rsvps')
            ->join('calendar_events', 'calendar_events.id', '=', 'calendar_event_rsvps.event_id')
            ->where('calendar_event_rsvps.user_id', $user->id)
            ->where('calendar_event_rsvps.status', EventRsvp::GOING)
            ->where('calendar_events.is_published', true)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('calendar_events.all_day', false)->where(fn ($q) => $q
                    ->where('calendar_events.end_at', '<=', $now)
                    ->orWhere(fn ($q) => $q->whereNull('calendar_events.end_at')->where('calendar_events.start_at', '<=', $now->copy()->subHour()))))
                ->orWhere(fn ($q) => $q->where('calendar_events.all_day', true)->where(fn ($q) => $q
                    ->where('calendar_events.end_at', '<=', $now->copy()->subDay())
                    ->orWhere(fn ($q) => $q->whereNull('calendar_events.end_at')->where('calendar_events.start_at', '<=', $now->copy()->subDay())))));

        if (! empty($config['calendar_category_id'])) {
            $query->where('calendar_events.category_id', (int) $config['calendar_category_id']);
        }

        // A badge's date range counts the events that STARTED inside it.
        if (! empty($config['date_range']['start'])) {
            $query->where('calendar_events.start_at', '>=', Carbon::parse($config['date_range']['start']));
        }
        if (! empty($config['date_range']['end'])) {
            $query->where('calendar_events.start_at', '<=', Carbon::parse($config['date_range']['end']));
        }

        return $query->count();
    }

    public function isBoolean(): bool
    {
        return false;
    }

    public function getConfigFields(): array
    {
        return ['calendar_category_id' => ['type' => 'select', 'required' => false]];
    }

    public function getExtensionDependencies(): array
    {
        return ['ernestdefoe/calendar'];
    }
}
