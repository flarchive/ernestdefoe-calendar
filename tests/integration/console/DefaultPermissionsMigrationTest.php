<?php

namespace ErnestDefoe\Calendar\Tests\integration\console;

use Flarum\Group\Group;
use Flarum\Testing\integration\ConsoleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * A fresh install lets members create events; a forum that already runs
 * Calendar, or already granted the permission, keeps what it has; rolling back
 * removes only what the install granted.
 *
 * Each test ends migrated, as it started: MySQL commits schema changes
 * whatever transaction the test runs in.
 */
class DefaultPermissionsMigrationTest extends ConsoleTestCase
{
    private const MIGRATION = '2026_01_01_000000_members_can_create_events_on_a_fresh_install';

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-calendar');
    }

    private function grants(): array
    {
        return $this->database()->table('group_permission')->where('permission', 'like', 'calendar.%')
            ->orderBy('permission')->orderBy('group_id')->get()
            ->map(fn ($r) => [(int) $r->group_id, $r->permission])->all();
    }

    private function migrate(bool $reset = true): void
    {
        if ($reset) {
            $this->runCommand(['command' => 'migrate:reset', '--extension' => 'ernestdefoe-calendar']);
        }

        $this->runCommand(['command' => 'migrate']);
    }

    #[Test]
    public function a_fresh_install_lets_members_create_events()
    {
        $this->assertSame([[Group::MEMBER_ID, 'calendar.create']], $this->grants(), 'Members only: no guests, nothing for manage');
    }

    #[Test]
    public function rolling_back_removes_only_what_the_install_granted()
    {
        $this->database()->table('group_permission')->insert(['group_id' => Group::MODERATOR_ID, 'permission' => 'calendar.manage']);

        $this->runCommand(['command' => 'migrate:reset', '--extension' => 'ernestdefoe-calendar']);

        $this->assertSame([[Group::MODERATOR_ID, 'calendar.manage']], $this->grants());
        $this->assertNull($this->database()->table('settings')->where('key', 'ernestdefoe-calendar.seeded_permissions')->value('value'));

        $this->database()->table('group_permission')->where('permission', 'like', 'calendar.%')->delete();
        $this->migrate(false);
    }

    #[Test]
    public function a_permission_another_group_holds_is_left_alone()
    {
        $this->runCommand(['command' => 'migrate:reset', '--extension' => 'ernestdefoe-calendar']);
        $this->database()->table('group_permission')->where('permission', 'like', 'calendar.%')->delete();
        $this->database()->table('group_permission')->insert(['group_id' => Group::MODERATOR_ID, 'permission' => 'calendar.create']);

        $this->migrate(false);

        $this->assertSame([[Group::MODERATOR_ID, 'calendar.create']], $this->grants());

        // Rolling back keeps the moderators' grant, which the install did not make.
        $this->runCommand(['command' => 'migrate:reset', '--extension' => 'ernestdefoe-calendar']);
        $this->assertSame([[Group::MODERATOR_ID, 'calendar.create']], $this->grants());

        $this->database()->table('group_permission')->where('permission', 'like', 'calendar.%')->delete();
        $this->migrate(false);
    }

    #[Test]
    public function a_forum_already_running_calendar_keeps_its_permissions()
    {
        // An update, not an install: the other migrations ran long ago, and
        // the admin took the grant away.
        $db = $this->database();
        $db->table('group_permission')->where('permission', 'like', 'calendar.%')->delete();
        $db->table('settings')->where('key', 'ernestdefoe-calendar.seeded_permissions')->delete();
        $db->table('migrations')->where('migration', self::MIGRATION)->delete();

        $this->migrate(false);

        $this->assertSame([], $this->grants());
        $this->assertTrue($db->table('migrations')->where('migration', self::MIGRATION)->exists(), 'Recorded as run');

        $this->migrate();
    }
}
