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
        $event = Event::query()->findOrFail($id);

        $host = $request->getUri()->getHost() ?: 'localhost';
        $ics = (new IcalGenerator($host))->single($event, $event->title);

        $response = new Response();
        $response->getBody()->write($ics);

        return $response
            ->withHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="event-' . $event->id . '.ics"');
    }
}
