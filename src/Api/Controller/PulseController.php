<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use ErnestDefoe\Calendar\Activity\ActivityRepository;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/calendar/pulse
 *
 * The forum-wide heartbeat: a daily activity series for the pulse widget plus a
 * "most active" leaderboard. Query params: `days` (series length, 14–365),
 * `leaderDays` (leaderboard window), `limit` (leaderboard size).
 */
class PulseController implements RequestHandlerInterface
{
    /** Seconds a computed pulse is served from the cache. */
    public const TTL = 600;

    public function __construct(protected ActivityRepository $activity, protected Repository $cache) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $qp         = $request->getQueryParams();
        $days       = max(14, min(365, (int) ($qp['days'] ?? 120)));
        $leaderDays = max(1, min(365, (int) ($qp['leaderDays'] ?? 30)));
        $limit      = max(1, min(25, (int) ($qp['limit'] ?? 10)));

        /*
         * 🚨 Cached. The pulse widget sits on the index sidebar by default,
         * so this ran two aggregate scans over up to a year of the posts
         * table for every visitor on every index view. The answer is the
         * same for everyone (aggregate counts of public comments) and a
         * heartbeat ten minutes old is still a heartbeat.
         */
        $data = $this->cache->remember(
            "ernestdefoe-calendar.pulse.$days.$leaderDays.$limit",
            self::TTL,
            fn () => $this->compute($days, $leaderDays, $limit)
        );

        return new JsonResponse(['data' => $data]);
    }

    private function compute(int $days, int $leaderDays, int $limit): array
    {
        $daily = $this->activity->forumDaily($days);

        $leaders = $this->activity->leaders($leaderDays, $limit);
        $users = User::query()->whereIn('id', array_column($leaders, 'userId'))->get()->keyBy('id');

        $board = [];
        foreach ($leaders as $row) {
            $u = $users->get($row['userId']);
            if (! $u) continue;
            $board[] = [
                'userId'      => (int) $u->id,
                'username'    => $u->username,
                'displayName' => $u->display_name,
                'avatarUrl'   => $u->avatar_url,
                'count'       => $row['count'],
            ];
        }

        return [
            'days'        => (object) $daily,
            'total'       => array_sum($daily),
            'max'         => $daily ? max($daily) : 0,
            'leaders'     => $board,
            'leaderDays'  => $leaderDays,
        ];
    }
}
