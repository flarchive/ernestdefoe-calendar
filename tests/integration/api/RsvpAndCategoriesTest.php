<?php

namespace ErnestDefoe\Calendar\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

class RsvpAndCategoriesTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-calendar');

        $event = fn (int $id, array $attrs) => $attrs + [
            'id' => $id, 'slug' => 'event-'.$id, 'start_at' => '2030-05-10 18:00:00', 'user_id' => 3,
            'is_published' => true, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
        ];

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'organiser', 'email' => 'organiser@machine.local', 'is_email_confirmed' => 1],
            ],
            'calendar_categories' => [
                ['id' => 1, 'name' => 'Meetups', 'slug' => 'meetups', 'color' => '#ff0000', 'position' => 1],
                ['id' => 2, 'name' => 'Raids', 'slug' => 'raids', 'color' => '#00ff00', 'position' => 0],
            ],
            'calendar_events' => [
                $event(1, ['title' => 'Published', 'category_id' => 1]),
                $event(2, ['title' => 'Draft', 'is_published' => false]),
            ],
        ]);
    }

    private function json(string $method, string $path, ?int $actor = null, ?array $body = null): array
    {
        $options = [];
        if ($actor) {
            $options['authenticatedAs'] = $actor;
        }
        if ($body !== null) {
            $options['json'] = $body;
        }

        $request = $this->request($method, $path, $options);
        if (! $actor) {
            // Past the CSRF check, so a guest reaches the permission check itself.
            $request = $request->withAttribute('bypassCsrfToken', true);
        }

        $response = $this->send($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    private function rsvp(int $event, ?int $actor, string $status): array
    {
        return $this->json('POST', "/api/calendar/events/$event/rsvp", $actor, ['data' => ['status' => $status]]);
    }

    #[Test]
    public function a_guest_cannot_rsvp()
    {
        $this->assertSame(401, $this->rsvp(1, null, 'going')[0]);
        $this->assertSame(0, $this->database()->table('calendar_event_rsvps')->count());
    }

    #[Test]
    public function a_member_rsvps_changes_their_mind_and_withdraws()
    {
        [$status, $body] = $this->rsvp(1, 2, 'going');
        $this->assertSame(200, $status);
        $this->assertSame(1, $body['data']['going']);
        $this->assertSame('going', $body['data']['mine']);
        $this->assertSame(['normal'], array_column($body['data']['attendees']['going'], 'username'));

        [, $body] = $this->rsvp(1, 2, 'interested');
        $this->assertSame(0, $body['data']['going']);
        $this->assertSame(1, $body['data']['interested']);
        $this->assertSame(1, $this->database()->table('calendar_event_rsvps')->count(), 'One RSVP per member, updated in place');

        [, $body] = $this->rsvp(1, 2, 'bogus');
        $this->assertSame('interested', $body['data']['mine'], 'An unknown status changes nothing');

        [, $body] = $this->rsvp(1, 2, 'none');
        $this->assertNull($body['data']['mine']);
        $this->assertSame(0, $this->database()->table('calendar_event_rsvps')->count());

        [, $body] = $this->json('GET', '/api/calendar/events/1', 2);
        $this->assertNull($body['data']['rsvp']['mine']);
    }

    #[Test]
    public function only_the_author_can_rsvp_to_a_draft()
    {
        $this->assertSame(404, $this->rsvp(2, 2, 'going')[0]);
        $this->assertSame(0, $this->database()->table('calendar_event_rsvps')->count());

        $this->assertSame(200, $this->rsvp(2, 3, 'going')[0]);
        $this->assertSame(404, $this->rsvp(999, 2, 'going')[0]);
    }

    #[Test]
    public function anyone_can_list_categories_in_order()
    {
        [$status, $body] = $this->json('GET', '/api/calendar/categories');

        $this->assertSame(200, $status);
        $this->assertSame(['raids', 'meetups'], array_column($body['data'], 'slug'));
    }

    #[Test]
    public function only_an_admin_manages_categories()
    {
        $new = ['data' => ['attributes' => ['name' => 'Socials', 'color' => '#123456']]];

        $this->assertSame(403, $this->json('POST', '/api/calendar/categories', null, $new)[0], 'Guest');
        $this->assertSame(403, $this->json('POST', '/api/calendar/categories', 2, $new)[0], 'Member');
        $this->assertSame(403, $this->json('PATCH', '/api/calendar/categories/1', 2, $new)[0], 'Member');
        $this->assertSame(403, $this->json('DELETE', '/api/calendar/categories/1', 2)[0], 'Member');
        $this->assertSame(2, $this->database()->table('calendar_categories')->count());
        $this->assertSame('Meetups', $this->database()->table('calendar_categories')->where('id', 1)->value('name'));

        [$status, $body] = $this->json('POST', '/api/calendar/categories', 1, $new);
        $this->assertSame(201, $status);
        $this->assertSame(['name' => 'Socials', 'slug' => 'socials', 'color' => '#123456'], array_intersect_key($body['data'], ['name' => 0, 'slug' => 0, 'color' => 0]));

        [$status, $body] = $this->json('PATCH', '/api/calendar/categories/1', 1, ['data' => ['attributes' => ['name' => 'Raids', 'color' => 'red;}']]]);
        $this->assertSame(200, $status);
        $this->assertSame('raids-2', $body['data']['slug'], 'A renamed category never takes another\'s slug');
        $this->assertSame('#3b5bdb', $body['data']['color'], 'Anything but a hex colour falls back to the default');

        $this->assertSame(422, $this->json('POST', '/api/calendar/categories', 1, ['data' => ['attributes' => ['name' => ' ']]])[0]);
    }

    #[Test]
    public function deleting_a_category_keeps_its_events()
    {
        $this->assertSame(204, $this->json('DELETE', '/api/calendar/categories/1', 1)[0]);

        $this->assertFalse($this->database()->table('calendar_categories')->where('id', 1)->exists());

        [$status, $body] = $this->json('GET', '/api/calendar/events/1');
        $this->assertSame(200, $status, 'The event outlives its category');
        $this->assertNull($body['data']['category']);
    }

    #[Test]
    public function the_ical_feed_lists_only_published_events()
    {
        $response = $this->send($this->request('GET', '/calendar/feed.ics'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('text/calendar', $response->getHeaderLine('Content-Type'));
        $ics = (string) $response->getBody();
        $this->assertStringContainsString("SUMMARY:Published\r\n", $ics);
        $this->assertStringContainsString("DTSTART:20300510T180000Z\r\n", $ics);
        $this->assertStringNotContainsString('Draft', $ics);
    }

    #[Test]
    public function a_draft_cannot_be_downloaded_as_ical()
    {
        $response = $this->send($this->request('GET', '/calendar/events/1/ical'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString("SUMMARY:Published\r\n", (string) $response->getBody());

        $this->assertSame(404, $this->send($this->request('GET', '/calendar/events/2/ical'))->getStatusCode());
    }
}
