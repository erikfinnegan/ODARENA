<?php

namespace OpenDominion\Http\Controllers\Dominion;

use Illuminate\Database\Eloquent\Builder;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;

use OpenDominion\Helpers\EventHelper;
use OpenDominion\Helpers\LandHelper;
use OpenDominion\Helpers\RaceHelper;
use OpenDominion\Helpers\SorceryHelper;
use OpenDominion\Helpers\UnitHelper;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\GameEvent;
use OpenDominion\Models\Realm;

class EventController extends AbstractDominionController
{
    public function index(string $eventUuid)
    {
        $viewer = $this->getSelectedDominion();
        $eventHelper = app(EventHelper::class);

        $query = GameEvent::query()
            ->with([
                'source',
                'source.race',
                'source.race.units',
                'source.race.units.perks',
                'source.realm',
                'target',
                'target.race',
                'target.race.units',
                'target.race.units.perks',
                'target.realm',
            ])
            ->where('id', $eventUuid);

        $event = $query->firstOrFail();

        if(!$eventHelper->canViewEvent($event, $viewer))
        {
            return redirect()->back()
                ->withErrors(['You cannot view this event.']);

            abort(403);
        }

        return view("pages.dominion.event.{$event->type}", [
            'event' => $event, // todo: compact()
            'unitHelper' => app(UnitHelper::class), // todo: only load if event->type == 'invasion'
            'militaryCalculator' => app(MilitaryCalculator::class), // todo: same thing here
            'landHelper' => app(LandHelper::class), // todo: same thing here
            'raceHelper' => app(RaceHelper::class), // todo: same thing here
            'sorceryHelper' => app(SorceryHelper::class), // todo: same thing here
            'canViewSource' => $eventHelper->canViewEventDetails($event, $viewer, 'source'),
            'canViewTarget' => $eventHelper->canViewEventDetails($event, $viewer, 'target'),
        ]);
    }


}
