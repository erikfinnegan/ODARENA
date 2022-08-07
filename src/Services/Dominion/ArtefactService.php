<?php

namespace OpenDominion\Services\Dominion;

use DB;
use Carbon\Carbon;
use OpenDominion\Models\Artefact;
use OpenDominion\Models\Realm;
use OpenDominion\Models\RealmArtefact;
use OpenDominion\Models\Round;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Calculators\Dominion\ArtefactCalculator;
use OpenDominion\Helpers\ArtefactHelper;
use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Models\GameEvent;

class ArtefactService
{
    public function __construct()
    {
        $this->artefactHelper = app(ArtefactHelper::class);
        $this->artefactCalculator = app(ArtefactCalculator::class);
    }

    public function addArtefactToRealm(Realm $realm, Artefact $artefact): void
    {
        $power = $this->artefactCalculator->getNewPower($realm, $artefact);

        DB::transaction(function () use ($realm, $artefact, $power)
        {
            RealmArtefact::create([
                'realm_id' => $realm->id,
                'artefact_id' => $artefact->id,
                'power' => $power
            ]);
        });
    }

    public function removeArtefactFromRealm(Realm $realm, Artefact $artefact): void
    {
        DB::transaction(function () use ($realm, $artefact)
        {
            RealmArtefact::where('realm_id', $realm->id)
                ->where('artefact_id', $artefact->id)
                ->delete();
        });
    }

    public function getAvailableArtefacts(Round $round)
    {
        $artefacts = Artefact::where('enabled',1)->get();
        $realmArtefacts = RealmArtefact::join('realms', 'realms.id', '=', 'realm_artefacts.realm_id')
                            ->join('rounds','rounds.id', '=', 'realms.round_id')->where('rounds.id',$round->id)->get();

        # Check if artefact is already in use in realmArtefacts
        foreach($artefacts as $key => $artefact)
        {
            if($realmArtefacts->contains($artefact))#where('artefact_id',$artefact->id)->count() > 0)
            {
                $artefacts->forget($key);
            }
        }
        
        return $artefacts;
    }

    public function getRandomArtefact(Round $round): Artefact
    {
        $artefacts = $this->getAvailableArtefacts($round);
        $artefact = $artefacts->random();
        return $artefact;
    }

    public function moveArtefactFromRealmToRealm(Realm $fromRealm, Realm $toRealm, Artefact $artefact): void
    {
        $this->removeArtefactFromRealm($fromRealm, $artefact);
        $this->addArtefactToRealm($toRealm, $artefact);
    }

    public function updateRealmArtefactPower(Realm $realm, Artefact $artefact, int $powerChange): void
    {
        if($amount >= 0)
        {
            DB::transaction(function () use ($dominion, $improvement, $amount)
            {
                RealmArtefact::where('realm_id', $realm->id)->where('artefact_id', $artefact->id)
                ->increment('power', $powerChange);
            });
        }
        else
        {
            DB::transaction(function () use ($dominion, $improvement, $amount)
            {
                RealmArtefact::where('realm_id', $realm->id)->where('artefact_id', $artefact->id)
                ->decrement('power', $powerChange);
            });
        }
    }

}
