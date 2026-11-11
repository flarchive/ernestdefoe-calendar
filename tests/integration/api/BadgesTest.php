<?php

namespace ErnestDefoe\Calendar\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/** Calendar's two FoF Badges metrics, awarded through fof/badges' triggers. */
class BadgesTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private function badge(int $id, string $metric, int $value, array $extra = []): array
    {
        return [
            'id' => $id, 'name' => "Badge $id", 'slug' => "badge-$id", 'is_active' => true,
            'trigger_config' => json_encode(['logic' => 'AND', 'conditions' => [['metric' => $metric, 'operator' => '>=', 'value' => $value]]] + $extra),
            'created_at' => Carbon::now(),
        ];
    }

    private function event(int $id, array $attrs): array
    {
        return $attrs + [
            'id' => $id, 'title' => "Event $id", 'slug' => "event-$id", 'user_id' => 3, 'all_day' => false,
            'timezone' => 'UTC', 'is_published' => true, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'organiser', 'email' => 'organiser@machine.local', 'is_email_confirmed' => 1],
            ],
            Group::class => [['id' => 5, 'name_singular' => 'Organiser', 'name_plural' => 'Organisers']],
            'group_user' => [['user_id' => 3, 'group_id' => 5]],
            'group_permission' => [['permission' => 'calendar.create', 'group_id' => 5]],
            'calendar_categories' => [
                ['id' => 1, 'name' => 'Raids', 'slug' => 'raids', 'color' => '#ff0000', 'position' => 0],
                ['id' => 2, 'name' => 'Meetups', 'slug' => 'meetups', 'color' => '#00ff00', 'position' => 1],
            ],
            'calendar_events' => [
                // Ended (timed, with an end) — a raid.
                $this->event(1, ['start_at' => Carbon::now()->subDays(3), 'end_at' => Carbon::now()->subDays(3)->addHours(2), 'category_id' => 1]),
                // Ended (all-day, no end, two days ago) — a meetup.
                $this->event(2, ['start_at' => Carbon::now()->subDays(2)->startOfDay(), 'all_day' => true, 'category_id' => 2]),
                // Still to come.
                $this->event(3, ['start_at' => Carbon::now()->addDays(3), 'category_id' => 1]),
                // Ended but a draft.
                $this->event(4, ['start_at' => Carbon::now()->subDays(5), 'is_published' => false, 'user_id' => 2]),
            ],
        ]);
    }

    private function json(string $method, string $path, int $actor, array $body): int
    {
        return $this->send($this->request($method, $path, ['authenticatedAs' => $actor, 'json' => $body]))->getStatusCode();
    }

    private function rsvp(int $event): int
    {
        return $this->json('POST', "/api/calendar/events/$event/rsvp", 2, ['data' => ['status' => 'going']]);
    }

    /** @return int[] */
    private function badgesOf(int $user): array
    {
        return $this->database()->table('fof_badge_user')->where('user_id', $user)->orderBy('badge_id')->pluck('badge_id')->map(fn ($id) => (int) $id)->all();
    }

    #[Test]
    public function going_to_ended_events_earns_the_attended_badge_at_its_threshold()
    {
        $this->extension('fof-badges', 'ernestdefoe-calendar');
        $this->prepareDatabase(['fof_badges' => [$this->badge(1, 'calendar_events_attended', 2)]]);

        $this->assertSame(200, $this->rsvp(3));
        $this->assertSame(200, $this->rsvp(1));
        $this->assertSame([], $this->badgesOf(2), 'One ended event and one still to come: 1 of 2');

        $this->assertSame(200, $this->rsvp(2));
        $this->assertSame([1], $this->badgesOf(2), 'Two ended events');
    }

    #[Test]
    public function an_event_that_ends_later_is_counted_on_the_members_next_post()
    {
        $this->extension('fof-badges', 'ernestdefoe-calendar');
        $this->prepareDatabase([
            'fof_badges' => [$this->badge(1, 'calendar_events_attended', 1)],
            // RSVP'd while the event was still to come; it has ended since.
            'calendar_event_rsvps' => [['event_id' => 1, 'user_id' => 2, 'status' => 'going', 'created_at' => Carbon::now()->subDays(4)]],
            Discussion::class => [['id' => 1, 'title' => 'Chat', 'user_id' => 3, 'comment_count' => 1, 'created_at' => Carbon::now()]],
            Post::class => [['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 3, 'type' => 'comment', 'content' => '<t><p>Hi</p></t>', 'created_at' => Carbon::now()]],
        ]);
        $this->assertSame([], $this->badgesOf(2));

        $status = $this->json('POST', '/api/posts', 2, ['data' => ['attributes' => ['content' => 'That was fun'], 'relationships' => ['discussion' => ['data' => ['type' => 'discussions', 'id' => '1']]]]]);

        $this->assertSame(201, $status);
        $this->assertSame([1], $this->badgesOf(2));
    }

    #[Test]
    public function the_attended_badge_can_count_one_category_only()
    {
        $this->extension('fof-badges', 'ernestdefoe-calendar');
        $this->prepareDatabase(['fof_badges' => [$this->badge(1, 'calendar_events_attended', 2, ['calendar_category_id' => 1])]]);

        $this->rsvp(1);
        $this->rsvp(2);
        $this->rsvp(3);

        $this->assertSame([], $this->badgesOf(2), 'Only one ended raid');
    }

    #[Test]
    public function organizing_events_earns_the_organized_badge_at_its_threshold()
    {
        $this->extension('fof-badges', 'ernestdefoe-calendar');
        $this->prepareDatabase(['fof_badges' => [$this->badge(1, 'calendar_events_organized', 4), $this->badge(2, 'calendar_events_organized', 5)]]);

        // The organiser already has three published events.
        $status = $this->json('POST', '/api/calendar/events', 3, ['data' => ['attributes' => ['title' => 'New', 'start' => '2030-06-01T18:00:00Z', 'timezone' => 'UTC']]]);

        $this->assertSame(201, $status);
        $this->assertSame([1], $this->badgesOf(3));
    }

    #[Test]
    public function without_fof_badges_nothing_changes()
    {
        $this->extension('ernestdefoe-calendar');

        $this->assertSame(200, $this->rsvp(1));
        $this->assertSame(201, $this->json('POST', '/api/calendar/events', 3, ['data' => ['attributes' => ['title' => 'New', 'start' => '2030-06-01T18:00:00Z', 'timezone' => 'UTC']]]));
    }
}
