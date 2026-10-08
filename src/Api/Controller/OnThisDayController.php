<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Cache\Repository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/calendar/onthisday.
 *
 * "On this day" memories: the most-discussed threads created on today's
 * month+day in previous years. Scoped with whereVisibleTo($actor) so it never
 * leaks threads the requester can't already see. The nostalgia engine that
 * resurrects old conversations.
 */
class OnThisDayController implements RequestHandlerInterface
{
    public function __construct(protected Repository $cache)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        // A forum guests can't view keeps its calendar from them too.
        $actor->assertCan('viewForum');
        $now = Carbon::now();
        $limit = max(1, min(20, (int) ($request->getQueryParams()['limit'] ?? 6)));

        /*
         * 🚨 The date match is cached, the rest is not. MONTH()/DAY() on
         * created_at cannot use an index, so matching it was a scan of the
         * discussions table for every visitor on every index view (the
         * widget is on by default). Which discussions were started on this
         * date in earlier years cannot change during the day, so that id list
         * is computed once a day; visibility, hiding and the comment-count
         * order are still applied live, per visitor, below.
         */
        $ids = $this->cache->remember(
            'ernestdefoe-calendar.onthisday.'.$now->toDateString(),
            3600,
            fn () => Discussion::query()
                ->whereMonth('created_at', $now->month)
                ->whereDay('created_at', $now->day)
                ->whereYear('created_at', '<', $now->year)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
        );

        if (! $ids) {
            return new JsonResponse(['data' => []]);
        }

        $discussions = Discussion::whereVisibleTo($actor)
            ->whereIn('discussions.id', $ids)
            ->whereNull('hidden_at')
            ->where('is_private', false)
            ->orderByDesc('comment_count')
            // Ties newest first: what the unordered scan happened to return,
            // made deterministic now that the plan goes by id.
            ->orderByDesc('discussions.id')
            ->limit($limit)
            ->get();

        $data = $discussions->map(fn (Discussion $d) => [
            'id' => (int) $d->id,
            'title' => $d->title,
            'slug' => $d->slug,
            'createdAt' => optional($d->created_at)->toIso8601String(),
            'yearsAgo' => $d->created_at ? $now->year - $d->created_at->year : null,
            'commentCount' => (int) $d->comment_count,
            'participantCount' => (int) $d->participant_count,
        ])->values()->all();

        return new JsonResponse(['data' => $data]);
    }
}
