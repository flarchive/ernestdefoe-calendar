<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use Carbon\Carbon;
use ErnestDefoe\Calendar\Api\EventSerializer;
use ErnestDefoe\Calendar\Event;
use ErnestDefoe\Calendar\EventCategory;
use ErnestDefoe\Calendar\EventRsvp;
use ErnestDefoe\Calendar\Recurrence\RecurrenceExpander;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/calendar/events?from=&to=&category=
 *
 * Public, read-only listing for the calendar views and widgets. Recurring series
 * are expanded into individual occurrences within the requested window. RSVP
 * counts are batched (one grouped query) to avoid N+1.
 */
class ListEventsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor  = RequestUtil::getActor($request);
        $params = $request->getQueryParams();

        $from = self::date(Arr::get($params, 'from')) ?? Carbon::now()->startOfMonth();
        $to   = self::date(Arr::get($params, 'to')) ?? (clone $from)->addMonths(2);
        // Bound the window so a recurring series can't be expanded indefinitely.
        if ($to->lt($from)) {
            $to = (clone $from)->addMonth();
        }
        if ($from->diffInDays($to) > 420) {
            $to = (clone $from)->addDays(420);
        }

        $query = Event::query()->where('is_published', true)->with(['user', 'category'])->inWindow($from, $to);

        if ($cat = trim((string) Arr::get($params, 'category', ''))) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $cat)->orWhere('id', (int) $cat));
        }

        $events = $query->orderBy('start_at')->limit(400)->get();

        // Hard ceiling on expanded occurrences so a few dense recurring series
        // can't blow up memory/CPU (e.g. 400 daily events over a 420-day window).
        $maxOccurrences = 3000;

        $ids = $events->pluck('id')->all();
        $counts = EventRsvp::query()->whereIn('event_id', $ids)
            ->selectRaw('event_id, status, COUNT(*) as c')->groupBy('event_id', 'status')->get()
            ->groupBy('event_id');
        $mine = (! $actor->isGuest() && $ids)
            ? EventRsvp::query()->whereIn('event_id', $ids)->where('user_id', $actor->id)->pluck('status', 'event_id')
            : collect();

        $data = [];
        foreach ($events as $event) {
            if (count($data) >= $maxOccurrences) break;
            $rsvp = self::rsvpFor($event->id, $counts, $mine);

            if ($event->isRecurring()) {
                foreach (RecurrenceExpander::occurrences($event->rrule, $event->start_at, $from, $to) as $occ) {
                    if (count($data) >= $maxOccurrences) break;
                    $data[] = EventSerializer::serialize($event, $actor, $occ, $rsvp);
                }
            } else {
                $data[] = EventSerializer::serialize($event, $actor, null, $rsvp);
            }
        }

        // Stable sort by occurrence start.
        usort($data, fn ($a, $b) => strcmp($a['start'], $b['start']));

        $categories = EventCategory::query()->orderBy('position')->orderBy('name')->get()
            ->map(fn (EventCategory $c) => ['id' => (int) $c->id, 'name' => $c->name, 'slug' => $c->slug, 'color' => $c->color])
            ->values()->all();

        return new JsonResponse(['data' => $data, 'categories' => $categories]);
    }

    private static function rsvpFor($eventId, $counts, $mine): array
    {
        $rows = $counts->get($eventId);
        $going = $interested = 0;
        if ($rows) {
            foreach ($rows as $r) {
                if ($r->status === EventRsvp::GOING) $going = (int) $r->c;
                if ($r->status === EventRsvp::INTERESTED) $interested = (int) $r->c;
            }
        }
        return ['going' => $going, 'interested' => $interested, 'mine' => $mine->get($eventId)];
    }

    private static function date($value): ?Carbon
    {
        if (! $value) return null;
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
