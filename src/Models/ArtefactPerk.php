<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\ArtefactPerk
 *
 * @property int $id
 * @property int $artefact_id
 * @property int $artefact_perk_type_id
 * @property string $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \OpenDominion\Models\Artefact $artefact
 * @property-read \OpenDominion\Models\ArtefactPerkType $type
 */
class ArtefactPerk extends AbstractModel
{
    public function artefact()
    {
        return $this->belongsTo(Artefact::class);
    }

    public function type()
    {
        return $this->belongsTo(ArtefactPerkType::class, 'artefact_perk_type_id');
    }
}
