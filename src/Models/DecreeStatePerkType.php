<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\UnitPerkType
 *
 * @property int $id
 * @property string $key
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\OpenDominion\Models\Unit[] $units
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\UnitPerkType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\UnitPerkType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\UnitPerkType query()
 * @mixin \Eloquent
 */
class DecreeStatePerkType extends AbstractModel
{
    public function units()
    {
        return $this->belongsToMany(
            DecreeState::class,
            'decree_state_perks',
            'decree_state_perk_type_id',
            'decree_state_id'
        )
            ->withTimestamps();
    }
}
