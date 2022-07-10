<?php

namespace OpenDominion\Helpers;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\GameEvent;

class EventHelper
{

    /*
    public function __construct()
    {
        $this->militaryCalculator = app(MilitaryCalculator::class);
    }
    */

    public function canViewEvent(GameEvent $event, Dominion $dominion): bool
    {
        if($dominion->user->isStaff()) {
            return true;
        }

        if($event->source_type === Dominion::class && $event->source->realm_id == $dominion->realm->id) {
            return true;
        }

        if($event->target_type === Dominion::class && $event->target->realm_id == $dominion->realm->id) {
            return true;
        }

        if($event->source_type === Realm::class && $event->source->id == $dominion->realm->id) {
            return true;
        }

        if($event->target_type === Realm::class && $event->target->id == $dominion->realm->id) {
            return true;
        }

        return false;
    }

    # Whether to be able to view event details
    public function canViewEventDetails(GameEvent $event, Dominion $viewer, string $scope): bool
    {
        if($event->{$scope . '_type'} === Dominion::class and ($event->{$scope}->realm_id == $viewer->realm->id))
        {
            return true;
        }

        if($event->{$scope . '_type'} === Realm::class and ($event->{$scope}->id == $viewer->realm->id))
        {
            return true;
        }

        return false;
    }
}
