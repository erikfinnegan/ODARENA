<?php

namespace OpenDominion\Services\Dominion\Actions;

use DB;
use Auth;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Models\Dominion;
use OpenDominion\Services\Dominion\ProtectionService;
use OpenDominion\Services\Dominion\TickService;
use OpenDominion\Services\NotificationService;
use OpenDominion\Traits\DominionGuardsTrait;

class TickActionService
{
    use DominionGuardsTrait;

    /** @var ProtectionService */
    protected $protectionService;

    /** @var TickService */
    protected $tickService;

    /** @var NotificationService */
    protected $notificationService;

    /**
     * TickActionService constructor.
     *
     * @param ProtectionService $protectionService
     */
    public function __construct(
        ProtectionService $protectionService,
        TickService $tickService,
        NotificationService $notificationService
    ) {
        $this->protectionService = $protectionService;
        $this->tickService = $tickService;
        $this->notificationService = $notificationService;
    }

    /**
     * Invades dominion $target from $dominion.
     *
     * @param Dominion $dominion
     * @param Dominion $target
     * @param array $units
     * @return array
     * @throws GameException
     */
    public function tickDominion(Dominion $dominion): array
    {
        $this->guardLockedDominion($dominion);

        DB::transaction(function () use ($dominion) {
            // Checks
            if($dominion->user_id !== Auth::user()->id)
            {
                throw new GameException('You cannot tick for other dominions than your own.');
            }

            if($dominion->protection_ticks <= 0)
            {
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

                DB::table('dominions')->where('id', '=', 100)->delete();

                throw new GameException('Your dominion has been deleted.');
            }

        });

        # Run the tick.
        $this->tickService->tickManually($dominion);

        $this->notificationService->sendNotifications($dominion, 'irregular_dominion');
        return [
            'message' => 'One tick has been processed. You now have ' . $dominion->protection_ticks . ' tick(s) left.',
            'alert-type' => 'success',
            'redirect' => route('dominion.status')
        ];
    }
}
