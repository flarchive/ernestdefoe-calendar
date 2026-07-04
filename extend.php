<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

use Flarum\Extend;
use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Api\Resource\ForumResource;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use Flarum\Api\Resource\UserResource;
use ErnestDefoe\Calendar\Api\Controller;
use Psr\Log\LoggerInterface;

$extenders = [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less')
        ->route('/calendar', 'calendar'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    (new Extend\Locales(__DIR__ . '/resources/locale')),

    // ---- Read/write JSON API (custom controllers) ----
    (new Extend\Routes('api'))
        ->get('/calendar/events', 'calendar.events.list', Controller\ListEventsController::class)
        ->get('/calendar/events/{id}', 'calendar.events.show', Controller\ShowEventController::class)
        ->post('/calendar/events', 'calendar.events.create', Controller\CreateEventController::class)
        ->patch('/calendar/events/{id}', 'calendar.events.update', Controller\UpdateEventController::class)
        ->delete('/calendar/events/{id}', 'calendar.events.delete', Controller\DeleteEventController::class)
        ->post('/calendar/events/{id}/rsvp', 'calendar.events.rsvp', Controller\RsvpController::class)
        ->get('/calendar/categories', 'calendar.categories.list', Controller\ListCategoriesController::class)
        ->post('/calendar/categories', 'calendar.categories.create', Controller\SaveCategoryController::class)
        ->patch('/calendar/categories/{id}', 'calendar.categories.update', Controller\SaveCategoryController::class)
        ->delete('/calendar/categories/{id}', 'calendar.categories.delete', Controller\DeleteCategoryController::class)
        // ---- Engagement: activity heatmap / streaks / forum pulse ----
        ->get('/calendar/activity/{id}', 'calendar.activity.user', Controller\UserActivityController::class)
        ->get('/calendar/pulse', 'calendar.activity.pulse', Controller\PulseController::class)
        // ---- Engagement: on-this-day memories + member celebrations ----
        ->get('/calendar/onthisday', 'calendar.onthisday', Controller\OnThisDayController::class)
        ->get('/calendar/celebrations', 'calendar.celebrations', Controller\CelebrationsController::class),

    // ---- iCal export (served as text/calendar for direct download / subscription) ----
    (new Extend\Routes('forum'))
        ->get('/calendar/feed.ics', 'calendar.ical.feed', Controller\IcalFeedController::class)
        ->get('/calendar/events/{id}/ical', 'calendar.ical.event', Controller\IcalEventController::class),

    // ---- Expose settings + the create permission to the forum payload ----
    (new Extend\Settings())
        ->serializeToForum('ernestdefoe-calendar.defaultView', 'ernestdefoe-calendar.default_view', fn ($v) => $v ?: 'month')
        ->serializeToForum('ernestdefoe-calendar.weekStartsOn', 'ernestdefoe-calendar.week_starts_on', fn ($v) => (int) ($v ?? 0))
        ->serializeToForum('ernestdefoe-calendar.showIndexWidget', 'ernestdefoe-calendar.show_index_widget', fn ($v) => (bool) $v)
        ->serializeToForum('ernestdefoe-calendar.indexWidgetCount', 'ernestdefoe-calendar.index_widget_count', fn ($v) => (int) ($v ?: 5))
        ->serializeToForum('ernestdefoe-calendar.showPulseWidget', 'ernestdefoe-calendar.show_pulse_widget', fn ($v) => $v === null ? true : filter_var($v, FILTER_VALIDATE_BOOLEAN))
        ->serializeToForum('ernestdefoe-calendar.showMemoriesWidget', 'ernestdefoe-calendar.show_memories_widget', fn ($v) => $v === null ? true : filter_var($v, FILTER_VALIDATE_BOOLEAN))
        ->serializeToForum('ernestdefoe-calendar.showCelebrationsWidget', 'ernestdefoe-calendar.show_celebrations_widget', fn ($v) => $v === null ? true : filter_var($v, FILTER_VALIDATE_BOOLEAN)),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(function () {
            // Resolve fof-upload availability ONCE here, in the fields builder
            // (request-time, not file-parse time) — keeps resolve() out of the
            // per-field ->get() closure while staying a process-cheap single call.
            $coverUploadsEnabled = false;
            try {
                $coverUploadsEnabled = resolve(ExtensionManager::class)->isEnabled('fof-upload');
            } catch (\Throwable $e) {
                try {
                    resolve(LoggerInterface::class)->debug('[calendar] fof-upload detection failed: ' . $e->getMessage());
                } catch (\Throwable $ignored) {
                }
            }

            return [
                Schema\Boolean::make('canCreateEvent')
                    ->get(fn ($model, Context $context) => $context->getActor()->can('calendar.create')),
                Schema\Boolean::make('canManageCalendar')
                    ->get(fn ($model, Context $context) => $context->getActor()->can('calendar.manage')),
                // True when FoF Upload is installed + enabled AND the actor may upload,
                // so the event form can offer a real file picker for cover images
                // (it always falls back to a plain URL field otherwise).
                Schema\Boolean::make('calendarCoverUploads')
                    ->get(fn ($model, Context $context) => $coverUploadsEnabled && $context->getActor()->hasPermission('fof-upload.upload')),
            ];
        }),

    // ---- Opt-in birthday (MM-DD only) on the user resource; self-editable. ----
    (new Extend\ApiResource(UserResource::class))
        ->fields(fn () => [
            Schema\Str::make('calendarBirthday')
                ->nullable()
                ->writable(fn (User $user, Context $context) => $context->getActor()->id === $user->id
                    || $context->getActor()->can('editUser', $user))
                ->get(fn (User $user) => $user->cal_birthday)
                ->set(function (User $user, ?string $value) {
                    $value = is_string($value) ? trim($value) : '';
                    $user->cal_birthday = preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $value) ? $value : null;
                }),
        ]),

    // ---- Live countdowns: a [countdown] BBCode usable in any post ----
    // The formatter emits a placeholder span; countdowns.ts turns it into a live
    // ticking timer client-side. Server output is cached HTML, so the clock has
    // to run on the client. `[countdown=2026-12-31T23:59:59]New Year[/countdown]`.
    (new Extend\Formatter())
        ->configure(function ($config) {
            $config->BBCodes->addCustom(
                '[COUNTDOWN={TEXT1}]{TEXT2}[/COUNTDOWN]',
                '<span class="CalCountdown" data-deadline="{TEXT1}">{TEXT2}</span>'
            );
        }),
];

// ---- Page Builder blocks (palette) ----------------------------------------
// The JS render components for these blocks are always registered (in
// integrations.ts, guarded on app.pageBuilder). But to appear in Page Builder's
// editor PALETTE a block must also have a SERVER manifest entry — contributed
// via Page Builder's own PageBuilderBlock extender. Only register when Page
// Builder is installed, so this is a no-op (and the AbstractBlock parent stays
// unautoloaded) on a forum without it.
if (class_exists(\Ernestdefoe\PageBuilder\Extend\PageBuilderBlock::class)) {
    $extenders[] = new \Ernestdefoe\PageBuilder\Extend\PageBuilderBlock(\ErnestDefoe\Calendar\PageBuilder\EventsBlock::class);
    $extenders[] = new \Ernestdefoe\PageBuilder\Extend\PageBuilderBlock(\ErnestDefoe\Calendar\PageBuilder\PulseBlock::class);
    $extenders[] = new \Ernestdefoe\PageBuilder\Extend\PageBuilderBlock(\ErnestDefoe\Calendar\PageBuilder\MemoriesBlock::class);
    $extenders[] = new \Ernestdefoe\PageBuilder\Extend\PageBuilderBlock(\ErnestDefoe\Calendar\PageBuilder\CelebrationsBlock::class);
}

return $extenders;
