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
class DominionHistory extends AbstractModel
{
    protected $table = 'dominion_history';

    public function dominion()
    {
        return $this->belongsTo(Dominion::class, 'dominion_id');
    }
}
