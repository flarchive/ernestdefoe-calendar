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
        $actor->assertCan('startDiscussion');

        $data = ['attributes' => ['title' => $event->title, 'content' => $this->content($event)]];

        // With the tags extension on, a member's new discussion must carry a
        // tag — an empty list is refused too — so without one configured the
        // organiser is told what is missing instead of "tags is required".
        if ($this->extensions->isEnabled('flarum-tags')) {
            $tags = $this->tagIds();
            if ($tags) {
                $data['relationships']['tags']['data'] = array_map(fn (int $id) => ['type' => 'tags', 'id' => (string) $id], $tags);
            } elseif (! $actor->hasPermission('bypassTagCounts')) {
                throw new ValidationException(['discussion' => $this->translator->trans('ernestdefoe-calendar.api.discussion_needs_tag')]);
            }
        }

        /** @var Discussion $discussion */
        $discussion = $this->api->forResource(DiscussionResource::class)
            ->forEndpoint('create')
            // The incoming request carries the EVENT's body; process() merges
            // the base request's data in, so start from an empty one.
            ->withRequest($request->withParsedBody([])->withQueryParams([]))
            ->process(['data' => $data], [], ['actor' => $actor]);

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
            $this->log->info('[calendar] event '.$event->id.' discussion not started: '.$e->getMessage());
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
        $ids = json_decode((string) $this->settings->get('ernestdefoe-calendar.event_discussion_tags'), true);

        return array_values(array_filter(array_map('intval', is_array($ids) ? $ids : [])));
    }

    private function content(Event $event): string
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

        $lines = array_filter([
            trim((string) $event->description),
            '📅 '.$when,
            $event->location ? '📍 '.$event->location : null,
            '🗓️ '.$this->url->to('forum')->route('calendar').'?event='.$event->id,
        ]);

        return implode("\n\n", $lines);
    }
}
