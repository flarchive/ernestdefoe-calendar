<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use Carbon\Carbon;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/calendar/celebrations.
 *
 * Today's member milestones: opt-in birthdays (matched on the privacy-preserving
 * MM-DD field — no age exposed) and join-anniversaries (derived from the public
 * joined_at). Social glue for the celebrations widget.
 */
class CelebrationsController implements RequestHandlerInterface
{
    /** Seconds today's list is served from the cache. */
    public const TTL = 600;

    public function __construct(protected Repository $cache)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // A forum guests can't view keeps its calendar from them too.
        RequestUtil::getActor($request)->assertCan('viewForum');

        $now = Carbon::now();

        /*
         * 🚨 Cached per day. The widget sits on the index sidebar by default,
         * and the anniversary half matches on MONTH() and DAY() of joined_at,
         * which no index can serve — a scan of the users table for every
         * visitor on every index view, to produce a list that is the same for
         * everyone all day. A birthday set a moment ago shows within the TTL.
         */
        $data = $this->cache->remember(
            'ernestdefoe-calendar.celebrations.'.$now->toDateString(),
            self::TTL,
            fn () => $this->compute($now)
        );

        return new JsonResponse(['data' => $data]);
    }

    private function compute(Carbon $now): array
    {
        $mmdd = $now->format('m-d');

        $birthdays = User::query()
            ->where('cal_birthday', $mmdd)
            ->orderBy('username')
            ->limit(40)
            ->get()
            ->map(fn (User $u) => $this->base($u) + ['type' => 'birthday']);

        $anniversaries = User::query()
            ->whereNotNull('joined_at')
            ->whereMonth('joined_at', $now->month)
            ->whereDay('joined_at', $now->day)
            ->whereYear('joined_at', '<', $now->year)
            ->orderBy('username')
            ->limit(40)
            ->get()
            ->map(fn (User $u) => $this->base($u) + [
                'type' => 'anniversary',
                'years' => $now->year - $u->joined_at->year,
            ]);

        return $birthdays->concat($anniversaries)->values()->all();
    }

    private function base(User $u): array
    {
        return [
            'userId' => (int) $u->id,
            'username' => $u->username,
            'displayName' => $u->display_name,
            'avatarUrl' => $u->avatar_url,
        ];
    }
}
