<?php

/*
 * The blocks in src/PageBuilder extend ernestdefoe/page-builder, an optional
 * extension that isn't on Packagist, so CI can't install it. These are its
 * block contracts (ernestdefoe/page-builder src/Block) for PHPStan to analyse
 * against. Never autoloaded; extend.php only registers the blocks when the
 * real classes exist.
 */

namespace Ernestdefoe\PageBuilder\Block;

use Flarum\User\User;

interface BlockInterface
{
    public function type(): string;

    public function name(): string;

    public function icon(): string;

    public function category(): string;

    public function defaultSettings(): array;

    public function settingsSchema(): array;

    public function resolve(array $settings, User $actor): array;
}

abstract class AbstractBlock implements BlockInterface
{
    public function category(): string
    {
        return 'content';
    }

    public function defaultSettings(): array
    {
        return [];
    }

    public function settingsSchema(): array
    {
        return [];
    }

    public function resolve(array $settings, User $actor): array
    {
        return [];
    }
}
