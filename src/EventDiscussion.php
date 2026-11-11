<?php

namespace ErnestDefoe\Calendar;

use Carbon\Carbon;
use Flarum\Api\JsonApi;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\ValidationException;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * An event's comment thread: a real discussion, so replies, likes, mentions,
 * subscriptions and notifications are all core's own.
 *
 * 🚨 The column, the relation and the "Open discussion" button shipped in 1.0,
 * and the announcement promised the feature, but nothing ever created the
 * discussion — so the button never appeared. Reported by RelicSystem.
 *
 * The discussion is started through core's own discussion endpoint, as the
 * member, so it passes the same checks a discussion from the composer does:
 * the tags extension's required-tag and per-tag permissions included.
 */
class EventDiscussion
{
    public function __construct(
        private JsonApi $api,
        private SettingsRepositoryInterface $settings,
        private ExtensionManager $extensions,
        private UrlGenerator $url,
        private TranslatorInterface $translator,
        private LoggerInterface $log,
        private DiscussionAuthor $author,
    ) {
    }

    /** New events get a thread automatically. */
    public function automatic(): bool
    {
        return (bool) $this->settings->get('ernestdefoe-calendar.event_discussions');
    }

    /**
     * Start the thread as $actor and link it to the event. Throws whatever
     * core throws (permission, validation) so the caller can surface it.
     */
    public function start(Event $event, User $actor, ServerRequestInterface $request): Discussion
    {
        // The admin can have every thread posted by one account (a bot, say)
        // instead of by the member who made the event.
        $author = $this->author->configured() ?? $actor;

        $data = ['attributes' => ['title' => $event->title, 'content' => $this->content($event, $author)]];

        // With the tags extension on, a member's new discussion must carry a
        // tag — an empty list is refused too — so without one configured the
        // organiser is told what is missing instead of "tags is required".
        $tags = null;
        if ($this->extensions->isEnabled('flarum-tags')) {
            $tags = $this->tagIds();
            if ($tags) {
                $data['relationships']['tags']['data'] = array_map(fn (int $id) => ['type' => 'tags', 'id' => (string) $id], $tags);
            } elseif (! $author->hasPermission('bypassTagCounts')) {
                throw new ValidationException(['discussion' => $this->translator->trans('ernestdefoe-calendar.api.discussion_needs_tag')]);
            }
        }

        // Said in words an admin can act on, not core's bare 403.
        if ($problem = $this->author->problem($author, $tags ?? [])) {
            throw new ValidationException(['discussion' => $problem]);
        }

        /** @var Discussion $discussion */
        $discussion = $this->api->forResource(DiscussionResource::class)
            ->forEndpoint('create')
            // The incoming request carries the EVENT's body; process() merges
            // the base request's data in, so start from an empty one.
            ->withRequest($request->withParsedBody([])->withQueryParams([]))
            ->process(['data' => $data], [], ['actor' => $author]);

        $event->discussion_id = $discussion->id;
        $event->save();

        return $discussion;
    }

    /** The automatic start on create: never lets a thread failure lose the event. */
    public function startQuietly(Event $event, User $actor, ServerRequestInterface $request): void
    {
        try {
            $this->start($event, $actor, $request);
        } catch (\Throwable $e) {
            $this->log->warning('[calendar] event '.$event->id.' discussion not started: '.$e->getMessage());
        }
    }

    /** Keep the thread's title in step with the event's. */
    public function syncTitle(Event $event): void
    {
        if (! $event->discussion_id || ! $event->wasChanged('title')) {
            return;
        }

        $discussion = Discussion::query()->find($event->discussion_id);
        if ($discussion && $discussion->title !== $event->title) {
            $discussion->rename($event->title);
            $discussion->save();
        }
    }

    /** @return int[] */
    private function tagIds(): array
    {
        return SettingIds::from($this->settings->get('ernestdefoe-calendar.event_discussion_tags'));
    }

    private function content(Event $event, User $author): string
    {
        // The admin's date order, applied as the calendar applies it: to the
        // date only, so the clock stays the language's own 12 or 24 hours.
        $locale = $this->translator->getLocale();
        $dateLocale = $locale;
        $order = $this->settings->get('ernestdefoe-calendar.date_order');
        if ($order === 'dmy' || $order === 'mdy') {
            $dateLocale = explode('_', str_replace('-', '_', $locale))[0].($order === 'dmy' ? '_GB' : '_US');
        }
        $tz = $event->timezone ?: 'UTC';
        // An all-day event's date is stored at UTC midnight; shown in its own
        // zone it would land a day early west of Greenwich.
        $start = Carbon::parse($event->start_at)->setTimezone($event->all_day ? 'UTC' : $tz);
        $when = $start->copy()->locale($dateLocale)->isoFormat('dddd, LL');
        if (! $event->all_day) {
            $when .= ', '.$start->copy()->locale($locale)->isoFormat('LT').' ('.$tz.')';
        }

        $organiser = $event->user_id && (int) $event->user_id !== (int) $author->id ? $event->user : null;

        $lines = array_filter([
            // The cover goes first, so it is the post's first picture: themes
            // and link previews that use a discussion's first image (Masthead's
            // banner, for one) show it, not the 📅 emoji further down.
            $this->image($event->cover_url),
            trim((string) $event->description),
            '📅 '.$when,
            $event->location ? '📍 '.$event->location : null,
            // Posted by another account, the thread would not say who runs it.
            $organiser ? '👤 '.($organiser->display_name ?: $organiser->username) : null,
            '🗓️ '.$this->url->to('forum')->route('calendar').'?event='.$event->id,
        ]);

        return implode("\n\n", $lines);
    }

    /**
     * The cover as an image in whichever markup the forum parses: Markdown,
     * else BBCode, else none (a bare address would only show as text).
     * EventInput only stores http(s) and same-site addresses.
     */
    private function image(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        // Whitespace, brackets, quotes and backslashes would end the Markdown
        // address early (a same-site path is stored without validation).
        $url = (string) preg_replace_callback('/[\s\x00-\x1f()\[\]<>"\\\\]/', fn ($m) => rawurlencode($m[0]), $url);
        // A same-site path is from the site's root, not the forum's folder.
        if (str_starts_with($url, '/')) {
            $base = parse_url($this->url->to('forum')->base());
            $url = ($base['scheme'] ?? 'https').'://'.($base['host'] ?? '').(isset($base['port']) ? ':'.$base['port'] : '').$url;
        }

        if ($this->extensions->isEnabled('flarum-markdown')) {
            return '![]('.$url.')';
        }

        return $this->extensions->isEnabled('flarum-bbcode') ? '[img]'.$url.'[/img]' : null;
    }
}
