<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use ErnestDefoe\Calendar\Event;
use ErnestDefoe\Calendar\EventRsvp;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/calendar/events/{id}/rsvp  { data: { status: going|interested|none } }
 *
 * Sets (or clears, with "none") the current user's RSVP and returns the fresh
 * counts + the user's status. Any registered user may RSVP to a visible event.
 */
class RsvpController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $id = (int) Arr::get($request->getAttributes(), 'routeParameters.id');
        // Only published events accept RSVPs — except the event's own author, who
        // may RSVP to their draft. Stops a registered user who guesses/brute-forces
        // a draft's integer ID from polluting its RSVP counts before publication.
        $event = Event::query()
            ->where(fn ($q) => $q->where('is_published', true)->orWhere('user_id', $actor->id))
            ->findOrFail($id);

        $status = (string) Arr::get((array) $request->getParsedBody(), 'data.status', '');

        $existing = EventRsvp::query()->where('event_id', $event->id)->where('user_id', $actor->id)->first();

        if ($status === 'none' || $status === '') {
            $existing?->delete();
            $mine = null;
        } elseif (in_array($status, [EventRsvp::GOING, EventRsvp::INTERESTED], true)) {
            $rsvp = $existing ?: new EventRsvp(['event_id' => $event->id, 'user_id' => $actor->id]);
            $rsvp->event_id = $event->id;
            $rsvp->user_id = $actor->id;
            $rsvp->status = $status;
            $rsvp->save();
            $mine = $status;
        } else {
            $mine = $existing?->status;
        }

        $rows = EventRsvp::query()->where('event_id', $event->id)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return new JsonResponse(['data' => [
            'going'      => (int) ($rows[EventRsvp::GOING] ?? 0),
            'interested' => (int) ($rows[EventRsvp::INTERESTED] ?? 0),
            'mine'       => $mine,
        ]]);
    }
}
