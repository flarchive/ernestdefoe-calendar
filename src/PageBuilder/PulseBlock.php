<?php

/*
 * Page Builder "Forum pulse" block (server manifest half; client self-fetches).
 * See EventsBlock for the architecture note.
 */

namespace ErnestDefoe\Calendar\PageBuilder;

use Ernestdefoe\PageBuilder\Block\AbstractBlock;

class PulseBlock extends AbstractBlock
{
    public function type(): string { return 'pulse'; }
    public function name(): string { return 'Forum Pulse'; }
    public function icon(): string { return 'fas fa-chart-line'; }
    public function category(): string { return 'forum'; }

    public function settingsSchema(): array
    {
        return [
            ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'default' => 'Forum pulse'],
            ['key' => 'count', 'type' => 'range', 'label' => 'Leaderboard size', 'default' => 5, 'min' => 1, 'max' => 20],
            ['key' => 'weeks', 'type' => 'range', 'label' => 'Weeks of activity', 'default' => 14, 'min' => 4, 'max' => 53],
        ];
    }
}
