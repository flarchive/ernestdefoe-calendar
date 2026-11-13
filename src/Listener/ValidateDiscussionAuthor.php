<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Listener;

use ErnestDefoe\Calendar\DiscussionAuthor;
use ErnestDefoe\Calendar\SettingIds;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\Event\Saving;
use Flarum\Settings\SettingsRepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Checks "Post event discussions as" when Admin → Calendar is saved, so a
 * typo or a missing permission is reported there, not later as an event
 * thread that never appears.
 */
class ValidateDiscussionAuthor
{
    private const AUTHOR = 'ernestdefoe-calendar.event_discussion_author';
    private const TAGS = 'ernestdefoe-calendar.event_discussion_tags';
    private const ON = 'ernestdefoe-calendar.event_discussions';

    public function __construct(
        private DiscussionAuthor $author,
        private SettingsRepositoryInterface $settings,
        private TranslatorInterface $translator,
    ) {
    }

    public function handle(Saving $event): void
    {
        $saving = $event->settings;
        if (! array_intersect_key($saving, [self::AUTHOR => 1, self::TAGS => 1, self::ON => 1])) {
            return;
        }

        // The page sends only what changed; the rest is what is stored.
        $value = fn (string $key) => array_key_exists($key, $saving) ? $saving[$key] : $this->settings->get($key);

        $name = trim((string) $value(self::AUTHOR));
        if ($name === '') {
            return;
        }

        $author = $this->author->configured($name);
        if (! $author) {
            throw new ValidationException([self::AUTHOR => $this->translator->trans('ernestdefoe-calendar.api.discussion_author_not_found', ['name' => $name])]);
        }

        // Permissions only matter once threads are being started.
        if (! filter_var($value(self::ON), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if ($problem = $this->author->problem($author, SettingIds::from($value(self::TAGS)))) {
            throw new ValidationException([self::AUTHOR => $problem]);
        }
    }
}
