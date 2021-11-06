<?php

# 1000-acre factory

namespace OpenDominion\Factories;

use Auth;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Pack;
use OpenDominion\Models\Race;
use OpenDominion\Models\Title;
use OpenDominion\Models\Realm;
use OpenDominion\Models\Round;
use OpenDominion\Models\User;
use OpenDominion\Models\Deity;

use Illuminate\Support\Carbon;
use OpenDominion\Helpers\RaceHelper;
use OpenDominion\Calculators\Dominion\BuildingCalculator;
use OpenDominion\Calculators\Dominion\BarbarianCalculator;
use OpenDominion\Services\Dominion\DeityService;
use OpenDominion\Services\Dominion\ResourceService;

class DominionFactory
{


    public function __construct(
        RaceHelper $raceHelper
    )
    {
        $this->raceHelper = app(RaceHelper::class);
        $this->buildingCalculator = app(BuildingCalculator::class);
        $this->barbarianCalculator = app(BarbarianCalculator::class);
        $this->deityService = app(DeityService::class);
        $this->resourceService = app(ResourceService::class);
    }

    /**
     * Creates and returns a new Dominion instance.
     *
     * @param User $user
     * @param Realm $realm
     * @param Race $race
     * @param Title $title
     * @param string $rulerName
     * @param string $dominionName
     * @param Pack|null $pack
     * @return Dominion
     * @throws GameException
     */
    public function create(
        User $user,
        Realm $realm,
        Race $race,
        Title $title,
        string $rulerName,
        string $dominionName,
        ?Pack $pack = null
    ): Dominion {
        $this->guardAgainstCrossRoundRegistration($user, $realm->round);
        $this->guardAgainstMultipleDominionsInARound($user, $realm->round);
        $this->guardAgainstMismatchedAlignments($race, $realm, $realm->round);

        // Starting resources are based on this.
        $acresBase = 1000;

        $startingParameters = [];
        $startingResources = [];

        $startingParameters['prestige'] = $acresBase/2;
        $startingParameters['npc_modifier'] = 0;
        $startingParameters['protection_ticks'] = 96;


        if($race->alignment == 'npc' and $race->name == 'Barbarian')
        {
            $startingParameters['protection_ticks'] = 0;

            # NPC modifier is a number from 500 to 1000 (skewed toward higher).
            # It is to be used as a multiplier but stored as an int in database.
            $startingParameters['npc_modifier'] = min(rand(500,1200), 1000);

            # For usage in this function, divide npc_modifier by 1000 to create a multiplier.
            $npcModifier = $startingParameters['npc_modifier'] / 1000;

            $acresBase *= $npcModifier;
        }

        $startingBuildings = $this->getStartingBuildings($race, $acresBase);

        $startingLand = $this->getStartingLand(
            $race,
            $this->getStartingBarrenLand($race, $acresBase),
            $startingBuildings
        );

        # Late-joiner bonus:
        # Give +1.5% starting resources per hour late, max +150% (at 100 hours, mid-day 4).
        # Fix this for zero-starts?
        $lateJoinMultiplier = 1 + $realm->round->ticks * 0.004;

        $startingParameters['draftees'] = 0;
        $startingParameters['unit1'] = 0;
        $startingParameters['unit2'] = 0;
        $startingParameters['unit3'] = 0;
        $startingParameters['unit4'] = 0;
        $startingParameters['spies'] = 0;
        $startingParameters['wizards'] = 0;
        $startingParameters['archmages'] = 0;

        if($race->name !== 'Barbarian')
        {
            if(Auth::user()->display_name == $rulerName)
            {
                $startingParameters['prestige'] += 100;
            }

            if($race->name == 'Demon')
            {
                $startingParameters['unit4'] = 1;
            }

            if($race->name == 'Growth')
            {
                $startingParameters['draft_rate'] = 100;
            }

            if($race->name == 'Kerranad')
            {
                $startingResources['gems'] = 400000;
            }

            if($race->name == 'Legion')
            {
                $startingParameters['unit4'] = 1;
            }

            $startingParameters['draft_rate'] = 50;
        }
        else
        {
              $startingParameters['peasants'] = $acresBase * (rand(50,200)/100);

              $startingParameters['draft_rate'] = 0;

              # Starting units for Barbarians
              $dpaTarget = $this->barbarianCalculator->getDpaTarget(null, $realm->round, $startingParameters['npc_modifier']);
              $opaTarget = $this->barbarianCalculator->getOpaTarget(null, $realm->round, $startingParameters['npc_modifier']);

              $dpRequired = $acresBase * $dpaTarget;
              $opRequired = $acresBase * $opaTarget;

              $specsRatio = rand($this->barbarianCalculator->getSetting('SPECS_RATIO_MIN'), $this->barbarianCalculator->getSetting('SPECS_RATIO_MIN'))/100;
              $elitesRatio = 1-$specsRatio;
              $startingParameters['unit3'] = floor(($dpRequired * $elitesRatio)/5);
              $startingParameters['unit2'] = floor(($dpRequired * $specsRatio)/3);

              $specsRatio = rand($this->barbarianCalculator->getSetting('SPECS_RATIO_MIN'), $this->barbarianCalculator->getSetting('SPECS_RATIO_MIN'))/100;
              $elitesRatio = 1-$specsRatio;
              $startingParameters['unit1'] = floor(($opRequired * $specsRatio)/3);
              $startingParameters['unit4'] = floor(($opRequired * $elitesRatio)/5);

              $startingParameters['protection_ticks'] = 0;
        }

        $startingParameters['xp'] = $startingParameters['prestige'];

        # Starting land
        $startingLand = $this->getStartingLand(
            $race,
            $this->getStartingBarrenLand($race, $acresBase),
            $startingBuildings
        );

        # Peasants
        $housingPerBarren = 5;
        $housingPerBarren += $race->getPerkValue('extra_barren_max_population');
        foreach($startingLand as $landType => $amount)
        {
            $housingPerBarren += $race->getPerkValue('extra_barren_' . $landType . '_max_population');
        }

        $popBonus = 1;
        $popBonus += $race->getPerkMultiplier('max_population');
        $popBonus *= 1 + $startingParameters['prestige']/10000;

        $startingParameters['peasants'] = floor($acresBase * $housingPerBarren * $popBonus);

        if(!$race->getPerkValue('no_food_consumption'))
        {
            $startingResources['food'] = floor($startingParameters['peasants'] * 18 * 0.25 * (1 + $race->getPerkValue('food_consumption_raw')));
        }

        foreach($startingResources as $resourceKey => $amount)
        {
            $startingResources[$resourceKey] = $amount * $lateJoinMultiplier;
        }

        $dominion = Dominion::create([
            'user_id' => $user->id,
            'round_id' => $realm->round->id,
            'realm_id' => $realm->id,
            'race_id' => $race->id,
            'title_id' => $title->id,
            'pack_id' => $pack->id ?? null,

            'ruler_name' => $rulerName,
            'name' => $dominionName,
            'prestige' => $startingParameters['prestige'],
            'xp' => $startingParameters['xp'],

            'peasants' => $startingParameters['peasants'],
            'peasants_last_hour' => 0,

            'draft_rate' => $startingParameters['draft_rate'],
            'morale' => 100,
            'spy_strength' => 100,
            'wizard_strength' => 100,

            'resource_gold' => 0,
            'resource_food' => 0,
            'resource_lumber' => 0,
            'resource_mana' => 0,
            'resource_ore' => 0,
            'resource_gems' => 0,
            'resource_champion' => 0,
            'resource_soul' => 0,
            'resource_blood' => 0,
            'resource_tech' => 0,

            'military_draftees' => intval($startingParameters['draftees']),
            'military_unit1' => intval($startingParameters['unit1']),
            'military_unit2' => intval($startingParameters['unit2']),
            'military_unit3' => intval($startingParameters['unit3']),
            'military_unit4' => intval($startingParameters['unit4']),
            'military_spies' => intval($startingParameters['spies']),
            'military_wizards' => intval($startingParameters['wizards']),
            'military_archmages' => intval($startingParameters['archmages']),

            'land_plain' => $startingLand['plain'],
            'land_mountain' => $startingLand['mountain'],
            'land_swamp' => $startingLand['swamp'],
            'land_cavern' => $startingLand['cavern'],
            'land_forest' => $startingLand['forest'],
            'land_hill' => $startingLand['hill'],
            'land_water' => $startingLand['water'],

            'npc_modifier' => $startingParameters['npc_modifier'],

            'protection_ticks' => $startingParameters['protection_ticks'],
        ]);

        $this->buildingCalculator->createOrIncrementBuildings($dominion, $startingBuildings);
        $this->resourceService->updateResources($dominion, $startingResources);


        if($race->name == 'Barbarian')
        {
            $deity = Deity::where('key','ib_tham')->first();
            $this->deityService->completeSubmissionToDeity($dominion, $deity);
        }

        if($race->getPerkValue('starts_devoted_to_azk_hurum'))
        {
            $deity = Deity::where('key','azk_hurum')->first();
            $this->deityService->completeSubmissionToDeity($dominion, $deity);
        }

        if($race->getPerkValue('starts_devoted_to_tiranthael'))
        {
            $deity = Deity::where('key','tiranthael')->first();
            $this->deityService->completeSubmissionToDeity($dominion, $deity);
        }

        return $dominion;

    }

    /**
     * @param User $user
     * @param Round $round
     * @throws GameException
     */
    protected function guardAgainstCrossRoundRegistration(User $user, Round $round): void
    {
        if($round->hasEnded())
        {
            throw new GameException('You cannot register for a round that has ended.');
        }
    }

    /**
     * @param User $user
     * @param Round $round
     * @throws GameException
     */
    protected function guardAgainstMultipleDominionsInARound(User $user, Round $round): void
    {
        $dominionCount = Dominion::query()
            ->where([
                'user_id' => $user->id,
                'round_id' => $round->id,
            ])
            ->count();

        if ($dominionCount > 0) {
            throw new GameException('User already has a dominion in this round');
        }
    }

    /**
     * @param Race $race
     * @param Realm $realm
     * @param Round $round
     * @throws GameException
     */
    protected function guardAgainstMismatchedAlignments(Race $race, Realm $realm, Round $round): void
    {
        if (!$round->mixed_alignment && $race->alignment !== $realm->alignment /*and $race->alignment !== 'independent'*/)
        {
            throw new GameException('Faction and realm alignment do not match');
        }
    }

    /**
     * Get amount of barren land a new Dominion starts with.
     *
     * @return array
     */
    protected function getStartingBarrenLand($race, $acresBase): array
    {
        # Change this to just look at home land type?
        # Special treatment for Void, Growth, Myconid, Merfolk, and Swarm
        if($race->name == 'Void')
        {
          return [
              'plain' => 100,
              'mountain' => 500,
              'swamp' => 200,
              'cavern' => 0,
              'forest' => 0,
              'hill' => 200,
              'water' => 0,
          ];
        }
        elseif($race->name == 'Growth')
        {
          return [
              'plain' => 0,
              'mountain' => 0,
              'swamp' => 0,
              'cavern' => 0,
              'forest' => 0,
              'hill' => 0,
              'water' => 0,
          ];
        }
        elseif($race->name == 'Dragon')
        {
          return [
              'plain' => 0,
              'mountain' => $acresBase,
              'swamp' => 0,
              'cavern' => 0,
              'forest' => 0,
              'hill' => 0,
              'water' => 0,
          ];
        }
        elseif($race->name == 'Myconid')
        {
          return [
              'plain' => 0,
              'mountain' => 0,
              'swamp' => 0,
              'cavern' => 0,
              'forest' => 0,
              'hill' => 0,
              'water' => 0,
          ];
        }
        elseif($race->name == 'Merfolk')
        {
          return [
              'plain' => 0,
              'mountain' => 0,
              'swamp' => 0,
              'cavern' => 0,
              'forest' => 0,
              'hill' => 0,
              'water' => $acresBase,
          ];
        }
        elseif($race->name == 'Icekin')
        {
          return [
              'plain' => 0,
              'mountain' => $acresBase,
              'swamp' => 0,
              'cavern' => 0,
              'forest' => 0,
              'hill' => 0,
              'water' => 0,
          ];
        }
        elseif($race->name == 'Sylvan' or $race->name == 'Wood Elf')
        {
          return [
              'plain' => 0,
              'mountain' => 0,
              'swamp' => 0,
              'cavern' => 0,
              'forest' => $acresBase,
              'hill' => 0,
              'water' => 0,
          ];
        }
        elseif($race->name == 'Elementals')
        {
          return [
              'plain' => 170,
              'mountain' => 166,
              'swamp' => 166,
              'cavern' => 0,
              'forest' => 166,
              'hill' => 166,
              'water' => 166,
          ];
        }
        elseif($race->name == 'Kerranad')
        {
          return [
              'plain' => 0,
              'mountain' => 0,
              'swamp' => 0,
              'cavern' => 0,
              'forest' => 0,
              'hill' => 0,
              'water' => 0,
          ];
        }
        elseif($race->name == 'Barbarian')
        {
          return [
              'plain' => 10,
              'mountain' => 10,
              'swamp' => 10,
              'cavern' => 0,
              'forest' => 10,
              'hill' => 10,
              'water' => 10,
          ];
        }
        else
        {
            return [
                'plain' => 175,
                'mountain' => 175,
                'swamp' => 175,
                'cavern' => 0,
                'forest' => 175,
                'hill' => 150,
                'water' => 150,
            ];
        }
    }

    /**
     * Get amount of buildings a new Dominion starts with.
     *
     * @return array
     */
    protected function getStartingBuildings($race, $acresBase): array
    {
        # Default
        $startingBuildings = [
            'farm' => 0,
            'smithy' => 0,
            'residence' => 0,
            'lumberyard' => 0,
            'forest_haven' => 0,
            'ore_mine' => 0,
            'gem_mine' => 0,
            'barracks' => 0,
            'tower' => 0,
            'wizard_guild' => 0,
            'temple' => 0,
            'dock' => 0,
            'shed' => 0,

            'tissue_swamp' => 0,
            'mycelia' => 0,
            'ziggurat' => 0,
        ];

        if($race->name == 'Kerranad')
        {
            $startingBuildings['farm'] = 50;
            $startingBuildings['smithy'] = 200;
            $startingBuildings['residence'] = 100;
            $startingBuildings['lumberyard'] = 50;
            $startingBuildings['forest_haven'] = 25;
            $startingBuildings['ore_mine'] = 100;
            $startingBuildings['gem_mine'] = 300;
            $startingBuildings['tower'] = 50;
            $startingBuildings['wizard_guild'] = 25;
            $startingBuildings['temple'] = 50;
            $startingBuildings['dock'] = 50;
        }
        elseif($race->name == 'Growth')
        {
          $startingBuildings['tissue_swamp'] = $acresBase;
        }
        elseif($race->name == 'Myconid')
        {
          $startingBuildings['mycelia'] = $acresBase;
        }
        elseif($race->name == 'Barbarian')
        {
            $startingBuildings['farm'] = floor($acresBase*0.10);
            $startingBuildings['smithy'] = floor($acresBase*0.10);
            $startingBuildings['lumberyard'] = floor($acresBase*0.06);
            $startingBuildings['forest_haven'] = floor($acresBase*0.06);
            $startingBuildings['ore_mine'] = floor($acresBase*0.10);
            $startingBuildings['gem_mine'] = floor($acresBase*0.10);
            $startingBuildings['barracks'] = floor($acresBase*0.20);
            $startingBuildings['tower'] = floor($acresBase*0.06);
            $startingBuildings['temple'] = floor($acresBase*0.06);
            $startingBuildings['dock'] = floor($acresBase*0.10);
        }

        return $startingBuildings;
    }

    /**
     * Get amount of total starting land a new Dominion starts with, factoring
     * in both buildings and barren land.
     *
     * @param Race $race
     * @param array $startingBarrenLand
     * @param array $startingBuildings
     * @return array
     */
    protected function getStartingLand(Race $race, array $startingBarrenLand, array $startingBuildings): array
    {
        $startingLand = [
            'plain' => $startingBarrenLand['plain'] + $startingBuildings['farm'] + $startingBuildings['smithy'] + $startingBuildings['residence'],
            'mountain' => $startingBarrenLand['mountain'] + $startingBuildings['ore_mine'] + $startingBuildings['gem_mine'],
            'swamp' => $startingBarrenLand['swamp'] + $startingBuildings['tower'] + $startingBuildings['wizard_guild'] + $startingBuildings['temple'] + $startingBuildings['tissue_swamp'],
            'cavern' => 0,
            'forest' => $startingBarrenLand['forest'] + $startingBuildings['lumberyard'] + $startingBuildings['forest_haven'] + $startingBuildings['mycelia'],
            'hill' => $startingBarrenLand['hill'] + $startingBuildings['barracks'],
            'water' => $startingBarrenLand['water'] + $startingBuildings['dock'],
        ];

        $startingLand[$race->home_land_type] += $startingBuildings['shed'];

        return $startingLand;
    }
}
