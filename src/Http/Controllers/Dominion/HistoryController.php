<?php

namespace OpenDominion\Http\Controllers\Dominion;

use DB;
use OpenDominion\Helpers\HistoryHelper;

class HistoryController extends AbstractDominionController
{
    public function getHistory()
    {
        $resultsPerPage = 25;
        $selectedDominion = $this->getSelectedDominion();

        $history = DB::table('dominion_history')
                            ->where('dominion_history.dominion_id', '=', $selectedDominion->id)
                            ->orderBy('dominion_history.created_at', 'desc')
                            ->get();

        return view('pages.dominion.history', [
            'historyHelper' => app(HistoryHelper::class),
            'history' => $history
        ]);
    }

}
