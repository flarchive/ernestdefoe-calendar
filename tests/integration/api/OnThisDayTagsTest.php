<?php

namespace ErnestDefoe\Calendar\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository;
use PHPUnit\Framework\Attributes\Test;

class OnThisDayTagsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'ernestdefoe-calendar');

        // Four years back always lands on the same month and day, 29 February included.
        $then = Carbon::now()->subYears(4);

        $this->prepareDatabase([
            User::class => [$this->normalUser()],
            Tag::class => [
                ['id' => 1, 'name' => 'Announcements', 'slug' => 'announcements', 'position' => 0],
                ['id' => 2, 'name' => 'General', 'slug' => 'general', 'position' => 1],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Read-only memory', 'created_at' => $then, 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 9],
                ['id' => 2, 'title' => 'General memory', 'created_at' => $then, 'user_id' => 2, 'first_post_id' => 2, 'comment_count' => 5],
                ['id' => 3, 'title' => 'Untagged memory', 'created_at' => $then, 'user_id' => 2, 'first_post_id' => 3, 'comment_count' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Post</p></t>', 'created_at' => $then],
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Post</p></t>', 'created_at' => $then],
                ['id' => 3, 'discussion_id' => 3, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Post</p></t>', 'created_at' => $then],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 2, 'tag_id' => 2],
            ],
        ]);
    }

    private function titles(): array
    {
        $this->app()->getContainer()->make(Repository::class)->flush();

        $response = $this->send($this->request('GET', '/api/calendar/onthisday', ['authenticatedAs' => 2]));

        $this->assertSame(200, $response->getStatusCode());

        return array_column(json_decode((string) $response->getBody(), true)['data'], 'title');
    }

    #[Test]
    public function every_tag_is_shown_by_default()
    {
        $this->assertSame(['Read-only memory', 'General memory', 'Untagged memory'], $this->titles());
    }

    #[Test]
    public function an_excluded_tag_is_left_out()
    {
        $this->setting('ernestdefoe-calendar.onthisday_exclude_tags', json_encode([1]));

        $this->assertSame(['General memory', 'Untagged memory'], $this->titles(), 'Untagged discussions are not excluded with it');
    }
}
