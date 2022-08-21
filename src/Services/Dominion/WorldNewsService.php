<?php

namespace OpenDominion\Services\Dominion;

use DB;
use Auth;
use Illuminate\Support\Collection;
use OpenDominion\Helpers\WorldNewsHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Realm;

class WorldNewsService
{
    /** @var array */
    protected $activeDeitys = [];

    public function __construct()
    {
        $this->worldNewsHelper = app(WorldNewsHelper::class);
    }

    public function getWorldNewsForDominion(Dominion $dominion, string $worldNewsScope = 'default', $maxTicksAgo = 192): Collection
    {
        $user = Auth::user();
        if($user->id !== $dominion->user->id)
        {
            dd('Eh, you can\'t see this');
        }

        $events = $dominion->round->gameEvents()
                                    ->where('tick', '>=', ($dominion->round->ticks - $maxTicksAgo))
                                    ->orderBy('created_at', 'desc')
                                    ->get();

        foreach($events as $index => $event)
        {
            foreach($user->settings['world_news'] as $eventScopeKey => $view)
            {
                $scope = explode('.',$eventScopeKey)[0];
                $eventKey = explode('.',$eventScopeKey)[1];

                

                dd($scope, $eventKey, $view);
            }
        }

        $events = $events->filter(function($event) {
            return $event->event_key != 'round_countdown' and $event->event_key != 'round_countdown_duration';
        });

        return $events;
    }

    private function getScope($dominion, $event)
    {
        return 'own';
    }
}
