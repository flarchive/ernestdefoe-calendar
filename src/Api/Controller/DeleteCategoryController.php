<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use ErnestDefoe\Calendar\EventCategory;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** DELETE /api/calendar/categories/{id} — admin only. Events keep their date but lose the category (nullOnDelete). */
class DeleteCategoryController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $id = (int) Arr::get($request->getAttributes(), 'routeParameters.id');
        EventCategory::query()->findOrFail($id)->delete();

        return new EmptyResponse(204);
    }
}
