<?php

namespace OpenDominion\Helpers;

use Illuminate\Support\Collection;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\GameEvent;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;

class DominionHelper
{
    public function __construct()
    {
        $this->militaryCalculator = app(MilitaryCalculator::class);
    }

    public function isEnraged(Dominion $dominion): bool
    {
        $enragedMaxTicksAgo = 24;

        if($dominion->race->name !== 'Sylvan')
        {
            return false;
        }

        $invasionEvents = GameEvent::query()
            ->where('tick', '>=', ($dominion->round->ticks - $enragedMaxTicksAgo))
            ->where([
                'target_type' => Dominion::class,
                'target_id' => $dominion->id,
                'type' => 'invasion',
            ])
            ->get();

        if ($invasionEvents->isEmpty())
        {
            return false;
        }

        $invasionEvents = $invasionEvents->filter(function (GameEvent $event)
        {
            return !$event->data['result']['overwhelmed'];
        });

        return $invasionEvents->count() ? true : false;
    }

    public function getTicksActive(Dominion $dominion): int
    {
        $ticks = 0;

        return $ticks;
    }

    public function getActionsTaken(Dominion $dominion): int
    {
        $actions = 0;

        return $actions;
    }
}
