<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\DominionTech
 *
 * @property int $dominion_id
 * @property int $tech_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \OpenDominion\Models\Dominion $dominion
 * @property-read \OpenDominion\Models\Deity $tech
 */
class DominionDeity extends AbstractModel
{
    protected $table = 'dominion_deity';

    public function dominion()
    {
        return $this->belongsTo(Dominion::class, 'dominion_id');
    }

    public function deity()
    {
        return $this->belongsTo(Deity::class, 'deity_id');
    }
}
