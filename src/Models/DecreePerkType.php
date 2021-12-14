<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\DecreePerkType
 *
 * @property int $id
 * @property string $key
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\OpenDominion\Models\Decree[] $decrees
 */
class DecreePerkType extends AbstractModel
{
    public function decrees()
    {
        return $this->belongsToMany(
            Decree::class,
            'decree_perks',
            'decree_perk_type_id',
            'decree_id'
        )
            ->withTimestamps();
    }
}
