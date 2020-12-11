<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\BuildingPerk
 *
 * @property int $id
 * @property int $building_id
 * @property int $building_perk_type_id
 * @property string $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \OpenDominion\Models\Building $building
 * @property-read \OpenDominion\Models\BuildingPerkType $type
 */
class SpellPerk extends AbstractModel
{
    public function spell()
    {
        return $this->belongsTo(Spell::class);
    }

    public function type()
    {
        return $this->belongsTo(SpellPerkType::class, 'spell_perk_type_id');
    }
}
