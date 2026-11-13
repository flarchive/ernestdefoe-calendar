<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Badges;

use Carbon\Carbon;
use ErnestDefoe\Calendar\Events\EventSaved;
use Flarum\User\User;
use FoF\Badges\Trigger\MetricInterface;
use Illuminate\Database\ConnectionInterface;

/**
 * FoF Badges metric: published events a member created. A recurring event
 * is one event. Only loaded when fof/badges registers its metrics.
 */
class EventsOrganizedMetric implements MetricInterface
{
    public function __construct(protected ConnectionInterface $db)
    {
    }

    public function getType(): string
    {
        return 'calendar_events_organized';
    }

    public function getTranslationKey(): string
    {
        return 'ernestdefoe-calendar.admin.badges.events_organized';
    }

    public function getEventTriggers(): array
    {
        // The organizer, also when a manager publishes or edits their event.
        return [EventSaved::class => fn (EventSaved $event) => $event->event->user];
    }

    public function getValue(User $user, array $config = []): int
    {
        $query = $this->db->table('calendar_events')
            ->where('user_id', $user->id)
            ->where('is_published', true);

        if (! empty($config['calendar_category_id'])) {
            $query->where('category_id', (int) $config['calendar_category_id']);
        }

        // A badge's date range counts the events created inside it.
        if (! empty($config['date_range']['start'])) {
            $query->where('created_at', '>=', Carbon::parse($config['date_range']['start']));
        }
        if (! empty($config['date_range']['end'])) {
            $query->where('created_at', '<=', Carbon::parse($config['date_range']['end']));
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
