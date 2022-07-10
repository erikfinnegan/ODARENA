<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\DominionDecree
 *
 * @property int $dominion_id
 * @property int $decree_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \OpenDominion\Models\Dominion $dominion
 * @property-read \OpenDominion\Models\Decree $decree
 */
class DominionDecreeState extends AbstractModel
{
    protected $table = 'dominion_decree_states';

    public function dominion()
    {
        return $this->belongsTo(Dominion::class, 'dominion_id');
    }

    public function decree()
    {
        return $this->belongsTo(Decree::class, 'decree_id');
    }

    public function decreeState()
    {
        return $this->belongsTo(DecreeState::class, 'decree_state_id');
    }
}
