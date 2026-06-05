<?php

/*
 * Page Builder "Celebrations" block (server manifest half; client self-fetches).
 * See EventsBlock for the architecture note.
 */

namespace ErnestDefoe\Calendar\PageBuilder;

use Ernestdefoe\PageBuilder\Block\AbstractBlock;

class CelebrationsBlock extends AbstractBlock
{
    public function type(): string { return 'celebrations'; }
    public function name(): string { return 'Celebrations'; }
    public function icon(): string { return 'fas fa-birthday-cake'; }
    public function category(): string { return 'forum'; }

    public function settingsSchema(): array
    {
        return [
            ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'default' => 'Celebrations'],
        ];
    }
}
