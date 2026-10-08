<?php

namespace ErnestDefoe\Calendar\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Group\Group;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository;
use PHPUnit\Framework\Attributes\Test;

class EngagementTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-calendar');

        $now = Carbon::now();
        // Four years back always lands on the same month and day, 29 February included.
        $then = $now->copy()->subYears(4);

        $post = fn (int $id, int $discussion, int $user, Carbon $at, array $extra = []) => $extra + [
            'id' => $id, 'discussion_id' => $discussion, 'number' => $id, 'user_id' => $user,
            'type' => 'comment', 'content' => '<t><p>Post</p></t>', 'created_at' => $at,
        ];

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'birthday', 'email' => 'birthday@machine.local', 'is_email_confirmed' => 1, 'cal_birthday' => $now->format('m-d'), 'joined_at' => $now],
                ['id' => 4, 'username' => 'veteran', 'email' => 'veteran@machine.local', 'is_email_confirmed' => 1, 'joined_at' => $then],
                ['id' => 5, 'username' => 'organiser', 'email' => 'organiser@machine.local', 'is_email_confirmed' => 1, 'cal_birthday' => $now->copy()->addDays(2)->format('m-d')],
            ],
            Group::class => [
                ['id' => 5, 'name_singular' => 'Organiser', 'name_plural' => 'Organisers'],
            ],
            'group_user' => [
                ['user_id' => 5, 'group_id' => 5],
            ],
            'group_permission' => [
                ['permission' => 'calendar.create', 'group_id' => 5],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Years ago today', 'created_at' => $then, 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 7],
                ['id' => 2, 'title' => 'Hidden years ago', 'created_at' => $then, 'user_id' => 2, 'first_post_id' => 2, 'comment_count' => 1, 'hidden_at' => $now],
                ['id' => 3, 'title' => 'Private years ago', 'created_at' => $then, 'user_id' => 2, 'first_post_id' => 3, 'comment_count' => 1, 'is_private' => true],
                // Core hides a discussion with no comments from all but its author and moderators.
                ['id' => 5, 'title' => 'No comments', 'created_at' => $then, 'user_id' => 2, 'first_post_id' => null, 'comment_count' => 0],
                ['id' => 4, 'title' => 'Started today', 'created_at' => $now, 'user_id' => 2, 'first_post_id' => 4, 'comment_count' => 4],
            ],
            Post::class => [
                $post(1, 1, 2, $then),
                $post(2, 2, 2, $then),
                $post(3, 3, 2, $then),
                // Member 2: two comments today, one yesterday, plus a hidden and a private one that never count.
                $post(4, 4, 2, $now),
                $post(5, 4, 2, $now),
                $post(6, 4, 2, $now->copy()->subDay()),
                $post(7, 4, 2, $now, ['hidden_at' => $now]),
                $post(8, 4, 2, $now, ['is_private' => true]),
                $post(9, 4, 4, $now),
                // Member 5: yesterday and the day before, nothing yet today.
                $post(10, 4, 5, $now->copy()->subDay()),
                $post(11, 4, 5, $now->copy()->subDays(2)),
            ],
        ]);
    }

    private function get(string $path, ?int $actor = null): array
    {
        $response = $this->send($this->request('GET', $path, $actor ? ['authenticatedAs' => $actor] : []));

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    /** The day's lists are cached; a run of this suite must not read another test's. */
    private function freshCache(): void
    {
        $this->app()->getContainer()->make(Repository::class)->flush();
    }

    #[Test]
    public function the_forum_says_what_the_actor_may_do()
    {
        $attributes = fn (?int $actor) => $this->get('/api', $actor)[1]['data']['attributes'];

        $guest = $attributes(null);
        $this->assertFalse($guest['canCreateEvent']);
        $this->assertFalse($guest['canManageCalendar']);
        $this->assertFalse($guest['calendarCoverUploads'], 'No uploads without fof/upload');

        $this->assertFalse($attributes(2)['canCreateEvent']);
        $this->assertTrue($attributes(5)['canCreateEvent']);
        $this->assertFalse($attributes(5)['canManageCalendar']);
        $this->assertTrue($attributes(1)['canManageCalendar']);
    }

    #[Test]
    public function a_member_sets_their_own_birthday_and_only_theirs()
    {
        $patch = fn (int $user, int $actor, string $birthday) => $this->send($this->request('PATCH', "/api/users/$user", [
            'authenticatedAs' => $actor,
            'json' => ['data' => ['type' => 'users', 'id' => (string) $user, 'attributes' => ['calendarBirthday' => $birthday]]],
        ]));

        $response = $patch(2, 2, '07-04');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('07-04', json_decode((string) $response->getBody(), true)['data']['attributes']['calendarBirthday']);

        $patch(2, 2, '13-40');
        $this->assertNull($this->database()->table('users')->where('id', 2)->value('cal_birthday'), 'An impossible date clears it');

        $this->assertSame(403, $patch(4, 2, '01-01')->getStatusCode());
        $this->assertNull($this->database()->table('users')->where('id', 4)->value('cal_birthday'));

        $this->assertSame(200, $patch(4, 1, '01-01')->getStatusCode(), 'An admin may edit any member');
        $this->assertSame('01-01', $this->database()->table('users')->where('id', 4)->value('cal_birthday'));
    }

    #[Test]
    public function a_members_activity_counts_only_their_visible_comments()
    {
        [$status, $body] = $this->get('/api/calendar/activity/2');

        $this->assertSame(200, $status);
        $today = Carbon::now()->toDateString();
        $yesterday = Carbon::now()->subDay()->toDateString();
        $this->assertEquals([$yesterday => 1, $today => 2], array_intersect_key($body['data']['days'], [$today => 0, $yesterday => 0]), 'Keyed by day, in any order');
        $this->assertSame(3, $body['data']['total'], 'Hidden and private comments, and those over a year old, are left out');
        $this->assertSame(['current' => 2, 'longest' => 2], $body['data']['streak']);

        [, $body] = $this->get('/api/calendar/activity/5');
        $this->assertSame(['current' => 2, 'longest' => 2], $body['data']['streak'], 'A streak stays alive until a whole day passes');
    }

    #[Test]
    public function the_pulse_ranks_the_most_active_members()
    {
        $this->freshCache();

        [$status, $body] = $this->get('/api/calendar/pulse');

        $this->assertSame(200, $status);
        $this->assertSame(6, $body['data']['total']);
        $this->assertSame(
            [['normal', 3], ['organiser', 2], ['veteran', 1]],
            array_map(fn ($l) => [$l['username'], $l['count']], $body['data']['leaders'])
        );
    }

    #[Test]
    public function on_this_day_shows_only_discussions_the_actor_can_see()
    {
        $this->freshCache();

        [$status, $body] = $this->get('/api/calendar/onthisday');

        $this->assertSame(200, $status);
        $this->assertSame(['Years ago today'], array_column($body['data'], 'title'), 'Not hidden, private, or from this year');
        $this->assertSame(4, $body['data'][0]['yearsAgo']);

        // An admin can see hidden and private discussions, but they are still no memory to share.
        [, $body] = $this->get('/api/calendar/onthisday', 1);
        $this->assertSame(['Years ago today', 'No comments'], array_column($body['data'], 'title'));
    }

    #[Test]
    public function celebrations_list_todays_birthdays_and_join_anniversaries()
    {
        $this->freshCache();

        [$status, $body] = $this->get('/api/calendar/celebrations');

        $this->assertSame(200, $status);
        $this->assertSame(
            [['birthday', 'birthday', null], ['veteran', 'anniversary', 4]],
            array_map(fn ($c) => [$c['username'], $c['type'], $c['years'] ?? null], $body['data'])
        );
    }
}
