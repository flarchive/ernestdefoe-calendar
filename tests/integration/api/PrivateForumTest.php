<?php

namespace ErnestDefoe\Calendar\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * On a forum guests can't view, the calendar's events, members and activity
 * stay private as well.
 */
class PrivateForumTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-calendar');

        $this->prepareDatabase([
            User::class => [$this->normalUser()],
            'calendar_events' => [
                ['id' => 1, 'title' => 'Members only', 'slug' => 'members-only', 'start_at' => '2030-05-10 18:00:00', 'is_published' => true, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ],
        ]);
    }

    public static function endpoints(): array
    {
        return [
            'events' => ['/api/calendar/events'],
            'event' => ['/api/calendar/events/1'],
            'categories' => ['/api/calendar/categories'],
            'activity' => ['/api/calendar/activity/2'],
            'pulse' => ['/api/calendar/pulse'],
            'on this day' => ['/api/calendar/onthisday'],
            'celebrations' => ['/api/calendar/celebrations'],
            'iCal feed' => ['/calendar/feed.ics'],
            'iCal event' => ['/calendar/events/1/ical'],
        ];
    }

    private function privateForum(): void
    {
        $this->app();
        // What making a forum private means: members can view it, guests can't.
        $this->database()->table('group_permission')->where('group_id', 2)->where('permission', 'viewForum')->delete();
        $this->database()->table('group_permission')->insert(['group_id' => 3, 'permission' => 'viewForum']);
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function a_guest_cannot_read_a_private_forums_calendar(string $path)
    {
        $this->privateForum();

        $response = $this->send($this->request('GET', $path));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringNotContainsString('Members only', (string) $response->getBody());
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function a_member_still_can(string $path)
    {
        $this->privateForum();

        $this->assertSame(200, $this->send($this->request('GET', $path, ['authenticatedAs' => 2]))->getStatusCode());
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function a_guest_can_read_a_public_forums_calendar(string $path)
    {
        $this->assertSame(200, $this->send($this->request('GET', $path))->getStatusCode());
    }
}
