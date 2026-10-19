<?php

namespace ErnestDefoe\Calendar\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\Calendar\Event;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LocationTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-calendar');

        $this->prepareDatabase([
            'calendar_events' => [
                [
                    'id' => 1, 'title' => 'Raid night', 'slug' => 'raid-night', 'user_id' => 1,
                    'start_at' => '2030-05-10 18:00:00', 'all_day' => false, 'timezone' => 'UTC', 'is_published' => true,
                    'location' => 'The Old Mill', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
                ],
            ],
        ]);
    }

    private function json(string $method, string $path, ?array $attrs = null): array
    {
        $options = ['authenticatedAs' => 1];
        if ($attrs !== null) {
            $options['json'] = ['data' => ['attributes' => $attrs]];
        }

        $response = $this->send($this->request($method, $path, $options));

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    #[Test]
    public function location_is_optional()
    {
        [$status, $body] = $this->json('POST', '/api/calendar/events', ['title' => 'No place', 'start' => '2030-06-01T18:00:00Z']);

        $this->assertSame(201, $status);
        $this->assertNull($body['data']['location']);
    }

    #[Test]
    public function location_is_shown_and_saved_by_default()
    {
        [, $body] = $this->json('GET', '/api/calendar/events/1');
        $this->assertSame('The Old Mill', $body['data']['location']);

        [$status, $body] = $this->json('PATCH', '/api/calendar/events/1', ['location' => 'https://discord.gg/abc']);
        $this->assertSame(200, $status);
        $this->assertSame('https://discord.gg/abc', $body['data']['location']);
    }

    #[Test]
    public function switched_off_it_is_hidden_and_ignored_but_kept()
    {
        $this->setting('ernestdefoe-calendar.location_mode', 'off');

        [, $body] = $this->json('GET', '/api/calendar/events/1');
        $this->assertNull($body['data']['location']);
        $this->assertStringNotContainsString('location=', $body['data']['googleUrl']);

        [$status, $body] = $this->json('PATCH', '/api/calendar/events/1', ['location' => 'Somewhere else']);
        $this->assertSame(200, $status);
        $this->assertNull($body['data']['location']);

        $ics = (string) $this->send($this->request('GET', '/calendar/events/1/ical'))->getBody();
        $this->assertStringContainsString('SUMMARY:Raid night', $ics);
        $this->assertStringNotContainsString('LOCATION', $ics);

        // The stored value is untouched, so switching it back on restores it.
        $this->assertSame('The Old Mill', Event::query()->find(1)->getRawOriginal('location'));
    }
}
