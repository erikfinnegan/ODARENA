<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\AdvancementPerkType
 *
 * @property int $id
 * @property string $key
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\OpenDominion\Models\Advancement[] $advancements
 */
class AdvancementPerkType extends AbstractModel
{
    public function advancements()
    {
        return $this->belongsToMany(
            Advancement::class,
            'advancement_perks',
            'advancement_perk_type_id',
            'advancement_id'
        )
            ->withTimestamps();
    }
}
