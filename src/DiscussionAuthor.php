<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar;

use Flarum\Extension\ExtensionManager;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Who starts an event's discussion, and whether Flarum will let them.
 *
 * 🚨 "Start discussions without approval" does not let an account start a
 * discussion: it only lets the ones it starts skip the approval queue. On a
 * restricted tag the account needs "Start discussions" in that tag as well.
 * Calendar used to pass core's bare "permission denied" on, which read as a
 * Calendar bug. Reported by RelicSystem (discuss.flarum.org/d/39377/65).
 */
class DiscussionAuthor
{
    public function __construct(
        private SettingsRepositoryInterface $settings,
        private ExtensionManager $extensions,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * The account set under "Post event discussions as": a user ID or a
     * username. Empty, or an account that no longer exists, means the
     * member who made the event.
     */
    public function configured(?string $value = null): ?User
    {
        $value = trim((string) ($value ?? $this->settings->get('ernestdefoe-calendar.event_discussion_author')));
        if ($value === '') {
            return null;
        }

        return ctype_digit($value)
            ? User::query()->find((int) $value)
            : User::query()->where('username', $value)->first();
    }

    /**
     * Why $author cannot start a discussion in these tags, in words an admin
     * can act on; null when it can.
     *
     * @param int[] $tagIds
     */
    public function problem(User $author, array $tagIds): ?string
    {
        $account = ['account' => $author->display_name ?: $author->username];

        if (! $author->can('startDiscussion')) {
            return $this->translator->trans('ernestdefoe-calendar.api.discussion_author_cannot_start', $account);
        }

        if (! $tagIds || ! $this->extensions->isEnabled('flarum-tags')) {
            return null;
        }

        $refused = \Flarum\Tags\Tag::query()->whereIn('id', $tagIds)->get()
            // A restricted tag must also be visible to it, or core answers 404.
            ->reject(fn ($tag) => $author->can('viewForum', $tag) && $author->can('startDiscussion', $tag))
            ->pluck('name');

        return $refused->isEmpty() ? null : $this->translator->trans(
            'ernestdefoe-calendar.api.discussion_author_cannot_start_in_tags',
            $account + ['tags' => $refused->implode(', ')]
        );
    }
}
