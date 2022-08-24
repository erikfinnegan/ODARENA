<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\WatchedDominion
 *
 * @property int $dominion_id
 * @property int $stat_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \OpenDominion\Models\Dominion $dominion
 * @property-read \OpenDominion\Models\Stat $stat
 */
class WatchedDominion extends AbstractModel
{
    protected $table = 'watched_dominions';

    public function watcher()
    {
        return $this->belongsTo(Dominion::class, 'watcher_id');
    }

    public function dominions()
    {
        return $this->belongsToMany(
            Dominion::class,
            'id',
            'id',
            'dominion_id'
        );
    }
}
