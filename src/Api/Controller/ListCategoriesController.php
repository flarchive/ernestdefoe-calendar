<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use ErnestDefoe\Calendar\EventCategory;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** GET /api/calendar/categories — public list of event categories. */
class ListCategoriesController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $data = EventCategory::query()->orderBy('position')->orderBy('name')->get()
            ->map(fn (EventCategory $c) => [
                'id'    => (int) $c->id,
                'name'  => $c->name,
                'slug'  => $c->slug,
                'color' => $c->color,
            ])->values()->all();

        return new JsonResponse(['data' => $data]);
    }
}
