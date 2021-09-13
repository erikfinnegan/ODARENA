<?php

namespace OpenDominion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notifiable;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Helpers\LandHelper;
use OpenDominion\Services\Dominion\HistoryService;
use OpenDominion\Services\Dominion\SelectorService;
use OpenDominion\Calculators\Dominion\ResourceCalculator;
use OpenDominion\Services\Dominion\QueueService;
use Illuminate\Support\Carbon;

/**
 * OpenDominion\Models\Dominion
 *
 * @property int $id
 * @property int $user_id
 * @property int $round_id
 * @property int $realm_id
 * @property int $race_id
 * @property string $name
 * @property string|null $ruler_name
 * @property int $prestige
 * @property int $peasants
 * @property int $peasants_last_hour
 * @property int $draft_rate
 * @property int $morale
 * @property float $spy_strength
 * @property float $wizard_strength
 * @property bool $daily_gold
 * @property bool $daily_land
 * @property int $resource_gold
 * @property int $resource_food
 * @property int $resource_lumber
 * @property int $resource_mana
 * @property int $resource_ore
 * @property int $resource_gems
 * @property float $resource_boats
 * @property int $resource_champion
 * @property int $resource_soul
 * @property int $resource_blood
 * @property int $improvement_science
 * @property int $improvement_keep
 * @property int $improvement_towers
 * @property int $improvement_forges
 * @property int $improvement_walls
 * @property int $improvement_harbor
 * @property int $improvement_armory
 * @property int $improvement_infirmary
 * @property int $improvement_tissue
 * @property int $military_draftees
 * @property int $military_unit1
 * @property int $military_unit2
 * @property int $military_unit3
 * @property int $military_unit4
 * @property int $military_spies
 * @property int $military_wizards
 * @property int $military_archmages
 * @property int $land_plain
 * @property int $land_mountain
 * @property int $land_swamp
 * @property int $land_cavern
 * @property int $land_forest
 * @property int $land_hill
 * @property int $land_water
 * @property int $discounted_land
 * @property int $building_home
 * @property int $building_alchemy
 * @property int $building_farm
 * @property int $building_smithy
 * @property int $building_masonry
 * @property int $building_ore_mine
 * @property int $building_gryphon_nest
 * @property int $building_tower
 * @property int $building_wizard_guild
 * @property int $building_temple
 * @property int $building_gem_mine
 * @property int $building_school
 * @property int $building_lumberyard
 * @property int $building_forest_haven
 * @property int $building_factory
 * @property int $building_guard_tower
 * @property int $building_shrine
 * @property int $building_barracks
 * @property int $building_dock
 * @property \Illuminate\Support\Carbon|null $council_last_read
 * @property \Illuminate\Support\Carbon|null $royal_guard
 * @property \Illuminate\Support\Carbon|null $elite_guard
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $pack_id
 * @property int|null $monarch_dominion_id
 * @property-read \Illuminate\Database\Eloquent\Collection|\OpenDominion\Models\Council\Thread[] $councilThreads
 * @property-read \Illuminate\Database\Eloquent\Collection|\OpenDominion\Models\GameEvent[] $gameEventsSource
 * @property-read \Illuminate\Database\Eloquent\Collection|\OpenDominion\Models\GameEvent[] $gameEventsTarget
 * @property-read \Illuminate\Database\Eloquent\Collection|\OpenDominion\Models\Dominion\History[] $history
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $notifications
 * @property-read \OpenDominion\Models\Pack|null $pack
 * @property-read \OpenDominion\Models\Race $race
 * @property-read \OpenDominion\Models\Realm $realm
 * @property-read \OpenDominion\Models\Round $round
 * @property-read \OpenDominion\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\Dominion active()
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\Dominion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\Dominion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\OpenDominion\Models\Dominion query()
 * @mixin \Eloquent
 */
class Dominion extends AbstractModel
{
    use Notifiable;

    protected $casts = [
        'prestige' => 'float',
        'xp' => 'integer',
        'peasants' => 'integer',
        'peasants_last_hour' => 'integer',
        'draft_rate' => 'integer',
        'morale' => 'integer',
        'spy_strength' => 'float',
        'wizard_strength' => 'float',

        'military_draftees' => 'integer',
        'military_unit1' => 'integer',
        'military_unit2' => 'integer',
        'military_unit3' => 'integer',
        'military_unit4' => 'integer',
        'military_spies' => 'integer',
        'military_wizards' => 'integer',
        'military_archmages' => 'integer',

        'land_plain' => 'integer',
        'land_mountain' => 'integer',
        'land_swamp' => 'integer',
        'land_cavern' => 'integer',
        'land_forest' => 'integer',
        'land_hill' => 'integer',
        'land_water' => 'integer',

        'daily_gold' => 'boolean',
        'daily_land' => 'boolean',

        'royal_guard_active_at' => 'datetime',
        'eltie_guard_active_at' => 'datetime',

        'is_locked' => 'integer',

        'most_recent_improvement_resource' => 'string',
        'most_recent_exchange_from' => 'string',
        'most_recent_exchange_to' => 'string',

        'npc_modifier' => 'integer',

        'protection_ticks' => 'integer',

        'notes' => 'text',
    ];

    // Relations

    public function councilThreads()
    {
        return $this->hasMany(Council\Thread::class);
    }

    public function gameEventsSource()
    {
        return $this->morphMany(GameEvent::class, 'source');
    }

    public function gameEventsTarget()
    {
        return $this->morphMany(GameEvent::class, 'target');
    }

    public function history()
    {
        return $this->hasMany(Dominion\History::class);
    }

    public function pack()
    {
        return $this->belongsTo(Pack::class);
    }

    public function race()
    {
        return $this->belongsTo(Race::class);
    }

    public function title()
    {
        return $this->belongsTo(Title::class);
    }

    public function realm()
    {
        return $this->belongsTo(Realm::class);
    }

    public function round()
    {
        return $this->belongsTo(Round::class);
    }

    public function techs()
    {
        return $this->hasManyThrough(
            Tech::class,
            DominionTech::class,
            'dominion_id',
            'id',
            'id',
            'tech_id'
        );
    }

    /*
    *    Abandoned because it lacks flexibility in how perks are added together.
    *    They are nearly always additive (so this would work), but there are exceptions
    *    such as unit costs (where deity and spell can go further than a max of -50%)
    *    and population where tech perk is multiplicative.
    */
    /*
    public function getPerkValue($perkValueString)
    {
        $perkValue = 0;

        $perkValue = $this->race->getPerkValue($perkValueString);
        $perkValue += $this->title->getPerkValue($perkValueString);
        $perkValue += $this->getSpellPerkValue($perkValueString);
        $perkValue += $this->getImprovementPerks($perkValueString);
        $perkValue += $this->getBuildingPerkValue($perkValueString);
        $perkValue += $this->getDeityPerkValue($perkValueString);
        $perkValue += $this->getBuildingPerkValue($perkValueString);
        $perkValue += $this->getTechPerkValue($perkValueString);

        return $perkValue;
    }

    public function getPerkMultiplier($perkValueString)
    {
        return $this->getPerkValue($perkValueString) / 100;
    }
    */


    public function buildings()
    {
        return $this->belongsToMany(
            Building::class,
            'dominion_buildings',
            'dominion_id',
            'building_id'
        )
            ->withTimestamps()
            ->withPivot('owned');
    }

    public function improvements()
    {
        return $this->belongsToMany(
            Improvement::class,
            'dominion_improvements',
            'dominion_id',
            'improvement_id'
        )
            ->withTimestamps()
            ->withPivot('invested');
    }

    public function spells()
    {
        return $this->hasManyThrough(
            Spell::class,
            DominionSpell::class,
            'dominion_id',
            'id',
            'id',
            'spell_id'
        );
    }

    public function resources()
    {
        return $this->hasManyThrough(
            Resource::class,
            DominionResource::class,
            'dominion_id',
            'id',
            'id',
            'resource_id'
        );
    }

    public function deity()
    {
        return $this->hasOneThrough(
            Deity::class,
            DominionDeity::class,
            'dominion_id',
            'id',
            'id',
            'deity_id'
        );
    }

    public function queues()
    {
        return $this->hasMany(Dominion\Queue::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tick()
    {
        return $this->hasOne(Dominion\Tick::class);
    }

    // Eloquent Query Scopes

    public function scopeActive(Builder $query)
    {

        return $query->whereHas('round', function (Builder $query) {
            #$query->where('start_date', '<=', now())
            #    ->where('end_date', '>', now());
            $query->whereRaw('start_date <= NOW() and (end_date IS NULL or end_date > NOW())');
        });
    }

    // Methods

    // todo: move to eloquent events, see $dispatchesEvents
    public function save(array $options = [])
    {
        $recordChanges = isset($options['event']);

        // Verify tick hasn't happened during this request
        if ($this->exists && $this->last_tick_at != $this->fresh()->last_tick_at)
        {
            throw new GameException('The World Spinner is spinning the world. Your request was discarded. Try again later, little one.');
        }

        $saved = parent::save($options);

        if ($saved && $recordChanges)
        {
            $dominionHistoryService = app(HistoryService::class);
            $deltaAttributes = $dominionHistoryService->getDeltaAttributes($this);
            if (isset($options['action'])) {
                $deltaAttributes['action'] = $options['action'];
            }
            /** @noinspection PhpUndefinedVariableInspection */
            $dominionHistoryService->record($this, $deltaAttributes, $options['event']);
        }

        // Recalculate next tick
        $tickService = app(\OpenDominion\Services\Dominion\TickService::class);
        $tickService->precalculateTick($this);

        return $saved;
    }

    public function getDirty()
    {
        $dirty = parent::getDirty();

        $query = $this->newModelQuery();

        $dominionHistoryService = app(HistoryService::class);
        $deltaAttributes = $dominionHistoryService->getDeltaAttributes($this);

        foreach ($deltaAttributes as $attr => $value) {
            if (gettype($this->getAttribute($attr)) != 'boolean' and gettype($this->getAttribute($attr)) != 'string') {
                $wrapped = $query->toBase()->grammar->wrap($attr);
                $dirty[$attr] = $query->toBase()->raw("$wrapped + $value");
            }
        }

        return $dirty;
    }

    /**
     * Route notifications for the mail channel.
     *
     * @return string
     */
    public function routeNotificationForMail(): string
    {
        if($this->isAbandoned())
        {
            return "abandoned-{$dominion->id}@odarena.com";
        }
        return $this->user->email;
    }

    /**
     * Returns whether this Dominion instance is selected by the logged in user.
     *
     * @return bool
     */
    public function isSelectedByAuthUser()
    {
        // todo: move to SelectorService
        $dominionSelectorService = app(SelectorService::class);

        $selectedDominion = $dominionSelectorService->getUserSelectedDominion();

        if ($selectedDominion === null) {
            return false;
        }

        return ($this->id === $selectedDominion->id);
    }

    /**
     * Returns whether this Dominion is locked due to the round having ended or administrative action.
     *
     * Locked Dominions cannot perform actions and are read-only.
     *
     * @return bool
     */
    public function isLocked(): bool
    {
        return $this->is_locked || $this->round->hasEnded();
    }

    public function isUnderProtection(): bool
    {
       return $this->protection_ticks;
    }

    public function isAbandoned(): bool
    {
        return $this->user_id ? false : true;
    }

    public function getLockedReason(int $reason): string
    {
        switch ($reason)
        {
            case 2:
                return "Player's request.";

            case 3:
                return "Rule violation.";

            default:
                return 'Round ended.';
        }
    }

    /**
     * Returns whether this Dominion is the monarch for its realm.
     *
     * @return bool
     */
    public function isMonarch()
    {
        $monarch = $this->realm->monarch;
        return ($monarch !== null && $this->id == $monarch->id);
    }

    /**
     * Returns the choice for monarch of a Dominion.
     *
     * @return Dominion
     */
    public function monarchVote()
    {
        return $this->hasOne(static::class, 'id', 'monarchy_vote_for_dominion_id');
    }

    /**
     * Returns the unit production bonus for a specific resource type (across all eligible units) for this dominion.
     *
     * @param string $resourceType
     * @return float
     */
    public function getUnitPerkProductionBonus(string $resourceType): float
    {
        $bonus = 0;

        foreach ($this->race->units as $unit) {
            $perkValue = $unit->getPerkValue($resourceType);

            if ($perkValue !== 0) {
                $bonus += ($this->{'military_unit' . $unit->slot} * (float)$perkValue);
            }
        }

        return $bonus;
    }

    /**
     * Returns the unit production bonus for a specific resource type (across all eligible units) for this dominion.
     *
     * @param string $resourceType
     * @return float
     */
    public function getUnitPerkProductionBonusFromTitle(string $resourceType): float
    {
        $bonus = 0;

        foreach ($this->race->units as $unit)
        {
            $titlePerkData = $this->race->getUnitPerkValueForUnitSlot($unit->slot, 'production_from_title', null);

            if($titlePerkData)
            {
                $titleKey = $titlePerkData[0];
                $perkResource = $titlePerkData[1];
                $perkAmount = $titlePerkData[2];

                if($resourceType === $perkResource and $this->title->key === $titleKey)
                {
                    $bonus += ($this->{'military_unit' . $unit->slot} * (float)$perkAmount);
                }
            }
        }

        return $bonus;
    }

    # TECHS

    protected function getTechPerks()
    {
        return $this->techs->flatMap(
            function ($tech) {
                return $tech->perks;
            }
        );
    }

    /**
     * @param string $key
     * @return float
     */
    public function getTechPerkValue(string $key): float
    {
        $perks = $this->getTechPerks()->groupBy('key');
        if (isset($perks[$key])) {
            $max = (float)$perks[$key]->max('pivot.value');
            if ($max < 0) {
                return (float)$perks[$key]->min('pivot.value');
            }
            return $max;
        }
        return 0;
    }

    /**
     * @param string $key
     * @return float
     */
    public function getTechPerkMultiplier(string $key): float
    {
        return ($this->getTechPerkValue($key) / 100);
    }

    # BUILDINGS

    protected function getBuildingPerks()
    {
        return $this->buildings->flatMap(
            function ($building)
            {
                return $building->perks;
            }
        );
    }

    /**
     * @param string $key
     * @return float
     */
     public function getBuildingProduction(string $resourceType): float
     {
         $production = 0;

         foreach ($this->buildings as $building)
         {
             $perkValue = $building->getPerkValue($resourceType);

             if ($perkValue !== 0)
             {
                 $production = $building->pivot->owned * $perkValue;
             }
         }

         return $production;
     }

      public function getBuildingPerkValue(string $perkKey)#: float
      {
          $landSize = $this->land_plain + $this->land_mountain + $this->land_swamp + $this->land_forest + $this->land_hill + $this->land_water;
          $perk = 0;

          foreach ($this->buildings as $building)
          {
              $perkValueString = $building->getPerkValue($perkKey);

              if(is_numeric($perkValueString))
              {
                  $perkValueString = (float)$perkValueString;
              }

              # Default value for housing.
              if($perkKey == 'housing' and !is_numeric($perkValueString))
              {
                  $perkValueString = 15;
              }

              # Default value for jobs.
              if($perkKey == 'jobs' and !is_numeric($perkValueString))
              {
                  $perkValueString = 20;
              }

              if($perkValueString)
              {
                  # Basic production and other single-value perks
                  if(
                          $perkKey == 'gold_production'
                          or $perkKey == 'food_production'
                          or $perkKey == 'ore_production'
                          or $perkKey == 'lumber_production'
                          or $perkKey == 'mana_production'
                          or $perkKey == 'draftee_generation'

                          or $perkKey == 'gold_production_raw'
                          or $perkKey == 'food_production_raw'
                          or $perkKey == 'ore_production_raw'
                          or $perkKey == 'lumber_production_raw'
                          or $perkKey == 'mana_production_raw'
                          or $perkKey == 'gems_production_raw'
                          or $perkKey == 'blood_production_raw'
                          or $perkKey == 'soul_production_raw'
                          or $perkKey == 'pearls_production_raw'

                          or $perkKey == 'gold_upkeep_raw'
                          or $perkKey == 'food_upkeep_raw'
                          or $perkKey == 'ore_upkeep_raw'
                          or $perkKey == 'lumber_upkeep_raw'
                          or $perkKey == 'mana_upkeep_raw'
                          or $perkKey == 'blood_upkeep_raw'
                          or $perkKey == 'soul_upkeep_raw'
                          or $perkKey == 'pearls_upkeep_raw'
                          or $perkKey == 'prisoners_upkeep_raw'

                          or $perkKey == 'xp_generation_raw'

                          # Building-specific housing
                          or $perkKey == 'afflicted_unit1_housing'
                          or $perkKey == 'human_unit1_housing'
                          or $perkKey == 'human_unit2_housing'
                          or $perkKey == 'troll_unit2_housing'
                          or $perkKey == 'troll_unit4_housing'

                          or $perkKey == 'faster_returning_units'
                      )
                  {
                      #$perk += (float)$perkValueString;

                      $perk += $perkValueString * $building->pivot->owned;
                  }

                  # Mods with ratio, multiplier, and max
                  elseif(
                          # OP/DP mods
                          $perkKey == 'defensive_power'
                          or $perkKey == 'offensive_power'
                          or $perkKey == 'reduces_offensive_power'
                          or $perkKey == 'defensive_modifier_reduction'
                          or $perkKey == 'defensive_casualties'
                          or $perkKey == 'offensive_casualties'
                          or $perkKey == 'casualties'

                          or $perkKey == 'morale_gains'
                          or $perkKey == 'base_morale'

                          or $perkKey == 'faster_return'

                          # Production mods
                          or $perkKey == 'gold_production_mod'
                          or $perkKey == 'food_production_mod'
                          or $perkKey == 'lumber_production_mod'
                          or $perkKey == 'ore_production_mod'
                          or $perkKey == 'gems_production_mod'
                          or $perkKey == 'mana_production_mod'
                          or $perkKey == 'xp_generation_mod'
                          or $perkKey == 'pearls_production_mod'
                          or $perkKey == 'exchange_rate'

                          # Unit costs
                          or $perkKey == 'unit_gold_costs'
                          or $perkKey == 'unit_ore_costs'
                          or $perkKey == 'unit_lumber_costs'
                          or $perkKey == 'unit_mana_costs'
                          or $perkKey == 'unit_food_costs'
                          or $perkKey == 'unit_blood_costs'

                          # Unit training
                          or $perkKey == 'extra_units_trained'
                          or $perkKey == 'drafting'

                          # Spy/wizard
                          or $perkKey == 'spell_cost'
                          or $perkKey == 'spy_losses'
                          or $perkKey == 'spy_strength'
                          or $perkKey == 'spy_strength_on_defense'
                          or $perkKey == 'spy_strength_on_offense'
                          or $perkKey == 'spy_strength_recovery'
                          or $perkKey == 'wizard_losses'
                          or $perkKey == 'wizard_strength'
                          or $perkKey == 'wizard_strength_recovery'
                          or $perkKey == 'wizard_cost'

                          # Construction/Rezoning and Land
                          or $perkKey == 'construction_cost'
                          or $perkKey == 'rezone_cost'
                          or $perkKey == 'land_discovered'

                          # Espionage
                          or $perkKey == 'gold_theft_reduction'
                          or $perkKey == 'gem_theft_reduction'
                          or $perkKey == 'lumber_theft_reduction'
                          or $perkKey == 'ore_theft_reduction'
                          or $perkKey == 'food_theft_reduction'

                          # Improvements
                          or $perkKey == 'improvements_capped'
                          or $perkKey == 'improvement_interest'
                          or $perkKey == 'invest_bonus'
                          or $perkKey == 'gold_invest_bonus'
                          or $perkKey == 'food_invest_bonus'
                          or $perkKey == 'ore_invest_bonus'
                          or $perkKey == 'lumber_invest_bonus'
                          or $perkKey == 'mana_invest_bonus'
                          or $perkKey == 'blood_invest_bonus'
                          or $perkKey == 'soul_invest_bonus'

                      )
                  {
                      $perkValues = $this->extractBuildingPerkValues($perkValueString);
                      $ratio = (float)$perkValues[0];
                      $multiplier = (float)$perkValues[1];
                      $max = (float)$perkValues[2] / 100;
                      $owned = $building->pivot->owned;

                      #$effect = min($owned / $landSize * $ratio * $multiplier, $max);

                      $perk += min($owned / $landSize * $ratio * $multiplier, $max) * 100;
                  }
                  # Mods with ratio, multiplier, and no max
                  elseif(
                          # OP/DP mods
                          $perkKey == 'improvements'
                          or $perkKey == 'lightning_bolt_damage'
                          or $perkKey == 'fireball_damage'
                          or $perkKey == 'population_growth'
                          or $perkKey == 'reduces_conversions'
                          or $perkKey == 'reduces_attrition'
                      )
                  {
                      $perkValues = $this->extractBuildingPerkValues($perkValueString);
                      $ratio = (float)$perkValues[0];
                      $multiplier = (float)$perkValues[1];
                      $owned = $building->pivot->owned;

                      #$effect = $owned / $landSize * $ratio * $multiplier;

                      $perk += ($owned / $landSize * $ratio * $multiplier) * 100;
                  }
                  # Production depleting
                  elseif(
                          # OP/DP mods
                          $perkKey == 'gold_production_depleting_raw'
                          or $perkKey == 'gems_production_depleting_raw'
                          or $perkKey == 'ore_production_depleting_raw'
                          or $perkKey == 'mana_production_depleting_raw'
                          or $perkKey == 'lumber_production_depleting_raw'
                          or $perkKey == 'food_production_depleting_raw'
                      )
                  {
                      $perkValues = $this->extractBuildingPerkValues($perkValueString);
                      $baseProduction = (float)$perkValues[0];
                      $ticklyReduction = (float)$perkValues[1];
                      $ticks = $this->round->ticks;

                      #$perkValueString = max(0, ($baseProduction - ($ticklyReduction * $ticks)));

                      $perk += max(0, ($baseProduction - ($ticklyReduction * $ticks)));
                  }
                  # Production/housing increasing
                  elseif(
                          # OP/DP mods
                          $perkKey == 'gold_production_increasing'
                          or $perkKey == 'gems_production_increasing'
                          or $perkKey == 'ore_production_increasing'
                          or $perkKey == 'mana_production_increasing'
                          or $perkKey == 'lumber_production_increasing'
                          or $perkKey == 'food_production_increasing'
                          or $perkKey == 'housing_increasing'
                          or $perkKey == 'military_housing_increasing'
                          or $perkKey == 'faster_returning_units_increasing'
                      )
                  {
                      $perkValues = $this->extractBuildingPerkValues($perkValueString);
                      $baseValue = (float)$perkValues[0];
                      $ticklyIncrease = (float)$perkValues[1];
                      $ticks = $this->round->ticks;

                      #$perkValueString = $baseValue + ($ticklyIncrease * $ticks);

                      $perk += $baseValue + ($ticklyIncrease * $ticks);

                  }

                  # Resource conversion
                  elseif($perkKey == 'resource_conversion')
                  {

                      $resourceCalculator = app(ResourceCalculator::class);
                      $perkValues = $this->extractBuildingPerkValues($perkValueString);

                      $sourceAmount = (float)$perkValues[0];
                      $sourceResourceKey = (string)$perkValues[1];
                      $targetAmount = (float)$perkValues[2];
                      $targetResourceKey = (string)$perkValues[3];
                      $buildingOwned = $building->pivot->owned;

                      $maxAmountConverted = min($resourceCalculator->getAmount($this, $sourceResourceKey), $buildingOwned * $sourceAmount);
                      $amountCreated = $maxAmountConverted / ($sourceAmount / $targetAmount);

                      #$data = [$sourceResourceKey => $maxAmountConverted, $targetResourceKey => $amountCreated];

                      return ['from' => [$sourceResourceKey => $maxAmountConverted], 'to' => [$targetResourceKey => $amountCreated]];

                      #$perk += $baseValue + ($ticklyIncrease * $ticks);

                  }
                  elseif($perkKey !== 'jobs' and $perkKey !== 'housing')
                  {
                      dd("Perk key ($perkKey) is undefined.");
                  }
              }

          }

          return $perk;
      }

    /**
     * @param string $key
     * @return float
     */
    public function getBuildingPerkMultiplier(string $key): float
    {
        return ($this->getBuildingPerkValue($key) / 100);
    }

    public function extractBuildingPerkValues(string $perkValue)
    {
        if (str_contains($perkValue, ',')) {
            $perkValues = explode(',', $perkValue);

            foreach($perkValues as $key => $value) {
                if (!str_contains($value, ';')) {
                    continue;
                }

                $perkValues[$key] = explode(';', $value);
            }
        }

        return $perkValues;
    }

    # SPELLS

    protected function getSpellPerks()
    {
        return $this->spells->flatMap(
            function ($spell) {
                return $spell->perks;
            }
        );
    }
    /**
     * @param string $key
     * @return float
     */

     public function getSpellPerkValue(string $perkKey): float
     {
         $landSize = $this->land_plain + $this->land_mountain + $this->land_swamp + $this->land_forest + $this->land_hill + $this->land_water;
         $perk = 0;

         foreach ($this->spells as $spell)
         {
             $perkValueString = $spell->getPerkValue($perkKey);

             if($perkValueString and (is_numeric($perkValueString) and !is_array($perkValueString)))
             {
                 # Single value numeric perks
                 if(
                         $perkKey == 'gold_production_mod'
                         or $perkKey == 'food_production_mod'
                         or $perkKey == 'ore_production_mod'
                         or $perkKey == 'gems_production_mod'
                         or $perkKey == 'lumber_production_mod'
                         or $perkKey == 'mana_production_mod'
                         or $perkKey == 'pearls_production_mod'
                         or $perkKey == 'xp_generation_mod'

                         or $perkKey == 'gold_production_raw'
                         or $perkKey == 'food_production_raw'
                         or $perkKey == 'ore_production_raw'
                         or $perkKey == 'gems_production_raw'
                         or $perkKey == 'lumber_production_raw'
                         or $perkKey == 'mana_production_raw'
                         or $perkKey == 'pearls_production_raw'
                         or $perkKey == 'xp_generation_raw'

                         or $perkKey == 'gold_production_raw_mod'
                         or $perkKey == 'food_production_raw_mod'
                         or $perkKey == 'ore_production_raw_mod'
                         or $perkKey == 'gems_production_raw_mod'
                         or $perkKey == 'lumber_production_raw_mod'
                         or $perkKey == 'mana_production_raw_mod'
                         or $perkKey == 'pearls_production_raw_mod'
                         or $perkKey == 'xp_generation_raw_mod'

                         or $perkKey == 'no_gold_production'
                         or $perkKey == 'no_food_production'
                         or $perkKey == 'no_ore_production'
                         or $perkKey == 'no_lumber_production'
                         or $perkKey == 'no_mana_production'
                         or $perkKey == 'no_xp_generation'

                         or $perkKey == 'unit_gold_costs'
                         or $perkKey == 'unit_ore_costs'
                         or $perkKey == 'unit_lumber_costs'
                         or $perkKey == 'unit_mana_costs'
                         or $perkKey == 'unit_food_costs'
                         or $perkKey == 'unit_blood_costs'

                         or $perkKey == 'unit_gold_cost'
                         or $perkKey == 'unit_ore_cost'
                         or $perkKey == 'unit_lumber_cost'

                         or $perkKey == 'food_production_raw'

                         or $perkKey == 'population_growth'
                         or $perkKey == 'improvements'
                         or $perkKey == 'invest_bonus'

                         or $perkKey == 'cannot_build'
                         or $perkKey == 'cannot_invade'
                         or $perkKey == 'cannot_explore'
                         or $perkKey == 'no_drafting'

                         or $perkKey == 'gold_theft'
                         or $perkKey == 'gems_theft'
                         or $perkKey == 'ore_theft'
                         or $perkKey == 'mana_theft'
                         or $perkKey == 'lumber_theft'
                         or $perkKey == 'all_theft'

                         or $perkKey == 'reveal_ops'
                         or $perkKey == 'fog_of_war'
                         or $perkKey == 'chance_to_reflect_spells'
                         or $perkKey == 'damage_from_spells'
                         or $perkKey == 'damage_from_fireballs'
                         or $perkKey == 'damage_from_lightning_bolts'


                         or $perkKey == 'offensive_power'
                         or $perkKey == 'defensive_power'
                         or $perkKey == 'drafting'
                         or $perkKey == 'can_kill_immortal'
                         or $perkKey == 'defensive_power_from_peasants'
                         or $perkKey == 'faster_return'
                         or $perkKey == 'training_time'
                         or $perkKey == 'target_defensive_power_mod'

                         or $perkKey == 'casualties'
                         or $perkKey == 'offensive_casualties'
                         or $perkKey == 'defensive_casualties'
                         or $perkKey == 'increases_casualties'
                         or $perkKey == 'increases_casualties_on_defense'
                         or $perkKey == 'increases_casualties_on_offense'
                         or $perkKey == 'increases_enemy_draftee_casualties'

                         or $perkKey == 'no_conversions'
                         or $perkKey == 'convert_enemy_casualties_to_food'
                         or $perkKey == 'convert_peasants_to_champions'

                         or $perkKey == 'increases_casualties_on_offense_from_wizard_ratio'
                         or $perkKey == 'offensive_power_on_retaliation'
                         or $perkKey == 'immune_to_temples'

                         or $perkKey == 'spy_strength'
                         or $perkKey == 'spy_strength_recovery'
                         or $perkKey == 'wizard_strength'
                         or $perkKey == 'wizard_strength_recovery'
                         or $perkKey == 'immortal_spies'
                         or $perkKey == 'immortal_wizards'

                         or $perkKey == 'land_discovered'
                         or $perkKey == 'buildings_destroyed'
                         or $perkKey == 'barren_land_rezoned'

                         or $perkKey == 'opens_portal'
                         or $perkKey == 'stop_land_generation'
                         or $perkKey == 'defensive_power_vs_insect_swarm'

                         or $perkKey == 'stasis'

                         # Cult
                         or $perkKey == 'mind_control'
                         or $perkKey == 'enthralling'
                         or $perkKey == 'cogency'
                         or $perkKey == 'persuasion'

                         # Reptilians

                         or $perkKey == 'blind_to_reptilian_spies_on_info'
                         or $perkKey == 'blind_to_reptilian_spies_on_theft'
                     )
                 {
                     $perk += (float)$perkValueString;
                 }
                 elseif($perkValueString and (!is_numeric($perkValueString) and !is_array($perkValueString)))
                 {
                    $perk = (string)$perkValueString;
                 }
             }
         }

         return $perk;
     }

    /**
     * @param string $key
     * @return float
     */
    public function getSpellPerkMultiplier(string $key): float
    {
        return ($this->getSpellPerkValue($key) / 100);
    }

    # IMPROVEMENTS

    protected function getImprovementPerks()
    {
        return $this->improvements->flatMap(
            function ($improvement)
            {
                return $improvement->perks;
            }
        );
    }

   /**
    * @param string $key
    * @return float
    */
    public function getImprovementPerkValue(string $perkKey): float
    {
        $perk = 0;
        $landSize = $this->land_plain + $this->land_mountain + $this->land_swamp + $this->land_forest + $this->land_hill + $this->land_water;

        foreach ($this->improvements as $improvement)
        {
            if($perkValueString = $improvement->getPerkValue($perkKey))
            {
                $perkValues = $this->extractImprovementPerkValues($perkValueString);
                $max = (float)$perkValues[0];
                $coefficient = (float)$perkValues[1];
                $invested = (float)$improvement->pivot->invested;

                $perk += $max * (1 - exp(-$invested / ($coefficient * $landSize + 15000)));
            }
        }

        if($perk === 0.0)
        {
        #    dd($perkKey, $perk, $max, $coefficient, $invested, ($max * (1 - exp(-$invested / ($coefficient * $landSize + 15000)))));
        }

        #dump($perkKey, $perk, $max, $coefficient, $invested, ($max * (1 - exp(-$invested / ($coefficient * $landSize + 15000)))));

        $multiplier = 1;
        $multiplier += $this->getBuildingPerkMultiplier('improvements');
        $multiplier += $this->getBuildingPerkMultiplier('improvements_capped');
        $multiplier += $this->getSpellPerkMultiplier('improvements');
        $multiplier += $this->getTechPerkMultiplier('improvements');
        $multiplier += $this->getDeityPerkMultiplier('improvements');
        $multiplier += $this->race->getPerkMultiplier('improvements_max');
        #if($this->title)
        #{
        #    $multiplier += $this->title->getPerkMultiplier('improvements') * $this->title->getPerkBonus($this);
        #}

        $perk *= $multiplier;



        return $perk;
    }

    /**
     * @param string $key
     * @return float
     */
    public function getImprovementPerkMultiplier(string $key): float
    {
        return ($this->getImprovementPerkValue($key) / 100);
    }

    public function extractImprovementPerkValues(string $perkValue)
    {
        if (str_contains($perkValue, ','))
        {
            $perkValues = explode(',', $perkValue);

            foreach($perkValues as $key => $value)
            {
                if (!str_contains($value, ';'))
                {
                    continue;
                }

                $perkValues[$key] = explode(';', $value);
            }
        }

        return $perkValues;
    }

    # DEITY

    public function hasDeity()
    {
        return $this->deity ? true : false;
    }

    public function hasPendingDeitySubmission(): bool
    {
        $queueService = app(QueueService::class);
        return $queueService->getDeityQueue($this)->count();
    }

    public function getPendingDeitySubmission()
    {
        if($this->hasPendingDeitySubmission())
        {
            $queueService = app(QueueService::class);

            foreach($queueService->getDeityQueue($this) as $row)
            {
                $deityKey = $row['resource'];
            }

            return Deity::where('key', $deityKey)->first();
        }

        return false;
    }

    public function getPendingDeitySubmissionTicksLeft(): int
    {
        if(!$this->hasPendingDeitySubmission())
        {
            return 0;
        }

        $queueService = app(QueueService::class);

        foreach($queueService->getDeityQueue($this) as $row)
        {
            $ticksLeft = $row['hours'];
        }

        return $ticksLeft;
    }

    public function getDeity(): Deity
    {
        return $this->deity;
    }

    public function getDominionDeity(): DominionDeity
    {
        return DominionDeity::where('deity_id', $this->getDeity()->id)
                            ->where('dominion_id', $this->id)
                            ->first();
    }

    /**
    * @param string $key
    * @return float
    */
    public function getDeityPerkValue(string $perkKey): float
    {
        if(!$this->hasDeity())
        {
            return 0;
        }

        $multiplier = 1;
        $multiplier += min($this->getDominionDeity()->duration * 0.1 / 100, 1);
        $multiplier += $this->getBuildingPerkMultiplier('deity_perks');
        $multiplier += $this->getSpellPerkMultiplier('deity_perks');
        $multiplier += $this->getTechPerkMultiplier('deity_perks');
        $multiplier += $this->race->getPerkMultiplier('deity_perks');
        $multiplier += $this->title->getPerkMultiplier('deity_perks');

        return (float)$this->deity->getPerkValue($perkKey) * $multiplier;
    }

    /**
     * @param string $key
     * @return float
     */
    public function getDeityPerkMultiplier(string $key): float
    {
        return ($this->getDeityPerkValue($key) / 100);
    }

    /**
     * Returns the unit production bonus for a specific resource type (across all eligible units) for this dominion.
     *
     * @param string $resourceType
     * @return float
     */
    public function getUnitPerkProductionBonusFromDeity(string $resourceType): float
    {
        $bonus = 0;

        foreach ($this->race->units as $unit)
        {
            $titlePerkData = $this->race->getUnitPerkValueForUnitSlot($unit->slot, 'production_from_deity', null);

            if($titlePerkData)
            {
                $titleKey = $titlePerkData[0];
                $perkResource = $titlePerkData[1];
                $perkAmount = $titlePerkData[2];

                if($resourceType === $perkResource and $this->getDeity()->key === $deityKey)
                {
                    $bonus += ($this->{'military_unit' . $unit->slot} * (float)$perkAmount);
                }
            }
        }

        return $bonus;
    }

    # Land improvements 2.0


    public function getLandImprovementsPerkValue(string $perkKey): float
    {
        $landHelper = app(LandHelper::class);

        $perk = 0;

        foreach($landHelper->getLandTypes($this->race) as $landType)
        {
            if(isset($this->race->land_improvements[$landType][$perkKey]))
            {
                #echo "<pre>$perkKey from $landType: " . $this->race->land_improvements[$landType][$perkKey] . " </pre>";
                $perk += $this->race->land_improvements[$landType][$perkKey] * $this->{'land_' . $landType};
            }
        }
        return $perk;
    }

    public function getLandImprovementsPerkMultiplier(string $perkKey): float
    {
        $landHelper = app(LandHelper::class);

        $perk = 0;

        foreach($landHelper->getLandTypes($this->race) as $landType)
        {
            if(isset($this->race->land_improvements[$landType][$perkKey]))
            {
                #dd($this->race->land_improvements, $landType, $perkKey, $this->race->land_improvements[$landType][$perkKey]);
                $perk += $this->race->land_improvements[$landType][$perkKey] * ($this->{'land_' . $landType} / $landHelper->getTotalLand($this));
            }
        }
        return $perk;
    }


}
