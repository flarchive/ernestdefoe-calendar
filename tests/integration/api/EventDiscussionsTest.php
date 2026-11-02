<?php

namespace ErnestDefoe\Calendar\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Group\Group;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

class EventDiscussionsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'ernestdefoe-calendar');

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'organiser', 'email' => 'organiser@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'organiser2', 'email' => 'organiser2@machine.local', 'is_email_confirmed' => 1],
            ],
            Group::class => [
                ['id' => 5, 'name_singular' => 'Organiser', 'name_plural' => 'Organisers'],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => 5],
                ['user_id' => 4, 'group_id' => 5],
            ],
            'group_permission' => [
                ['permission' => 'calendar.create', 'group_id' => 5],
            ],
            Tag::class => [
                ['id' => 1, 'name' => 'Events', 'slug' => 'events', 'position' => 0],
            ],
            'calendar_events' => [
                [
                    'id' => 1, 'title' => 'Made before threads', 'slug' => 'made-before', 'user_id' => 3,
                    'start_at' => '2030-05-10 18:00:00', 'all_day' => false, 'timezone' => 'UTC', 'is_published' => true,
                    'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
                ],
                [
                    'id' => 2, 'title' => 'Secret draft', 'slug' => 'secret-draft', 'user_id' => 3,
                    'start_at' => '2030-05-11 18:00:00', 'all_day' => false, 'timezone' => 'UTC', 'is_published' => false,
                    'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
                ],
            ],
        ]);

        $this->setting('ernestdefoe-calendar.event_discussion_tags', '[1]');
    }

    private function json(string $method, string $path, int $actor, ?array $attrs = null): array
    {
        $options = ['authenticatedAs' => $actor];
        if ($attrs !== null) {
            $options['json'] = ['data' => ['attributes' => $attrs]];
        }

        $response = $this->send($this->request($method, $path, $options));

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    private function create(array $attrs = []): array
    {
        return $this->json('POST', '/api/calendar/events', 3, $attrs + [
            'title' => 'Launch party', 'start' => '2030-06-01T18:00:00Z', 'timezone' => 'UTC', 'location' => 'The Old Mill',
        ]);
    }

    #[Test]
    public function no_thread_is_started_while_the_setting_is_off()
    {
        [$status, $body] = $this->create();

        $this->assertSame(201, $status);
        $this->assertNull($body['data']['discussionId']);
        $this->assertSame(0, Discussion::query()->count());
    }

    #[Test]
    public function a_new_event_gets_a_thread_started_by_its_organiser()
    {
        $this->setting('ernestdefoe-calendar.event_discussions', '1');

        [$status, $body] = $this->create();

        $this->assertSame(201, $status);
        $discussion = Discussion::query()->find($body['data']['discussionId']);
        $this->assertNotNull($discussion);
        $this->assertSame('Launch party', $discussion->title);
        $this->assertSame(3, (int) $discussion->user_id);
        $this->assertSame(1, (int) $discussion->comment_count);

        $content = $discussion->firstPost->content;
        $this->assertStringContainsString('The Old Mill', $content);
        $this->assertStringContainsString('/calendar?event='.$body['data']['id'], $content);
    }

    #[Test]
    public function the_thread_carries_the_configured_tags()
    {
        $this->setting('ernestdefoe-calendar.event_discussions', '1');

        [, $body] = $this->create();

        $discussion = Discussion::query()->find($body['data']['discussionId']);
        $this->assertSame([1], $discussion->tags()->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    #[Test]
    public function a_thread_the_forum_refuses_never_loses_the_event()
    {
        $this->setting('ernestdefoe-calendar.event_discussions', '1');
        // Tags are on and the admin has chosen none.
        $this->setting('ernestdefoe-calendar.event_discussion_tags', '[]');

        [$status, $body] = $this->create();

        $this->assertSame(201, $status, 'The event is saved');
        $this->assertNull($body['data']['discussionId']);
        $this->assertSame(0, Discussion::query()->count());

        // Starting it by hand says what is missing.
        [$status, $error] = $this->json('POST', '/api/calendar/events/'.$body['data']['id'].'/discussion', 3);
        $this->assertSame(422, $status);
        $this->assertSame('/data/attributes/discussion', $error['errors'][0]['source']['pointer']);
    }

    #[Test]
    public function renaming_the_event_renames_its_thread()
    {
        $this->setting('ernestdefoe-calendar.event_discussions', '1');
        [, $body] = $this->create();

        [$status] = $this->json('PATCH', '/api/calendar/events/'.$body['data']['id'], 3, ['title' => 'Launch party (moved)']);

        $this->assertSame(200, $status);
        $this->assertSame('Launch party (moved)', Discussion::query()->find($body['data']['discussionId'])->title);
    }

    #[Test]
    public function an_older_event_gets_its_thread_on_request_once()
    {
        [$status, $body] = $this->json('POST', '/api/calendar/events/1/discussion', 3);

        $this->assertSame(200, $status);
        $id = $body['data']['discussionId'];
        $this->assertSame('Made before threads', Discussion::query()->find($id)->title);

        [$status, $body] = $this->json('POST', '/api/calendar/events/1/discussion', 3);
        $this->assertSame(200, $status);
        $this->assertSame($id, $body['data']['discussionId'], 'A second request starts no second thread');
        $this->assertSame(1, Discussion::query()->count());
    }

    #[Test]
    public function only_the_organiser_or_a_manager_can_start_it()
    {
        [$status] = $this->json('POST', '/api/calendar/events/1/discussion', 4);
        $this->assertSame(403, $status);

        [$status] = $this->json('POST', '/api/calendar/events/1/discussion', 2);
        $this->assertSame(403, $status);

        $this->assertSame(0, Discussion::query()->count());
    }

    #[Test]
    public function a_draft_never_gets_a_public_thread()
    {
        [$status] = $this->json('POST', '/api/calendar/events/2/discussion', 3);

        $this->assertSame(404, $status);
        $this->assertSame(0, Discussion::query()->count());
    }
}
