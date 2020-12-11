<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\Spell
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property array $prerequisites
 * @property int $costMultiplier
 * @property int $enabled
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\OpenDominion\Models\SpellPerkType[] $perks
 */
class Spell extends AbstractModel
{
    protected $table = 'spells';

    protected $casts = [
        'excluded_races' => 'array',
        'exclusive_races' => 'array',
        'scope' => 'string',
        'class' => 'string',
        'cost' => 'float',
        'duration' => 'integer',
        'cooldown' => 'integer',
    ];

    public function perks()
    {
        return $this->belongsToMany(
            SpellPerkType::class,
            'spell_perks',
            'spell_id',
            'spell_perk_type_id'
        )
            ->withTimestamps()
            ->withPivot('value');
    }

    public function getPerkValue(string $key)
    {
        $perks = $this->perks->filter(static function (SpellPerkType $spellPerkType) use ($key) {
            return ($spellPerkType->key === $key);
        });

        if ($perks->isEmpty()) {
            return 0; // todo: change to null instead, also add return type and docblock(s)
        }

        return $perks->first()->pivot->value;
    }
}
