<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\DominionStat
 *
 * @property int $dominion_id
 * @property int $stat_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \OpenDominion\Models\Dominion $dominion
 * @property-read \OpenDominion\Models\Stat $stat
 */
class DominionStat extends AbstractModel
{
    protected $table = 'dominion_stats';

    public function dominion()
    {
        return $this->belongsTo(Dominion::class, 'dominion_id');
    }

    public function stat()
    {
        return $this->belongsTo(Stat::class, 'stat_id');
    }
}
