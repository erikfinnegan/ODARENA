<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\Decree
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property array $prerequisites
 * @property int $level
 * @property int $enabled
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\OpenDominion\Models\DecreePerkType[] $perks
 */
class Decree extends AbstractModel
{
    protected $table = 'decrees';

    protected $casts = [
        'enabled' => 'integer',
        'cooldown' => 'integer',
        'states' => 'array',
        'default' => 'string',
        'excluded_races' => 'array',
        'exclusive_races' => 'array',
    ];

    public function perks()
    {
        return $this->belongsToMany(
            DecreePerkType::class,
            'decree_perks',
            'decree_id',
            'decree_perk_type_id'
        )
            ->withTimestamps()
            ->withPivot('value');
    }

    public function getPerkValue(string $key)
    {
        $perks = $this->perks->filter(static function (DecreePerkType $decreePerkType) use ($key) {
            return ($decreePerkType->key === $key);
        });

        if ($perks->isEmpty()) {
            return 0; // todo: change to null instead, also add return type and docblock(s)
        }

        return $perks->first()->pivot->value;
    }
}
