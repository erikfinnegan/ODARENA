<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\BuildingPerkType
 *
 * @property int $id
 * @property string $key
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\OpenDominion\Models\Building[] $buildingss
 */
class SpellPerkType extends AbstractModel
{
    public function buildings()
    {
        return $this->belongsToMany(
            Building::class,
            'spell_perks',
            'spell_perk_type_id',
            'spell_id'
        )
            ->withTimestamps();
    }
}
