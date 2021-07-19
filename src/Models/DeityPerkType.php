<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\DeityPerkType
 *
 * @property int $id
 * @property string $key
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\OpenDominion\Models\Deity[] $deitys
 */
class DeityPerkType extends AbstractModel
{
    public function deity()
    {
        return $this->belongsToMany(
            Deity::class,
            'deity_perks',
            'deity_perk_type_id',
            'deity_id'
        )
            ->withTimestamps();
    }
}
