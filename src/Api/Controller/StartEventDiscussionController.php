<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use ErnestDefoe\Calendar\Api\EventSerializer;
use ErnestDefoe\Calendar\Event;
use ErnestDefoe\Calendar\EventDiscussion;
use Flarum\Http\RequestUtil;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/calendar/events/{id}/discussion — start the comment thread for an
 * event that has none (one made before threads were switched on, or whose
 * thread was deleted). Creator or manager; idempotent.
 */
class StartEventDiscussionController implements RequestHandlerInterface
{
    public function __construct(private EventDiscussion $discussions)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $id = (int) Arr::get($request->getAttributes(), 'routeParameters.id');

        $event = Event::query()->findOrFail($id);
        if (! EventSerializer::canEdit($event, $actor)) {
            $actor->assertCan('calendar.manage');
        }

        // A draft's thread would show it to everyone before it is published.
        if (! $event->is_published) {
            throw new ModelNotFoundException();
        }

        if (! $event->discussion_id) {
            $this->discussions->start($event, $actor, $request);
        }

        $event->load(['user', 'category']);

        return new JsonResponse(['data' => EventSerializer::serialize($event, $actor)]);
    }
}
