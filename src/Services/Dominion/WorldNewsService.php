<?php

namespace OpenDominion\Services\Dominion;

use DB;
use Auth;
use Illuminate\Support\Collection;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\GameEvent;
use OpenDominion\Models\Realm;

use OpenDominion\Helpers\WorldNewsHelper;

class WorldNewsService
{
    /** @var array */
    protected $activeDeitys = [];

    public function __construct()
    {
        $this->roundService = app(RoundService::class);
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
                $settingScope = explode('.',$eventScopeKey)[0];
                $settingEventKey = explode('.',$eventScopeKey)[1];

                if($this->getDominionScopeRelation($dominion, $event) == $settingScope and $event->type == $settingEventKey and !$view)
                {
                    $events->forget($index);
                }
            }
        }

        return $events;
    }

    public function getWorldNewsForRealm(Realm $realm, string $worldNewsScope = 'default', $maxTicksAgo = 192): Collection
    {
        $user = Auth::user();

        $events = $realm->round->gameEvents()
                                    ->where('tick', '>=', ($realm->round->ticks - $maxTicksAgo))
                                    ->orderBy('created_at', 'desc')
                                    ->get();

        foreach($events as $index => $event)
        {
            foreach($user->settings['world_news'] as $eventScopeKey => $view)
            {
                $settingScope = explode('.',$eventScopeKey)[0];
                $settingEventKey = explode('.',$eventScopeKey)[1];

                if($this->getRealmScopeRelation($user, $event) == $settingScope and $event->type == $settingEventKey and !$view)
                {
                    $events->forget($index);
                }
            }
        }

        return $events;
    }

    private function getDominionScopeRelation(Dominion $dominion, GameEvent $event)
    {
        if(
            $event->source_type == Dominion::class and $event->source_id == $dominion->id or
            $event->target_type == Dominion::class and $event->target_id == $dominion->id or
            $event->target_type == Realm::class and $event->target_id == $dominion->realm->id or
            $event->target_type == Realm::class and $event->target_id == $dominion->realm->id
        )
        {
            return 'own';
        }
        else
        {
            return 'other';
        }
    }

    private function getRealmScopeRelation(User $user, GameEvent $event)
    {
        $dominion = $this->roundService->getUserDominionFromRound($round);
        
        if(
            $event->source_type == Dominion::class and Dominion::findOrFail($event->source_id)->realm->id == $realm->id or
            $event->target_type == Dominion::class and Dominion::findOrFail($event->target_id)->realm->id == $realm->id or
            $event->target_type == Realm::class and $event->target_id == $realm->id or
            $event->target_type == Realm::class and $event->target_id == $realm->id
        )
        {
            return 'own';
        }
        else
        {
            return 'other';
        }
    }
}
