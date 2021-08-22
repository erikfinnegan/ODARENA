<?php

namespace OpenDominion\Services\Dominion;


use DB;
use Carbon\Carbon;
use Illuminate\Support\Collection;

use OpenDominion\Exceptions\GameException;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\DominionInsight;

use OpenDominion\Helpers\BuildingHelper;
use OpenDominion\Helpers\ImprovementHelper;
use OpenDominion\Helpers\LandHelper;
use OpenDominion\Helpers\TitleHelper;

use OpenDominion\Calculators\NetworthCalculator;
use OpenDominion\Calculators\Dominion\BuildingCalculator;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\LandImprovementCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\PopulationCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;

use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Services\Dominion\ProtectionService;
use OpenDominion\Services\Dominion\StatsService;


class InsightService
{

    public function __construct()
    {
        $this->buildingHelper = app(BuildingHelper::class);
        $this->improvementHelper = app(ImprovementHelper::class);
        $this->landHelper = app(LandHelper::class);
        $this->titleHelper = app(TitleHelper::class);

        $this->buildingCalculator = app(BuildingCalculator::class);
        $this->improvementCalculator = app(ImprovementCalculator::class);
        $this->landCalculator = app(LandCalculator::class);
        $this->landImprovementCalculator = app(LandImprovementCalculator::class);
        $this->militaryCalculator = app(MilitaryCalculator::class);
        $this->networthCalculator = app(NetworthCalculator::class);
        $this->populationCalculator = app(PopulationCalculator::class);
        $this->spellCalculator = app(SpellCalculator::class);

        $this->statsService = app(StatsService::class);
        $this->protectionService = app(ProtectionService::class);
        $this->queueService = app(QueueService::class);
    }

    # $target = the dominion for whom Insight is being captured
    # $source = the dominion (if any) which is capturing the Insight
    public function captureDominionInsight(Dominion $target, Dominion $source = null): array
    {

        if($this->protectionService->isUnderProtection($target))
        {
            throw new GameException('You cannot capture insight for a dominion that is under protection.');
        }

        if(!$target->round->hasStarted())
        {
            throw new GameException('You cannot capture insight before the round has started.');
        }

        if($target->getSpellPerkValue('fog_of_war'))
        {
            throw new GameException('This dominion is temporarily hidden from insight.');
        }

        $dominionInsight = new DominionInsight([
            'dominion_id' => $target->id,
            'source_dominion_id' => $source ? $source->id : null,
            'round_tick' => $target->round->ticks,
        ]);

        $data = [
            # CS: Overview
            'title' => $target->title->name,
            'title_perks' => $this->titleHelper->getRulerTitlePerksForDominion($target),
            'ruler' => $target->ruler_name,
            'race' => $target->race->name,
            'total_land' => $this->landCalculator->getTotalLand($target),
            'peasants' => $target->peasants,
            'employment' => $this->populationCalculator->getEmploymentPercentage($target),
            'networth' => $this->networthCalculator->getDominionNetworth($target),
            'prestige' => $target->prestige,
            'victories' => $this->statsService->getStat($target, 'invasion_victories'),
            'net_victories' => $this->militaryCalculator->getNetVictories($target),

            # CS: Resources
            'resource_gold'     => $target->resource_gold,
            'resource_food'     => $target->resource_food,
            'resource_lumber'   => $target->resource_lumber,
            'resource_mana'     => $target->resource_mana,
            'resource_ore'      => $target->resource_ore,
            'resource_gems'     => $target->resource_gems,
            'resource_tech'     => $target->resource_tech,
            'resource_champion' => $target->resource_champion,
            'resource_soul'     => $target->resource_soul,
            'resource_blood'    => $target->resource_blood,

            # CS: Military
            'morale' => $target->morale,
            'military_draftees' => $target->military_draftees,
            'military_unit1' => $this->militaryCalculator->getTotalUnitsForSlot($target, $target->race->units->get(0)->slot),
            'military_unit2' => $this->militaryCalculator->getTotalUnitsForSlot($target, $target->race->units->get(1)->slot),
            'military_unit3' => $this->militaryCalculator->getTotalUnitsForSlot($target, $target->race->units->get(2)->slot),
            'military_unit4' => $this->militaryCalculator->getTotalUnitsForSlot($target, $target->race->units->get(3)->slot),
            'military_spies' => $target->military_spies,
            'military_wizards' => $target->military_wizards,
            'military_archmages' => $target->military_archmages,

            # Deity
            'deity' => $target->hasDeity() ? $target->getDeity()->name : NULL,
            'deity_devotion' => $target->hasDeity() ? $target->getDominionDeity()->duration : NULL,
            'deity_perk' => NULL,

            # Annexation
            'is_annexed' => $this->spellCalculator->isAnnexed($target),
            'annexed_military_power_provided' => $this->militaryCalculator->getRawMilitaryPowerFromAnnexedDominion($target),
            'has_annexed' => $this->spellCalculator->hasAnnexedDominions($target),
            'military_power_from_annexed_dominions' => $this->militaryCalculator->getRawMilitaryPowerFromAnnexedDominions($target),
        ];

        # Units
        $data['units'] = [];
        $data['units']['training'] =
            [
                'unit1' => array_fill(1, 12, 0),
                'unit2' => array_fill(1, 12, 0),
                'unit3' => array_fill(1, 12, 0),
                'unit4' => array_fill(1, 12, 0),
                'spies' => array_fill(1, 12, 0),
                'wizards' => array_fill(1, 12, 0),
                'archmages' => array_fill(1, 12, 0),
            ];

        $data['units']['returning'] =
            [
                'unit1' => array_fill(1, 12, 0),
                'unit2' => array_fill(1, 12, 0),
                'unit3' => array_fill(1, 12, 0),
                'unit4' => array_fill(1, 12, 0),
                'spies' => array_fill(1, 12, 0),
                'wizards' => array_fill(1, 12, 0),
                'archmages' => array_fill(1, 12, 0),
                'draftees' => array_fill(1, 12, 0),
            ];

        $data['units']['home'] =
            [
                'draftees' => $target->military_draftees,
                'spies' => $target->military_spies,
                'wizards' => $target->military_wizards,
                'archmages' => $target->military_archmages,
                'unit1' => $target->military_unit1,
                'unit2' => $target->military_unit2,
                'unit3' => $target->military_unit3,
                'unit4' => $target->military_unit4,
          ];

        // Units returning
        $this->queueService->getInvasionQueue($target)->each(static function ($row) use (&$data)
        {
            if (starts_with($row->resource, 'military_')) {
                $unitType = str_replace('military_', '', $row->resource);
                $data['units']['returning'][$unitType][$row->hours] += $row->amount;
            }
        });

        // Units training
        $this->queueService->getTrainingQueue($target)->each(static function ($row) use (&$data)
        {
            if (starts_with($row->resource, 'military_'))
            {
                $unitType = str_replace('military_', '', $row->resource);
                $data['units']['training'][$unitType][$row->hours] += $row->amount;
            }
        });

        # Spells
        $data['spells'] = [];

        foreach($this->spellCalculator->getActiveSpells($target) as $dominionSpell)
        {
            $data['spells'][$dominionSpell->spell->key]['duration'] = $dominionSpell->spell->duration;
            $data['spells'][$dominionSpell->spell->key]['remaining'] = $dominionSpell->duration;
            $data['spells'][$dominionSpell->spell->key]['caster_name'] = Dominion::findOrFail($dominionSpell->caster_id)->name;
            $data['spells'][$dominionSpell->spell->key]['caster_realm'] = Dominion::findOrFail($dominionSpell->caster_id)->realm->number;
        }

        # Buildings
        $data['buildings'] = [];
        $data['buildings']['constructed'] = [];
        $data['buildings']['constructing'] = [];

        foreach ($this->buildingHelper->getBuildingsByRace($target->race) as $building)
        {
            $data['buildings']['constructed'][$building->key] = $this->buildingCalculator->getBuildingAmountOwned($target, $building);
            $data['buildings']['constructing'][$building->key] = array_fill(1, 12, 0);
        }

        $totalConstructingLand = 0;

        $this->queueService->getConstructionQueue($target)->each(static function ($row) use (&$data, &$totalConstructingLand) {
            $buildingKey = str_replace('building_', '', $row->resource);
            $data['buildings']['constructing'][$buildingKey][$row->hours] += $row->amount;
            $totalConstructingLand += (int)$row->amount;
        });

        $this->queueService->getSabotageQueue($target)->each(static function ($row) use (&$data, &$totalConstructingLand) {
            $buildingKey = str_replace('building_', '', $row->resource);
            $data['buildings']['constructing'][$buildingKey][$row->hours] += $row->amount;
            $totalConstructingLand += (int)$row->amount;
        });

        $data['barren_land'] = $this->landCalculator->getTotalBarrenLand($target);
        $data['constructing_land'] = $totalConstructingLand;

        # Improvemens
        $data['improvements'] = [];

        foreach($this->improvementHelper->getImprovementsByRace($target->race) as $improvement)
        {
            foreach($improvement->perks as $perk)
            {
                  $data['improvements'][$improvement->key]['points'] = $this->improvementCalculator->getDominionImprovementAmountInvested($target, $improvement);
                  $data['improvements'][$perk->key]['rating'] = $target->getImprovementPerkMultiplier($perk->key);
            }
        }

        # Advancements
        $advancements = [];
        $techs = $target->techs->sortBy('key');
        $techs = $techs->sortBy(function ($tech, $key)
        {
            return $tech['name'] . str_pad($tech['level'], 2, '0', STR_PAD_LEFT);
        });

        foreach($techs as $tech)
        {
            $advancement = $tech['name'];
            $key = $tech['key'];
            $level = (int)$tech['level'];
            $advancements[$advancement] = [
                'key' => $key,
                'name' => $advancement,
                'level' => (int)$level,
                ];
        }

        $data['advancements'] = $advancements;

        # Land
        $data['land'] = [];

        foreach ($this->landHelper->getLandTypes() as $landType)
        {
            $amount = $target->{'land_' . $landType};
            $data['land'][$landType]['amount'] = $amount;
            $data['land'][$landType]['percentage'] = ($amount / $this->landCalculator->getTotalLand($target)) * 100;
            $data['land'][$landType]['barren'] = $this->landCalculator->getTotalBarrenLandByLandType($target, $landType);
            $data['land'][$landType]['landtype_defense'] = $this->militaryCalculator->getDefensivePowerModifierFromLandType($target, $landType);

            $data['land']['incoming'][$landType] = array_fill(1, 12, 0);
        }

        if($target->race->getPerkValue('land_improvements'))
        {
            $data['land']['land_improvements']['plain'] = $this->landImprovementCalculator->getOffensivePowerBonus($target);
            $data['land']['land_improvements']['mountain'] = $this->landImprovementCalculator->getGoldProductionBonus($target);
            $data['land']['land_improvements']['swamp'] = $this->landImprovementCalculator->getWizardPowerBonus($target);
            $data['land']['land_improvements']['forest'] = $this->landImprovementCalculator->getPopulationBonus($target);
            $data['land']['land_improvements']['hill'] = $this->landImprovementCalculator->getDefensivePowerBonus($target);
            $data['land']['land_improvements']['water'] = $this->landImprovementCalculator->getFoodProductionBonus($target);
        }

        $this->queueService->getExplorationQueue($target)->each(static function ($row) use (&$data)
        {
            if (starts_with($row->resource, 'land_'))
            {
                $landType = str_replace('land_', '', $row->resource);
                $data['land']['incoming'][$landType][$row->hours] += $row->amount;
            }
        });

        $this->queueService->getInvasionQueue($target)->each(static function ($row) use (&$data)
        {
            if (starts_with($row->resource, 'land_'))
            {
                $landType = str_replace('land_', '', $row->resource);
                $data['land']['incoming'][$landType][$row->hours] += $row->amount;
            }
        });

        $data = json_encode($data);

        $dominionInsight->data = $data;

        $dominionInsight->save();

        return [
            'alert-type' => 'success',
            'redirect' => route('dominion.insight.archive', $target),
            'message' => 'Insight successfully archived.',
        ];
    }


    public function getDominionInsight(Dominion $dominion, Dominion $source): Collection
    {
        return DominionInsight::where('dominion_id', $dominion->id)->get();
    }
}
