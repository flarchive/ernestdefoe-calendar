<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use Carbon\Carbon;
use Flarum\User\User;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/calendar/celebrations
 *
 * Today's member milestones: opt-in birthdays (matched on the privacy-preserving
 * MM-DD field — no age exposed) and join-anniversaries (derived from the public
 * joined_at). Social glue for the celebrations widget.
 */
class CelebrationsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $now  = Carbon::now();
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
                'type'  => 'anniversary',
                'years' => $now->year - $u->joined_at->year,
            ]);

        return new JsonResponse([
            'data' => $birthdays->concat($anniversaries)->values()->all(),
        ]);
    }

    private function base(User $u): array
    {
        return [
            'userId'      => (int) $u->id,
            'username'    => $u->username,
            'displayName' => $u->display_name,
            'avatarUrl'   => $u->avatar_url,
        ];
    }
}
