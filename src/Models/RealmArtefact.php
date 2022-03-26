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
class RealmArtefact extends AbstractModel
{
    protected $table = 'realm_artefacts';

    public function realm()
    {
        return $this->belongsTo(Realm::class, 'realm_id');
    }

    public function artefact()
    {
        return $this->belongsTo(Artefact::class, 'artefact_id');
    }
}
