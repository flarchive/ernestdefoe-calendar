<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use ErnestDefoe\Calendar\EventCategory;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/calendar/categories            create
 * PATCH /api/calendar/categories/{id}      update
 * Admin only.
 */
class SaveCategoryController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $id = Arr::get($request->getAttributes(), 'routeParameters.id');
        $attrs = (array) Arr::get((array) $request->getParsedBody(), 'data.attributes', []);

        $category = $id ? EventCategory::query()->findOrFail((int) $id) : new EventCategory();

        if (array_key_exists('name', $attrs) || ! $id) {
            $name = trim((string) ($attrs['name'] ?? ''));
            if ($name === '') {
                throw new ValidationException(['name' => 'The category name is required.']);
            }
            $category->name = mb_substr($name, 0, 100);
        }
        if (array_key_exists('color', $attrs)) {
            $color = (string) $attrs['color'];
            $category->color = preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) ? $color : '#3b5bdb';
        }
        if (array_key_exists('position', $attrs)) {
            $category->position = (int) $attrs['position'];
        }

        if (! $category->slug || array_key_exists('name', $attrs)) {
            $category->slug = self::uniqueSlug($category->name, $category->id);
        }

        $category->save();

        return new JsonResponse(['data' => [
            'id'    => (int) $category->id,
            'name'  => $category->name,
            'slug'  => $category->slug,
            'color' => $category->color,
        ]], $id ? 200 : 201);
    }

    private static function uniqueSlug(string $name, $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;
        while (EventCategory::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
