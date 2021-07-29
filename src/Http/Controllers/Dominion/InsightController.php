<?php

namespace OpenDominion\Http\Controllers\Dominion;

use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\RangeCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;
use OpenDominion\Calculators\Dominion\BuildingCalculator;
use OpenDominion\Calculators\Dominion\PopulationCalculator;
use OpenDominion\Calculators\Dominion\ProductionCalculator;
use OpenDominion\Calculators\NetworthCalculator;
use OpenDominion\Helpers\BuildingHelper;
use OpenDominion\Helpers\ImprovementHelper;
use OpenDominion\Helpers\LandHelper;
use OpenDominion\Helpers\SpellHelper;
use OpenDominion\Helpers\TechHelper;
use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Helpers\TitleHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Realm;
use OpenDominion\Services\Dominion\InfoOpService;
use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Services\Dominion\ProtectionService;
use OpenDominion\Services\Dominion\StatsService;
use OpenDominion\Services\GameEventService;

# ODA
use OpenDominion\Helpers\RaceHelper;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;
use OpenDominion\Calculators\Dominion\LandImprovementCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Models\Tech;
use OpenDominion\Helpers\DeityHelper;

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
                    # Is not in protection;
                    !$protectionService->isUnderProtection($dominion) and

                    # Is not locked;
                    $dominion->is_locked !== 1
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
        return view('pages.dominion.insight.show', [
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
            'improvementCalculator' => app(ImprovementCalculator::class),
            'militaryCalculator' => app(MilitaryCalculator::class),
            'dominion' => $dominion,
            'deityHelper' => app(DeityHelper::class),
            'networthCalculator' => app(NetworthCalculator::class),
            'statsService' => app(StatsService::class),
            'titleHelper' => app(TitleHelper::class),
            'populationCalculator' => app(PopulationCalculator::class),
            'buildingCalculator' => app(BuildingCalculator::class),
            'productionCalculator' => app(ProductionCalculator::class),
            'queueService' => app(QueueService::class),
        ]);
    }

    public function getDominionArchive(Dominion $dominion, string $type)
    {
        $resultsPerPage = 10;
        $valid_types = ['clear_sight', 'vision', 'revelation', 'barracks_spy', 'castle_spy', 'survey_dominion', 'land_spy'];
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

}
