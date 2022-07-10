<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\DeityPerk
 *
 * @property int $id
 * @property int $deity_id
 * @property int $deity_perk_type_id
 * @property string $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \OpenDominion\Models\Deity $deity
 * @property-read \OpenDominion\Models\DeityPerkType $type
 */
class DeityPerk extends AbstractModel
{
    public function deity()
    {
        return $this->belongsTo(Deity::class);
    }

    public function type()
    {
        return $this->belongsTo(DeityPerkType::class, 'deity_perk_type_id');
    }
}
