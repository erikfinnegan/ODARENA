<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\RacePerk
 *
 * @property int $id
 * @property int $race_id
 * @property int $race_perk_type_id
 * @property float $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \OpenDominion\Models\Race $race
 * @property-read \OpenDominion\Models\RacePerkType $type
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\RacePerk newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\RacePerk newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\RacePerk query()
 * @mixin \Eloquent
 */
class TitlePerk extends AbstractModel
{
    protected $casts = [
        'value' => 'float',
    ];

    public function title()
    {
        return $this->belongsTo(Title::class);
    }

    public function type()
    {
        return $this->belongsTo(TitlePerkType::class, 'title_perk_type_id');
    }
}
