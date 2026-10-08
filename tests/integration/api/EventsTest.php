<?php

namespace ErnestDefoe\Calendar\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

class EventsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-calendar');

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'organiser', 'email' => 'organiser@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'manager', 'email' => 'manager@machine.local', 'is_email_confirmed' => 1],
                ['id' => 5, 'username' => 'organiser2', 'email' => 'organiser2@machine.local', 'is_email_confirmed' => 1],
            ],
            Group::class => [
                ['id' => 5, 'name_singular' => 'Organiser', 'name_plural' => 'Organisers'],
                ['id' => 6, 'name_singular' => 'Manager', 'name_plural' => 'Managers'],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => 5],
                ['user_id' => 5, 'group_id' => 5],
                ['user_id' => 4, 'group_id' => 6],
            ],
            'group_permission' => [
                ['permission' => 'calendar.create', 'group_id' => 5],
                ['permission' => 'calendar.manage', 'group_id' => 6],
            ],
            'calendar_categories' => [
                ['id' => 1, 'name' => 'Meetups', 'slug' => 'meetups', 'color' => '#ff0000', 'position' => 0],
            ],
            'calendar_events' => [
                $this->event(1, ['title' => 'Published', 'start_at' => '2030-05-10 18:00:00', 'end_at' => '2030-05-10 20:00:00', 'category_id' => 1, 'user_id' => 3]),
                $this->event(2, ['title' => 'Draft', 'start_at' => '2030-05-12 18:00:00', 'is_published' => false, 'user_id' => 3]),
                // A series that began, and whose first occurrence ended, before the window.
                $this->event(3, ['title' => 'Weekly', 'start_at' => '2030-04-24 09:00:00', 'end_at' => '2030-04-24 10:00:00', 'rrule' => 'FREQ=WEEKLY;COUNT=4', 'user_id' => 3]),
                $this->event(4, ['title' => 'Outside the window', 'start_at' => '2031-01-01 09:00:00', 'user_id' => 3]),
            ],
        ]);
    }

    private function event(int $id, array $attrs): array
    {
        return $attrs + [
            'id' => $id,
            'slug' => 'event-'.$id,
            'all_day' => false,
            'timezone' => 'UTC',
            'is_published' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    private function json(string $method, string $path, ?int $actor = null, ?array $body = null, array $query = []): array
    {
        $options = [];
        if ($actor) {
            $options['authenticatedAs'] = $actor;
        }
        if ($body !== null) {
            $options['json'] = $body;
        }

        $request = $this->request($method, $path, $options)->withQueryParams($query);
        if (! $actor) {
            // Past the CSRF check, so a guest reaches the permission check itself.
            $request = $request->withAttribute('bypassCsrfToken', true);
        }

        $response = $this->send($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    private function list(?int $actor = null, array $query = []): array
    {
        return $this->json('GET', '/api/calendar/events', $actor, null, $query + ['from' => '2030-05-01T00:00:00Z', 'to' => '2030-05-31T23:59:59Z']);
    }

    #[Test]
    public function the_list_shows_published_events_in_the_window_with_recurrences_expanded()
    {
        [$status, $body] = $this->list();
        $this->assertSame(200, $status);
        $titles = array_column($body['data'], 'title');
        $this->assertNotContains('Draft', $titles, 'An unpublished event is never listed');
        $this->assertNotContains('Outside the window', $titles);
        $this->assertSame(1, count(array_keys($titles, 'Published')));
        $this->assertSame(3, count(array_keys($titles, 'Weekly')), 'COUNT=4 gives four weekly occurrences, three of them in May');

        $weekly = array_values(array_filter($body['data'], fn ($e) => $e['title'] === 'Weekly'));
        $this->assertSame(
            ['2030-05-01', '2030-05-08', '2030-05-15'],
            array_map(fn ($e) => substr($e['start'], 0, 10), $weekly)
        );
        $this->assertSame(3, $weekly[0]['id'], 'Each occurrence keeps the series id');

        $published = $body['data'][array_search('Published', $titles)];
        $this->assertSame(['id' => 1, 'name' => 'Meetups', 'color' => '#ff0000'], $published['category']);
        $this->assertSame('organiser', $published['author']['username']);
        $this->assertFalse($published['canEdit'], 'A guest can edit nothing');
    }

    #[Test]
    public function the_list_filters_by_category_slug()
    {
        [, $body] = $this->list(null, ['category' => 'meetups']);

        $this->assertSame(['Published'], array_column($body['data'], 'title'));
    }

    #[Test]
    public function the_list_does_not_query_per_event()
    {
        $events = [];
        $rsvps = [];
        for ($id = 10; $id < 30; $id++) {
            $events[] = $this->event($id, ['title' => "Event $id", 'start_at' => '2030-05-20 10:00:00', 'category_id' => 1, 'user_id' => 2 + $id % 4]);
            $rsvps[] = ['event_id' => $id, 'user_id' => 2, 'status' => 'going', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()];
        }
        $this->prepareDatabase(['calendar_events' => $events, 'calendar_event_rsvps' => $rsvps]);

        // The repeated-query detector fails the request on a per-event query.
        [$status, $body] = $this->list(2);

        $this->assertSame(200, $status);
        $this->assertCount(24, $body['data']);
        $event = $body['data'][array_search('Event 10', array_column($body['data'], 'title'))];
        $this->assertSame(['going' => 1, 'interested' => 0, 'mine' => 'going', 'attendees' => null], $event['rsvp']);
        $this->assertSame('Meetups', $event['category']['name']);
        foreach ($body['data'] as $listed) {
            $this->assertNotNull($listed['author'], 'Every author is loaded, in one query');
        }
    }

    #[Test]
    public function a_draft_is_visible_only_to_its_author_and_managers()
    {
        $this->assertSame(404, $this->json('GET', '/api/calendar/events/2')[0], 'Guest');
        $this->assertSame(404, $this->json('GET', '/api/calendar/events/2', 2)[0], 'Another member');
        $this->assertSame(404, $this->json('GET', '/api/calendar/events/2', 5)[0], 'Another organiser');
        $this->assertSame(200, $this->json('GET', '/api/calendar/events/2', 3)[0], 'Its author');
        $this->assertSame(200, $this->json('GET', '/api/calendar/events/2', 4)[0], 'A manager');
        $this->assertSame(200, $this->json('GET', '/api/calendar/events/2', 1)[0], 'An admin');

        $this->assertSame(404, $this->json('GET', '/api/calendar/events/999')[0]);
    }

    #[Test]
    public function can_edit_is_true_only_for_the_author_and_managers()
    {
        $canEdit = fn (?int $actor) => $this->json('GET', '/api/calendar/events/1', $actor)[1]['data']['canEdit'];

        $this->assertFalse($canEdit(null));
        $this->assertFalse($canEdit(2));
        $this->assertFalse($canEdit(5), 'Another organiser');
        $this->assertTrue($canEdit(3), 'Its author');
        $this->assertTrue($canEdit(4), 'A manager');
        $this->assertTrue($canEdit(1), 'An admin');
    }

    #[Test]
    public function a_published_event_shows_to_a_guest()
    {
        [$status, $body] = $this->json('GET', '/api/calendar/events/1');

        $this->assertSame(200, $status);
        $this->assertSame('Published', $body['data']['title']);
        $this->assertSame('2030-05-10T18:00:00+00:00', $body['data']['start']);
        $this->assertSame('2030-05-10T20:00:00+00:00', $body['data']['end']);
        $this->assertSame(['going' => [], 'interested' => []], $body['data']['rsvp']['attendees']);
        $this->assertNull($body['data']['rsvp']['mine']);
    }

    #[Test]
    public function only_members_with_the_create_permission_can_create()
    {
        $event = ['data' => ['attributes' => ['title' => 'New', 'start' => '2030-06-01T10:00:00Z']]];

        $this->assertSame(401, $this->json('POST', '/api/calendar/events', null, $event)[0], 'Guest');
        $this->assertSame(403, $this->json('POST', '/api/calendar/events', 2, $event)[0], 'Member without permission');

        [$status, $body] = $this->json('POST', '/api/calendar/events', 3, $event);
        $this->assertSame(201, $status);
        $this->assertSame('New', $body['data']['title']);
        $this->assertSame('organiser', $body['data']['author']['username']);
        $this->assertTrue($body['data']['canEdit']);
        $this->assertSame(3, $this->database()->table('calendar_events')->where('id', $body['data']['id'])->value('user_id'));
    }

    #[Test]
    public function creating_validates_and_sanitises_the_input()
    {
        [$status] = $this->json('POST', '/api/calendar/events', 3, ['data' => ['attributes' => ['title' => '  ', 'start' => '2030-06-01T10:00:00Z']]]);
        $this->assertSame(422, $status, 'A title is required');

        [$status] = $this->json('POST', '/api/calendar/events', 3, ['data' => ['attributes' => ['title' => 'When?', 'start' => 'not a date']]]);
        $this->assertSame(422, $status, 'A start is required');
        $this->assertSame(0, $this->database()->table('calendar_events')->where('user_id', 3)->where('title', 'When?')->count());

        [$status] = $this->json('POST', '/api/calendar/events', 3, ['data' => ['attributes' => [
            'title' => 'Backwards', 'start' => '2030-06-02T10:00:00Z', 'end' => '2030-06-01T10:00:00Z',
        ]]]);
        $this->assertSame(422, $status, 'An end before the start is rejected');

        [$status, $body] = $this->json('POST', '/api/calendar/events', 3, ['data' => ['attributes' => [
            'title' => 'Links',
            'start' => '2030-06-01T10:00:00Z',
            'url' => 'javascript://example.com/%0Aalert(1)',
            'coverUrl' => '//evil.example/x.png',
            'timezone' => 'Not/AZone',
        ]]]);
        $this->assertSame(201, $status);
        $this->assertNull($body['data']['url'], 'A javascript: link is never stored');
        $this->assertNull($body['data']['coverUrl'], 'A protocol-relative URL is never stored');
        $this->assertSame('UTC', $body['data']['timezone']);
    }

    #[Test]
    public function two_events_with_one_title_get_distinct_slugs()
    {
        $event = ['data' => ['attributes' => ['title' => 'Same', 'start' => '2030-06-01T10:00:00Z']]];

        $first = $this->json('POST', '/api/calendar/events', 3, $event)[1]['data']['slug'];
        $second = $this->json('POST', '/api/calendar/events', 3, $event)[1]['data']['slug'];

        $this->assertSame('same', $first);
        $this->assertSame('same-2', $second);
    }

    #[Test]
    public function only_the_author_or_a_manager_can_update()
    {
        $patch = ['data' => ['attributes' => ['title' => 'Renamed']]];

        $this->assertSame(401, $this->json('PATCH', '/api/calendar/events/1', null, $patch)[0], 'Guest');
        $this->assertSame(403, $this->json('PATCH', '/api/calendar/events/1', 2, $patch)[0], 'Member');
        $this->assertSame(403, $this->json('PATCH', '/api/calendar/events/1', 5, $patch)[0], 'Another organiser');
        $this->assertSame('Published', $this->database()->table('calendar_events')->where('id', 1)->value('title'));

        [$status, $body] = $this->json('PATCH', '/api/calendar/events/1', 3, $patch);
        $this->assertSame(200, $status, 'Author');
        $this->assertSame('Renamed', $body['data']['title']);

        $this->assertSame(200, $this->json('PATCH', '/api/calendar/events/1', 4, ['data' => ['attributes' => ['title' => 'Managed']]])[0], 'Manager');
        $this->assertSame('Managed', $this->database()->table('calendar_events')->where('id', 1)->value('title'));
    }

    #[Test]
    public function only_the_author_or_a_manager_can_delete()
    {
        $this->assertSame(401, $this->json('DELETE', '/api/calendar/events/1')[0], 'Guest');
        $this->assertSame(403, $this->json('DELETE', '/api/calendar/events/1', 2)[0], 'Member');
        $this->assertSame(403, $this->json('DELETE', '/api/calendar/events/1', 5)[0], 'Another organiser');
        $this->assertTrue($this->database()->table('calendar_events')->where('id', 1)->exists());

        $this->assertSame(204, $this->json('DELETE', '/api/calendar/events/1', 3)[0], 'Author');
        $this->assertFalse($this->database()->table('calendar_events')->where('id', 1)->exists());

        $this->assertSame(204, $this->json('DELETE', '/api/calendar/events/3', 4)[0], 'Manager');
        $this->assertFalse($this->database()->table('calendar_events')->where('id', 3)->exists());
    }
}
