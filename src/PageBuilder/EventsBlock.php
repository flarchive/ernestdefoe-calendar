<?php

/*
 * Page Builder "Upcoming events" block.
 *
 * This is the SERVER half of the block — it only contributes the manifest entry
 * (type/name/icon/category/schema/defaults) so the block appears in Page
 * Builder's editor palette. The CLIENT half (registered in
 * js/src/forum/integrations.ts via app.pageBuilder.registerBlock('events', …))
 * self-fetches its data from the calendar API, so resolve() stays empty —
 * inherited from AbstractBlock.
 *
 * Only loaded when ernestdefoe/page-builder is installed (the extender that
 * registers it is guarded by class_exists in extend.php), so the reference to
 * Page Builder's AbstractBlock is safe.
 */

namespace ErnestDefoe\Calendar\PageBuilder;

use Ernestdefoe\PageBuilder\Block\AbstractBlock;

class EventsBlock extends AbstractBlock
{
    public function type(): string { return 'events'; }
    public function name(): string { return 'Upcoming Events'; }
    public function icon(): string { return 'fas fa-calendar-day'; }
    public function category(): string { return 'forum'; }

    public function settingsSchema(): array
    {
        return [
            ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'default' => 'Upcoming events'],
            ['key' => 'count', 'type' => 'range', 'label' => 'How many', 'default' => 5, 'min' => 1, 'max' => 20],
            ['key' => 'category', 'type' => 'text', 'label' => 'Category slug (optional)', 'default' => ''],
        ];
    }
}
