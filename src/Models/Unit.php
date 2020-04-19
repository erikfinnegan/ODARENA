<?php

namespace OpenDominion\Models;

/**
 * OpenDominion\Models\Unit
 *
 * @property int $id
 * @property int $race_id
 * @property int $slot
 * @property string $name
 * @property int $cost_platinum
 * @property int $cost_ore
 * @property float $power_offense
 * @property float $power_defense
 * @property bool $need_boat
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\OpenDominion\Models\UnitPerkType[] $perks
 * @property-read \OpenDominion\Models\Race $race
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\Unit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\Unit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\Unit query()
 * @mixin \Eloquent
 */
class Unit extends AbstractModel
{
    protected $casts = [
        'slot' => 'integer',
        'cost_platinum' => 'integer',
        'cost_ore' => 'integer',
        'power_offense' => 'float',
        'power_defense' => 'float',
        'need_boat' => 'boolean',
        // Unit food cost
        'cost_food' => 'integer',
        // Unit mana cost
        'cost_mana' => 'integer',
        // Unit gem cost
        'cost_gem' => 'integer',
        // Unit lumber cost
        'cost_lumber' => 'integer',
        // Unit prestige cost
        'cost_prestige' => 'integer',
        // Unit boat cost
        'cost_boat' => 'integer',
        // Unit champion cost
        'cost_champion' => 'integer',
        // Unit soul cost
        'cost_soul' => 'integer',
        // Unit soul cost
        'cost_blood' => 'integer',
        // Unit unit 1 cost
        'cost_unit1' => 'integer',
        // Unit unit 2 cost
        'cost_unit2' => 'integer',
        // Unit unit 3 cost
        'cost_unit3' => 'integer',
        // Unit unit 4 cost
        'cost_unit4' => 'integer',
        // Unit morale cost
        'cost_morale' => 'integer',
        // Unit wild yeti cost
        'cost_wild_yeti' => 'integer',
        // Unit spy cost
        'cost_spy' => 'integer',
        // Unit wizard cost
        'cost_wizard' => 'integer',
        // Unit archmage cost
        'cost_archmage' => 'integer',
        // For cases when a unit's NW needs to be manually set
        'static_networth' => 'integer',
    ];

    public function perks()
    {
        return $this->belongsToMany(
            UnitPerkType::class,
            'unit_perks',
            'unit_id',
            'unit_perk_type_id'
        )
            ->withTimestamps()
            ->withPivot('value');
    }

    public function race()
    {
        return $this->hasOne(Race::class);
    }

    public function getPerkValue(string $key)
    {
        $perks = $this->perks->filter(static function (UnitPerkType $unitPerkType) use ($key) {
            return ($unitPerkType->key === $key);
        });

        if ($perks->isEmpty()) {
            return 0; // todo: change to null instead, also add return type and docblock(s)
        }

        return $perks->first()->pivot->value;
    }
}
