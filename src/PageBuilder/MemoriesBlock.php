<?php

/*
 * Page Builder "On this day" block (server manifest half; client self-fetches).
 * See EventsBlock for the architecture note.
 */

namespace ErnestDefoe\Calendar\PageBuilder;

use Ernestdefoe\PageBuilder\Block\AbstractBlock;

class MemoriesBlock extends AbstractBlock
{
    public function type(): string { return 'memories'; }
    public function name(): string { return 'On This Day'; }
    public function icon(): string { return 'fas fa-history'; }
    public function category(): string { return 'forum'; }

    public function settingsSchema(): array
    {
        return [
            ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'default' => 'On this day'],
            ['key' => 'count', 'type' => 'range', 'label' => 'How many', 'default' => 6, 'min' => 1, 'max' => 20],
        ];
    }
}
