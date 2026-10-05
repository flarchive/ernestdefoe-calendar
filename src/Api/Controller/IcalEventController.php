<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use ErnestDefoe\Calendar\Event;
use ErnestDefoe\Calendar\Ical\IcalGenerator;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** GET /calendar/events/{id}/ical — download a single event as .ics. */
class IcalEventController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int) Arr::get($request->getAttributes(), 'routeParameters.id');
        // Only published events are downloadable — mirror IcalFeedController so a
        // visitor who guesses an event id can't pull an unpublished/draft event's
        // full .ics (title, description, location, RRULE). Unpublished → 404.
        $event = Event::query()->where('is_published', true)->findOrFail($id);

        $host = $request->getUri()->getHost() ?: 'localhost';
        $ics = (new IcalGenerator($host))->single($event, $event->title);

        $response = new Response();
        $response->getBody()->write($ics);

        return $response
            ->withHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="event-' . $event->id . '.ics"');
    }
}
