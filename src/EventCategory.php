<?php

namespace ErnestDefoe\Calendar;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An admin-defined event category with a display colour.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $color
 * @property int $position
 */
class EventCategory extends AbstractModel
{
    protected $table = 'calendar_categories';

    protected $casts = [
        'position'   => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'color' => '#3b5bdb',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'category_id');
    }
}
