<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/calendar/onthisday
 *
 * "On this day" memories: the most-discussed threads created on today's
 * month+day in previous years. Scoped with whereVisibleTo($actor) so it never
 * leaks threads the requester can't already see. The nostalgia engine that
 * resurrects old conversations.
 */
class OnThisDayController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $now   = Carbon::now();
        $limit = max(1, min(20, (int) ($request->getQueryParams()['limit'] ?? 6)));

        $discussions = Discussion::whereVisibleTo($actor)
            ->whereMonth('created_at', $now->month)
            ->whereDay('created_at', $now->day)
            ->whereYear('created_at', '<', $now->year)
            ->whereNull('hidden_at')
            ->where('is_private', false)
            ->orderByDesc('comment_count')
            ->limit($limit)
            ->get();

        $data = $discussions->map(fn (Discussion $d) => [
            'id'               => (int) $d->id,
            'title'            => $d->title,
            'slug'             => $d->slug,
            'createdAt'        => optional($d->created_at)->toIso8601String(),
            'yearsAgo'         => $d->created_at ? $now->year - $d->created_at->year : null,
            'commentCount'     => (int) $d->comment_count,
            'participantCount' => (int) $d->participant_count,
        ])->values()->all();

        return new JsonResponse(['data' => $data]);
    }
}
