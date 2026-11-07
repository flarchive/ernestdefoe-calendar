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
        // A forum guests can't view keeps its calendar from them too.
        $actor->assertCan('viewForum');
        $id = (int) Arr::get($request->getAttributes(), 'routeParameters.id');

        $event = Event::query()->with(['user', 'category'])->findOrFail($id);

        // Unpublished (draft) events are visible only to event managers or the
        // author — everyone else gets a 404 (no existence disclosure).
        if (! $event->is_published) {
            $isManager = $actor->hasPermission('calendar.manage');
            $isAuthor = $event->user_id && (int) $actor->id === (int) $event->user_id && $actor->hasPermission('calendar.create');
            if (! $isManager && ! $isAuthor) {
                throw new ModelNotFoundException();
            }
        }

        // Counts and the actor's own status in ONE grouped query: `mine` flags the
        // status group holding the actor's row (a guest's id 0 never matches).
        $rows = EventRsvp::query()->where('event_id', $id)
            ->selectRaw('status, COUNT(*) as c, MAX(CASE WHEN user_id = ? THEN 1 ELSE 0 END) as mine', [(int) $actor->id])
            ->groupBy('status')->toBase()->get()->keyBy('status');
        $rsvp = [
            'going' => (int) ($rows[EventRsvp::GOING]->c ?? 0),
            'interested' => (int) ($rows[EventRsvp::INTERESTED]->c ?? 0),
            'mine' => $rows->first(fn ($r) => (int) $r->mine === 1)?->status,
            'attendees' => \ErnestDefoe\Calendar\Attendees::build($id),
        ];

        return new JsonResponse(['data' => EventSerializer::serialize($event, $actor, null, $rsvp)]);
    }
}
