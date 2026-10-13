<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use ErnestDefoe\Calendar\Api\EventInput;
use ErnestDefoe\Calendar\Api\EventSerializer;
use ErnestDefoe\Calendar\Event;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** PATCH /api/calendar/events/{id} — update an event (creator or manager). */
class UpdateEventController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered(); // guests → 401, not a 403 from the manage check
        $id = (int) Arr::get($request->getAttributes(), 'routeParameters.id');

        $event = Event::query()->findOrFail($id);
        if (! EventSerializer::canEdit($event, $actor)) {
            $actor->assertCan('calendar.manage'); // throws PermissionDeniedException
        }

        $attrs = (array) Arr::get((array) $request->getParsedBody(), 'data.attributes', []);
        EventInput::apply($event, $attrs, false);
        $event->save();

        $event->load(['user', 'category']);

        return new JsonResponse(['data' => EventSerializer::serialize($event, $actor)]);
    }
}
