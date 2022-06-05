<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\UnitPerk
 *
 * @property int $id
 * @property int $unit_id
 * @property int $unit_perk_type_id
 * @property float|null $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \OpenDominion\Models\UnitPerkType $type
 * @property-read \OpenDominion\Models\Unit $unit
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\UnitPerk newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\UnitPerk newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\UnitPerk query()
 * @mixin \Eloquent
 */
class DecreeStatePerk extends AbstractModel
{
    protected $casts = [
        'value' => 'float',
    ];

    public function decree()
    {
        return $this->belongsTo(Decree::class);
    }

    public function type()
    {
        return $this->belongsTo(DecreeStatePerkType::class, 'decree_state_perk_type_id');
    }
}
