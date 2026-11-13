<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use ErnestDefoe\Calendar\Api\EventInput;
use ErnestDefoe\Calendar\Api\EventSerializer;
use ErnestDefoe\Calendar\Event;
use ErnestDefoe\Calendar\EventDiscussion;
use ErnestDefoe\Calendar\Events\EventSaved;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** PATCH /api/calendar/events/{id} — update an event (creator or manager). */
class UpdateEventController implements RequestHandlerInterface
{
    public function __construct(private EventDiscussion $discussions, private Dispatcher $events)
    {
    }

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
        $this->discussions->syncTitle($event);
        $this->events->dispatch(new EventSaved($event, $actor));

        $event->load(['user', 'category']);

        return new JsonResponse(['data' => EventSerializer::serialize($event, $actor)]);
    }
}
