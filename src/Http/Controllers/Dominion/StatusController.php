<?php

namespace OpenDominion\Http\Controllers\Dominion;

use OpenDominion\Http\Requests\Dominion\Actions\TickActionRequest;

use OpenDominion\Calculators\NetworthCalculator;
use OpenDominion\Calculators\Dominion\DominionCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\MoraleCalculator;
use OpenDominion\Calculators\Dominion\PopulationCalculator;
use OpenDominion\Calculators\Dominion\PrestigeCalculator;
use OpenDominion\Calculators\Dominion\ProductionCalculator;
use OpenDominion\Calculators\Dominion\ResourceCalculator;
use OpenDominion\Calculators\Dominion\TitleCalculator;

use OpenDominion\Services\Dominion\ProtectionService;
use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Services\Dominion\StatsService;
use OpenDominion\Services\Dominion\Actions\TickActionService;

use OpenDominion\Helpers\DominionHelper;
use OpenDominion\Helpers\RaceHelper;
use OpenDominion\Helpers\NotificationHelper;
use OpenDominion\Helpers\TitleHelper;
use OpenDominion\Helpers\UnitHelper;

class StatusController extends AbstractDominionController
{
    public function getStatus()
    {
        $resultsPerPage = 25;
        $selectedDominion = $this->getSelectedDominion();

        $notifications = $selectedDominion->notifications()->paginate($resultsPerPage);

        return view('pages.dominion.status', [
            'title' => 'STATUS',
            'dominionProtectionService' => app(ProtectionService::class),

            'dominionCalculator' => app(DominionCalculator::class),
            'landCalculator' => app(LandCalculator::class),
            'militaryCalculator' => app(MilitaryCalculator::class),
            'moraleCalculator' => app(MoraleCalculator::class),
            'networthCalculator' => app(NetworthCalculator::class),
            'notificationHelper' => app(NotificationHelper::class),
            'populationCalculator' => app(PopulationCalculator::class),
            'productionCalculator' => app(ProductionCalculator::class),
            'prestigeCalculator' => app(PrestigeCalculator::class),
            'resourceCalculator' => app(ResourceCalculator::class),
            'queueService' => app(QueueService::class),
            'titleCalculator' => app(TitleCalculator::class),

            'dominionHelper' => app(DominionHelper::class),
            'raceHelper' => app(RaceHelper::class),
            'titleHelper' => app(TitleHelper::class),
            'statsService' => app(StatsService::class),
            'unitHelper' => app(UnitHelper::class),
            'notifications' => $notifications
        ]);
    }

    public function postTick(TickActionRequest $request)
    {
        $ticks = intval($request->ticks);
        $ticks = max(min($ticks, 96), 0);
        $dominion = $this->getSelectedDominion();
        $tickActionService = app(TickActionService::class);

        try
        {
            for ($tick = 1; $tick <= $ticks; $tick++)
            {
                $result = $tickActionService->tickDominion($dominion);
                #usleep(rand(100000,100000)); # WTF was this?
            }
        }
        catch (GameException $e)
        {
            return redirect()->back()
                ->withInput($request->all())
                ->withErrors([$e->getMessage()]);
        }

        $request->session()->flash(('alert-' . ($result['alert-type'] ?? 'success')), $result['message']);
        return redirect()->to(route($request->returnTo));

    }

}
