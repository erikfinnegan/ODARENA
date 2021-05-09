<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\Stat
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property int $value
 * @property int $enabled
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Stat extends AbstractModel
{
    protected $table = 'stats';

    protected $casts = [
        'value' => 'int',
    ];

}
