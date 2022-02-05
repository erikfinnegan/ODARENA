<?php

namespace OpenDominion\Http\Controllers;

use Illuminate\Http\Response;
use OpenDominion\Calculators\NetworthCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\Race;
use OpenDominion\Models\Realm;
use OpenDominion\Models\Round;
use OpenDominion\Models\User;

use OpenDominion\Helpers\RealmHelper;
use OpenDominion\Helpers\UserHelper;

class ChroniclesController extends AbstractController
{
    public function getIndex()
    {
        $rounds = Round::with('league')->where('end_date','<=',now())->orderBy('start_date', 'desc')->get();

        $users = User::orderBy('display_name')->get();

        return view('pages.chronicles.index', [
            'userHelper' => app(UserHelper::class),

            'rounds' => $rounds,
            'users' => $users,
        ]);
    }

    public function getRound(Round $round)
    {
        if ($response = $this->guardAgainstActiveRound($round)) {
            return $response;
        }

        $races = $round->dominions
            ->sortBy('race.name')
            ->pluck('race.name', 'race.id')
            ->unique();

        return view('pages.chronicles.round', [
            'round' => $round,
            'races' => $races,
        ]);
    }

    public function getRuler(string $userDisplayName)
    {
        $user = User::where('display_name', $userDisplayName)->first();

        if(!$user)
        {
            return redirect()->back()
                ->withErrors(['No such ruler found.']);
        }

        $userHelper = app(UserHelper::class);
        $dominions = $userHelper->getUserDominions($user);

        return view('pages.chronicles.ruler', [
            'landCalculator' => app(LandCalculator::class),
            'networthCalculator' => app(NetworthCalculator::class),
            'user' => $user,
            'userHelper' => $userHelper,
            'dominions' => $dominions
        ]);
    }

    // todo: search user

    /**
     * @param Round $round
     * @return Response|null
     */
    protected function guardAgainstActiveRound(Round $round)
    {
        if (!$round->hasEnded())
        {
            return redirect()->back()
                ->withErrors(['The chronicles for this round have not been finished yet. Come back after the round has ended.']);
        }

        return null;
    }

    protected function getDominionsByStatistic(Round $round, string $stat)
    {
        $builder = $round->dominions()
            ->with(['realm', 'race', 'user'])
            ->where($stat, '>', 0);

        return $builder->get()
            ->map(function (Dominion $dominion) use ($stat) {
                $data = [
                    '#' => null,
                    'dominion' => $dominion->name,
                    'player' => $dominion->isAbandoned() ? $dominion->ruler_name . ' (abandoned)' : $dominion->user->display_name,
                    'faction' => $dominion->race->name,
                    'realm' => $dominion->realm->number,
                    'value' => $dominion->{$stat},
                ];
                return $data;
            })
            ->sortByDesc(function ($row) {
                return $row['value'];
            })
            ->take(100)
            ->values()
            ->map(function ($row, $key) {
                $row['#'] = ($key + 1);
                $row['value'] = number_format($row['value']);
                return $row;
            });
    }
}
