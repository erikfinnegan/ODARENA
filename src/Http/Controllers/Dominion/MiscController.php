<?php

namespace OpenDominion\Http\Controllers\Dominion;

use LogicException;

# ODA
use DB;
use Auth;
use Log;
use OpenDominion\Services\Dominion\ProtectionService;

// misc functions, probably could use a refactor later
class MiscController extends AbstractDominionController
{
    public function postClearNotifications()
    {
        $this->getSelectedDominion()->notifications->markAsRead();
        return redirect()->back();
    }

    public function postClosePack()
    {
        $dominion = $this->getSelectedDominion();
        $pack = $dominion->pack;

        // Only pack creator can manually close it
        if ($pack->creator_dominion_id !== $dominion->id) {
            throw new LogicException('Pack may only be closed by the creator');
        }

        $pack->closed_at = now();
        $pack->save();

        return redirect()->back();
    }

    public function postDeleteDominion()
    {

        /*
        *   Conditions for allowing deleting:
        *   - The dominion belongs to the logged in user.
        *   - If the round has started, only allow deleting if protection ticks > 0.
        *   - If the round hasn't started, always allow.
        */

        $dominion = $this->getSelectedDominion();

        # Can only delete your own dominion.
        if($dominion->user_id !== Auth::user()->id)
        {
            throw new GameException('You cannot delete other dominions than your own.');
        }

        # If the round has started, can only delete if protection ticks > 0.
        if($dominion->round->hasStarted() and $dominion->protection_ticks <= 0 and request()->getHost() !== 'sim.odarena.com')
        {
            throw new GameException('You cannot delete your dominion because the round has already started.');
        }

        $result = [];
        # Destroy the dominion.
        DB::table('active_spells')->where('dominion_id', '=', $dominion->id)->delete();
        DB::table('active_spells')->where('cast_by_dominion_id', '=', $dominion->id)->delete();

        DB::table('council_posts')->where('dominion_id', '=', $dominion->id)->delete();
        DB::table('council_threads')->where('dominion_id', '=', $dominion->id)->delete();
        DB::table('daily_rankings')->where('dominion_id', '=', $dominion->id)->delete();
        DB::table('dominion_history')->where('dominion_id', '=', $dominion->id)->delete();
        DB::table('dominion_queue')->where('dominion_id', '=', $dominion->id)->delete();
        DB::table('dominion_techs')->where('dominion_id', '=', $dominion->id)->delete();
        DB::table('dominion_tick')->where('dominion_id', '=', $dominion->id)->delete();

        DB::table('game_events')->where('source_id', '=', $dominion->id)->delete();
        DB::table('game_events')->where('target_id', '=', $dominion->id)->delete();

        DB::table('info_ops')->where('source_dominion_id', '=', $dominion->id)->delete();
        DB::table('info_ops')->where('target_dominion_id', '=', $dominion->id)->delete();

        DB::table('dominions')->where('id', '=', $dominion->id)->delete();


        Log::info(sprintf(
            'The dominion %s (ID %s) was deleted by user %s (ID %s).',
            $dominion->name,
            $dominion->id,
            Auth::user()->display_name,
            Auth::user()->id
        ));

        return redirect()
            ->to(route('dashboard')))
            ->with(
                'alert-success',
                'Your dominion has been destroyed.'
            );
    }


}
