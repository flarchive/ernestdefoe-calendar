<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use ErnestDefoe\Calendar\Api\EventSerializer;
use ErnestDefoe\Calendar\Event;
use ErnestDefoe\Calendar\EventRsvp;
use Flarum\Http\RequestUtil;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** GET /api/calendar/events/{id} — single event with RSVP counts. */
class ShowEventController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $id = (int) Arr::get($request->getAttributes(), 'routeParameters.id');

        $event = Event::query()->with(['user', 'category'])->findOrFail($id);

        // Unpublished (draft) events are visible only to event managers or the
        // author — everyone else gets a 404 (no existence disclosure).
        if (! $event->is_published) {
            $isManager = $actor->hasPermission('calendar.manage');
            $isAuthor  = $event->user_id && (int) $actor->id === (int) $event->user_id && $actor->hasPermission('calendar.create');
            if (! $isManager && ! $isAuthor) {
                throw new ModelNotFoundException();
            }
        }

        $rows = EventRsvp::query()->where('event_id', $id)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $rsvp = [
            'going'      => (int) ($rows[EventRsvp::GOING] ?? 0),
            'interested' => (int) ($rows[EventRsvp::INTERESTED] ?? 0),
            'mine'       => $actor->isGuest() ? null
                : EventRsvp::query()->where('event_id', $id)->where('user_id', $actor->id)->value('status'),
            'attendees'  => \ErnestDefoe\Calendar\Attendees::build($id),
        ];

        return new JsonResponse(['data' => EventSerializer::serialize($event, $actor, null, $rsvp)]);
    }
}
