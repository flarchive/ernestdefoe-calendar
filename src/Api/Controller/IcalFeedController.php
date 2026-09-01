<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use Carbon\Carbon;
use ErnestDefoe\Calendar\Event;
use ErnestDefoe\Calendar\Ical\IcalGenerator;
use ErnestDefoe\Calendar\Recurrence\RecurrenceExpander;
use Flarum\Settings\SettingsRepositoryInterface;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /calendar/feed.ics — the whole calendar as a subscribable iCal feed.
 *
 * Recurring events are emitted once with their RRULE (the subscriber's client
 * expands them). Non-recurring events from the last month onward are included so
 * a fresh subscription shows recent + upcoming items.
 */
class IcalFeedController implements RequestHandlerInterface
{
    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Eager-load category + user so per-event serialisation below doesn't
        // lazy-load them one row at a time (N+1). The 2000-row ceiling bounds
        // peak memory for forums with long event histories / many recurrences.
        $events = Event::query()
            ->with(['category', 'user'])
            ->where('is_published', true)
            ->where(function ($q) {
                $q->whereNotNull('rrule')->orWhere('start_at', '>=', Carbon::now()->subMonth());
            })
            ->orderBy('start_at')
            ->limit(2000)
            ->get();

        $name = (string) ($this->settings->get('forum_title') ?: 'Calendar');
        $host = $request->getUri()->getHost() ?: 'localhost';

        $ics = (new IcalGenerator($host))->calendar($events, $name . ' — Calendar');

        $response = new Response();
        $response->getBody()->write($ics);

        return $response
            ->withHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->withHeader('Content-Disposition', 'inline; filename="calendar.ics"');
    }
}
