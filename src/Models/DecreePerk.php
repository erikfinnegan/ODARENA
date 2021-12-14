<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\DecreePerk
 *
 * @property int $id
 * @property int $decree_id
 * @property int $decree_perk_type_id
 * @property string $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \OpenDominion\Models\Decree $decree
 * @property-read \OpenDominion\Models\DecreePerkType $type
 */
class DecreePerk extends AbstractModel
{
    public function decree()
    {
        return $this->belongsTo(Decree::class);
    }

    public function type()
    {
        return $this->belongsTo(DecreePerkType::class, 'decree_perk_type_id');
    }
}
