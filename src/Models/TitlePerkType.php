<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\RacePerkType
 *
 * @property int $id
 * @property string $key
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\OpenDominion\Models\Race[] $races
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\RacePerkType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\RacePerkType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\RacePerkType query()
 * @mixin \Eloquent
 */
class TitlePerkType extends AbstractModel
{
    public function races()
    {
        return $this->belongsToMany(
            Title::class,
            'title_perks',
            'title_perk_type_id',
            'title_id'
        )
            ->withTimestamps();
    }
}
