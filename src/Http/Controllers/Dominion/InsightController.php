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
use OpenDominion\Calculators\Dominion\ResourceCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;

use OpenDominion\Helpers\BuildingHelper;
use OpenDominion\Helpers\DeityHelper;
use OpenDominion\Helpers\ImprovementHelper;
use OpenDominion\Helpers\InsightHelper;
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
            'insightHelper' => app(InsightHelper::class),
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
            'resourceCalculator' => app(ResourceCalculator::class),
            'spellCalculator' => app(SpellCalculator::class),

            'protectionService' => app(ProtectionService::class),
            'statsService' => app(StatsService::class),
            'queueService' => app(QueueService::class),
        ]);
    }

    public function postCaptureDominionInsight(InsightActionRequest $request)
    {
        $insightService = app(InsightService::class);
        $protectionService = app(ProtectionService::class);

        $target = Dominion::findOrFail($request->get('target_dominion_id'));
        $source = $this->getSelectedDominion();

        try
        {
            $result = $insightService->captureDominionInsight($target, $source);
        }
        catch (GameException $e)
        {
            return redirect()->back()
                ->withInput($request->all())
                ->withErrors([$e->getMessage()]);
        }

        $request->session()->flash(('alert-' . ($result['alert-type'] ?? 'success')), $result['message']);
        return redirect()->route('dominion.insight.archive', $target);

    }

    public function getDominionInsightArchive(Dominion $dominion)
    {
        $insightService = app(InsightService::class);
        $target = $dominion;
        $source = $this->getSelectedDominion();

        #$dominionInsight = $insightService->getDominionInsight($target, $source);

        $dominionInsights = DominionInsight::where('dominion_id',$dominion->id)->where(function($query) use($source)
        {
            $query->where('source_realm_id', $source->realm->id)
                  ->orWhere('source_realm_id', NULL);
        })
        ->orderBy('created_at','desc')
        ->paginate(1);

        return view('pages.dominion.insight.archive', [
            'dominion' => $target,
            'dominionInsights' => $dominionInsights,

            'buildingHelper' => app(BuildingHelper::class),
            'deityHelper' => app(DeityHelper::class),
            'improvementHelper' => app(ImprovementHelper::class),
            'insightHelper' => app(InsightHelper::class),
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
            'resourceCalculator' => app(ResourceCalculator::class),
            'spellCalculator' => app(SpellCalculator::class),

            'protectionService' => app(ProtectionService::class),
            'statsService' => app(StatsService::class),
            'queueService' => app(QueueService::class),
        ]);
    }





}
