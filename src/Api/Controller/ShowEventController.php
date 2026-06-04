<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use ErnestDefoe\Calendar\Api\EventSerializer;
use ErnestDefoe\Calendar\Event;
use ErnestDefoe\Calendar\EventRsvp;
use Flarum\Http\RequestUtil;
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

        $rows = EventRsvp::query()->where('event_id', $id)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $rsvp = [
            'going'      => (int) ($rows[EventRsvp::GOING] ?? 0),
            'interested' => (int) ($rows[EventRsvp::INTERESTED] ?? 0),
            'mine'       => $actor->isGuest() ? null
                : EventRsvp::query()->where('event_id', $id)->where('user_id', $actor->id)->value('status'),
        ];

        return new JsonResponse(['data' => EventSerializer::serialize($event, $actor, null, $rsvp)]);
    }
}
