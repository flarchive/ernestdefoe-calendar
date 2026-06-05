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

/** POST /api/calendar/events — create an event (requires calendar.create). */
class CreateEventController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $actor->assertCan('calendar.create');

        $attrs = (array) Arr::get((array) $request->getParsedBody(), 'data.attributes', []);

        $event = new Event();
        $event->user_id = $actor->id;
        EventInput::apply($event, $attrs, true);
        $event->save();

        $event->load(['user', 'category']);

        return new JsonResponse(['data' => EventSerializer::serialize($event, $actor)], 201);
    }
}
