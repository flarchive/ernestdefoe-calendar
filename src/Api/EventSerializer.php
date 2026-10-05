<?php

namespace ErnestDefoe\Calendar\Api;

use Carbon\Carbon;
use ErnestDefoe\Calendar\Event;
use Flarum\User\User;

/**
 * Serialises an Event to a plain array for the calendar's JSON endpoints. One
 * place builds the export links so the calendar page, the event page and every
 * widget (standalone / Bespoke / Page Builder) share identical behaviour.
 *
 * For a recurring series, pass the specific $occurrence start so the payload
 * reflects that instance's date while keeping the series id.
 */
class EventSerializer
{
    public static function serialize(Event $event, ?User $actor = null, ?Carbon $occurrence = null, array $rsvp = []): array
    {
        $start = $occurrence
            ? $occurrence->copy()
            : ($event->start_at instanceof Carbon ? $event->start_at->copy() : Carbon::parse($event->start_at));

        // For an occurrence, preserve the original duration.
        $end = null;
        if ($event->end_at) {
            $origStart = $event->start_at instanceof Carbon ? $event->start_at : Carbon::parse($event->start_at);
            $origEnd   = $event->end_at instanceof Carbon ? $event->end_at : Carbon::parse($event->end_at);
            $end = $occurrence ? $start->copy()->addSeconds($origStart->diffInSeconds($origEnd)) : $origEnd->copy();
        }

        $category = null;
        if ($event->category_id && $event->relationLoaded('category') && $event->category) {
            $category = ['id' => (int) $event->category->id, 'name' => $event->category->name, 'color' => $event->category->color];
        }

        $author = null;
        if ($event->relationLoaded('user') && $event->user) {
            $author = [
                'id'          => (int) $event->user->id,
                'username'    => $event->user->username,
                'displayName' => $event->user->display_name ?: $event->user->username,
                'avatarUrl'   => $event->user->avatar_url,
            ];
        }

        return [
            'id'            => (int) $event->id,
            'title'         => $event->title,
            'slug'          => $event->slug,
            'description'   => $event->description,
            'start'         => $start->toIso8601String(),
            'end'           => $end?->toIso8601String(),
            'allDay'        => (bool) $event->all_day,
            'timezone'      => $event->timezone,
            'location'      => $event->location,
            'url'           => $event->url,
            'coverUrl'      => $event->cover_url,
            'rrule'         => $event->rrule,
            'recurring'     => $event->isRecurring(),
            'occurrence'    => $occurrence?->toIso8601String(),
            'category'      => $category,
            'author'        => $author,
            'discussionId'  => $event->discussion_id ? (int) $event->discussion_id : null,
            'rsvp'          => [
                'going'      => (int) ($rsvp['going'] ?? 0),
                'interested' => (int) ($rsvp['interested'] ?? 0),
                'mine'       => $rsvp['mine'] ?? null,
                'attendees'  => $rsvp['attendees'] ?? null,
            ],
            'canEdit'       => self::canEdit($event, $actor),
            'icalUrl'       => 'calendar/events/' . $event->id . '/ical', // path; client prefixes baseUrl
            'googleUrl'     => self::googleUrl($event, $start, $end),
        ];
    }

    public static function canEdit(Event $event, ?User $actor): bool
    {
        if (! $actor || $actor->isGuest()) {
            return false;
        }
        if ($actor->isAdmin() || $actor->hasPermission('calendar.manage')) {
            return true;
        }
        return $event->user_id && (int) $actor->id === (int) $event->user_id && $actor->hasPermission('calendar.create');
    }

    /** "Add to Google Calendar" template URL — no API/OAuth, works for everyone. */
    private static function googleUrl(Event $event, Carbon $start, ?Carbon $end): string
    {
        if ($event->all_day) {
            $s = $start->format('Ymd');
            $e = ($end ?: $start)->copy()->addDay()->format('Ymd'); // exclusive end
            $dates = "$s/$e";
        } else {
            $s = $start->copy()->utc()->format('Ymd\THis\Z');
            $e = ($end ?: $start->copy()->addHour())->copy()->utc()->format('Ymd\THis\Z');
            $dates = "$s/$e";
        }

        $params = [
            'action'   => 'TEMPLATE',
            'text'     => $event->title,
            'dates'    => $dates,
            'details'  => trim(strip_tags((string) $event->description)),
            'location' => (string) $event->location,
        ];

        return 'https://calendar.google.com/calendar/render?' . http_build_query(array_filter($params, fn ($v) => $v !== ''));
    }
}
