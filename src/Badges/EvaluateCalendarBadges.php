<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Badges;

use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use Illuminate\Contracts\Container\Container;

/**
 * Awards FoF Badges that use Calendar's metrics when an RSVP changes or an
 * event is saved.
 *
 * fof/badges' own listener only knows which user an event is about for the
 * events it ships with, so Calendar's events are evaluated here, with the
 * extractor each metric declares. Posts and sign-ins are left to fof/badges.
 */
class EvaluateCalendarBadges
{
    public function __construct(
        private ExtensionManager $extensions,
        private Container $container,
    ) {
    }

    public function handle(object $event): void
    {
        if (! $this->extensions->isEnabled('fof-badges') || ! class_exists('FoF\Badges\Trigger\MetricManager')) {
            return;
        }

        $metrics = $this->container->make('FoF\Badges\Trigger\MetricManager');
        $evaluator = $this->container->make('FoF\Badges\Trigger\TriggerEvaluator');
        $awarder = $this->container->make('FoF\Badges\BadgeAwarder');
        $class = get_class($event);

        $users = [];
        foreach ($metrics->getTypesForEvent($class) as $type) {
            $user = ($metrics->getUserExtractor($type, $class))($event);
            if ($user instanceof User && $user->exists) {
                $users[$user->id] = $user;
            }
        }

        foreach ($users as $user) {
            foreach ($evaluator->evaluateForEvent($user, $class) as $badge) {
                $awarder->award($user, $badge);
            }
        }
    }
}
