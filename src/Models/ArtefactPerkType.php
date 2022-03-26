<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\ArtefactPerkType
 *
 * @property int $id
 * @property string $key
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\OpenDominion\Models\Artefact[] $artefacts
 */
class ArtefactPerkType extends AbstractModel
{
    public function artefact()
    {
        return $this->belongsToMany(
            Artefact::class,
            'artefact_perks',
            'artefact_perk_type_id',
            'artefact_id'
        )
            ->withTimestamps();
    }
}
