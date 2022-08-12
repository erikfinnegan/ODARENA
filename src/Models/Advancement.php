<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\Tech
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property array $prerequisites
 * @property int $level
 * @property int $enabled
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\OpenDominion\Models\AdvancementPerkType[] $perks
 */
class Advancement extends AbstractModel
{
    protected $table = 'advancements';

    protected $casts = [
        'excluded_races' => 'array',
        'exclusive_races' => 'array',
        'enabled' => 'integer',
    ];

    public function perks()
    {
        return $this->belongsToMany(
            AdvancementPerkType::class,
            'advancement_perks',
            'advancement_id',
            'advancement_perk_type_id'
        )
            ->withTimestamps()
            ->withPivot('value');
    }

    public function getPerkValue(string $key)
    {
        $perks = $this->perks->filter(static function (AdvancementPerkType $advancementPerkType) use ($key) {
            return ($advancementPerkType->key === $key);
        });

        if ($perks->isEmpty()) {
            return 0; // todo: change to null instead, also add return type and docblock(s)
        }

        return $perks->first()->pivot->value;
    }
}
