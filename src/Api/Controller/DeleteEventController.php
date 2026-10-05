<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use ErnestDefoe\Calendar\Api\EventSerializer;
use ErnestDefoe\Calendar\Event;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** DELETE /api/calendar/events/{id} — delete an event (creator or manager). */
class DeleteEventController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered(); // guests → 401, not a 403 from the manage check
        $id = (int) Arr::get($request->getAttributes(), 'routeParameters.id');

        $event = Event::query()->findOrFail($id);
        if (! EventSerializer::canEdit($event, $actor)) {
            $actor->assertCan('calendar.manage');
        }

        $event->delete();

        return new EmptyResponse(204);
    }
}
