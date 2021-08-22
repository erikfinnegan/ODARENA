<?php

namespace OpenDominion\Http\Controllers\Dominion;

use OpenDominion\Exceptions\GameException;

use OpenDominion\Http\Requests\Dominion\Actions\InsightActionRequest;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\DominionInsight;

use OpenDominion\Calculators\NetworthCalculator;
use OpenDominion\Calculators\Dominion\BuildingCalculator;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\LandImprovementCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\PopulationCalculator;
use OpenDominion\Calculators\Dominion\ProductionCalculator;
use OpenDominion\Calculators\Dominion\RangeCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;

use OpenDominion\Helpers\BuildingHelper;
use OpenDominion\Helpers\DeityHelper;
use OpenDominion\Helpers\ImprovementHelper;
use OpenDominion\Helpers\LandHelper;
use OpenDominion\Helpers\RaceHelper;
use OpenDominion\Helpers\SpellHelper;
use OpenDominion\Helpers\TechHelper;
use OpenDominion\Helpers\TitleHelper;
use OpenDominion\Helpers\UnitHelper;

#use OpenDominion\Services\GameEventService;
use OpenDominion\Services\Dominion\InsightService;
use OpenDominion\Services\Dominion\ProtectionService;
use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Services\Dominion\StatsService;

class InsightController extends AbstractDominionController
{
    public function getIndex()
    {
        $protectionService = app(ProtectionService::class);
        $landCalculator = app(LandCalculator::class);
        $self = $this->getSelectedDominion();

        $dominions = $self->round->activeDominions()
            ->with(['realm', 'round'])
            ->get()
            ->filter(function ($dominion) use ($self, $protectionService) {
                return (
                    # Is not in protection
                    !$protectionService->isUnderProtection($dominion) and

                    # Round has started
                    $dominion->round->hasStarted() and

                    # Does not have fog of war
                    !$dominion->getSpellPerkValue('fog_of_war')
                );
            })
            ->sortByDesc(function ($dominion) use($landCalculator) {
                return $landCalculator->getTotalLand($dominion);
            })
            ->values();


        return view('pages.dominion.insight.index', [
            'dominions' => $dominions,
            'landCalculator' => $landCalculator,
            'protectionService' => $protectionService,
            'networthCalculator' => app(NetworthCalculator::class),
            'spellCalculator' => app(SpellCalculator::class),
            'militaryCalculator' => app(MilitaryCalculator::class),
        ]);
    }

    public function getDominion(Dominion $dominion)
    {
        $advancements = [];
        $techs = $dominion->techs->sortBy('key');
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

        return view('pages.dominion.insight.show', [
            'advancements' => $advancements,
            'dominion' => $dominion,

            'buildingHelper' => app(BuildingHelper::class),
            'deityHelper' => app(DeityHelper::class),
            'improvementHelper' => app(ImprovementHelper::class),
            'landHelper' => app(LandHelper::class),
            'raceHelper' => app(RaceHelper::class),
            'spellHelper' => app(SpellHelper::class),
            'techHelper' => app(TechHelper::class),
            'titleHelper' => app(TitleHelper::class),
            'unitHelper' => app(UnitHelper::class),

            'buildingCalculator' => app(BuildingCalculator::class),
            'networthCalculator' => app(NetworthCalculator::class),
            'improvementCalculator' => app(ImprovementCalculator::class),
            'landImprovementCalculator' => app(LandImprovementCalculator::class),
            'landCalculator' => app(LandCalculator::class),
            'militaryCalculator' => app(MilitaryCalculator::class),
            'populationCalculator' => app(PopulationCalculator::class),
            'productionCalculator' => app(ProductionCalculator::class),
            'rangeCalculator' => app(RangeCalculator::class),
            'spellCalculator' => app(SpellCalculator::class),

            'protectionService' => app(ProtectionService::class),
            'statsService' => app(StatsService::class),
            'queueService' => app(QueueService::class),
        ]);
    }
    /*
    public function getDominionArchive(Dominion $dominion, string $type)
    {
        $resultsPerPage = 10;
        $valid_types = [];#['clear_sight', 'vision', 'revelation', 'barracks_spy', 'castle_spy', 'survey_dominion', 'land_spy'];
        $infoOpService = app(InfoOpService::class);

        if (!in_array($type, $valid_types)) {
            return redirect()->route('dominion.insight.show', $dominion);
        }

        $infoOpArchive = $this->getSelectedDominion()->realm
            ->infoOps()
            ->with('sourceDominion')
            ->where('target_dominion_id', '=', $dominion->id)
            ->where('type', '=', $type)
            ->orderBy('created_at', 'desc')
            ->paginate($resultsPerPage);

        return view('pages.dominion.insight.archive', [
            'buildingHelper' => app(BuildingHelper::class),
            'improvementHelper' => app(ImprovementHelper::class),
            'infoOpService' => app(InfoOpService::class),
            'landCalculator' => app(LandCalculator::class),
            'landHelper' => app(LandHelper::class),
            'rangeCalculator' => app(RangeCalculator::class),
            'spellCalculator' => app(SpellCalculator::class),
            'spellHelper' => app(SpellHelper::class),
            'techHelper' => app(TechHelper::class),
            'unitHelper' => app(UnitHelper::class),
            'raceHelper' => app(RaceHelper::class),
            'landImprovementCalculator' => app(LandImprovementCalculator::class),
            'militaryCalculator' => app(MilitaryCalculator::class),
            'dominion' => $dominion,
            'deityHelper' => app(DeityHelper::class),
            'infoOpArchive' => $infoOpArchive
        ]);
    }
    */

    public function postCaptureDominionInsight(InsightActionRequest $request)
    {
        $insightService = app(InsightService::class);

        $target = Dominion::findOrFail($request->get('target_dominion_id'));
        $roundTick = (int)$request->get('round_tick');
        $source = $this->getSelectedDominion();

        $insightService->captureDominionInsight($target, $source);

        return redirect()->route('dominion.insight.archive', $target);

        /*
        return view('pages.dominion.insight.show', [
            'dominion' => $target,
        ]);
        */
    }

    public function getDominionInsightArchive(Dominion $dominion)
    {
        $insightService = app(InsightService::class);
        $target = $dominion;
        $source = $this->getSelectedDominion();

        #$dominionInsight = $insightService->getDominionInsight($target, $source);

        $dominionInsights = DominionInsight::where('dominion_id', $target->id)->orderBy('created_at','desc')->paginate(1);

        return view('pages.dominion.insight.archive', [
            'dominion' => $target,
            'dominionInsights' => $dominionInsights,

            'buildingHelper' => app(BuildingHelper::class),
            'deityHelper' => app(DeityHelper::class),
            'improvementHelper' => app(ImprovementHelper::class),
            'landHelper' => app(LandHelper::class),
            'raceHelper' => app(RaceHelper::class),
            'spellHelper' => app(SpellHelper::class),
            'techHelper' => app(TechHelper::class),
            'titleHelper' => app(TitleHelper::class),
            'unitHelper' => app(UnitHelper::class),

            'buildingCalculator' => app(BuildingCalculator::class),
            'networthCalculator' => app(NetworthCalculator::class),
            'improvementCalculator' => app(ImprovementCalculator::class),
            'landImprovementCalculator' => app(LandImprovementCalculator::class),
            'landCalculator' => app(LandCalculator::class),
            'militaryCalculator' => app(MilitaryCalculator::class),
            'populationCalculator' => app(PopulationCalculator::class),
            'productionCalculator' => app(ProductionCalculator::class),
            'rangeCalculator' => app(RangeCalculator::class),
            'spellCalculator' => app(SpellCalculator::class),

            'protectionService' => app(ProtectionService::class),
            'statsService' => app(StatsService::class),
            'queueService' => app(QueueService::class),
        ]);
    }





}
