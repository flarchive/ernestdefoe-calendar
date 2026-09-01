<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use ErnestDefoe\Calendar\Activity\ActivityRepository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/calendar/activity/{id}
 *
 * The contribution heatmap + streak payload for one member. Public — it only
 * exposes aggregate comment counts, which are already visible via their posts.
 */
class UserActivityController implements RequestHandlerInterface
{
    public function __construct(protected ActivityRepository $activity) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userId = (int) ($request->getAttribute('routeParameters')['id'] ?? 0);

        $daily  = $this->activity->userDaily($userId, 365);
        $streak = $this->activity->streak($daily);

        $total = array_sum($daily);
        $max   = $daily ? max($daily) : 0;

        return new JsonResponse([
            'data' => [
                'userId'     => $userId,
                'days'       => (object) $daily,   // { 'Y-m-d': count }
                'streak'     => $streak,           // { current, longest }
                'total'      => $total,            // comments in the last year
                'activeDays' => count($daily),
                'max'        => $max,              // peak day, for colour scaling
            ],
        ]);
    }
}
