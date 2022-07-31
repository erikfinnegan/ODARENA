<?php

namespace OpenDominion\Http\Controllers\Dominion;

use OpenDominion\Http\Requests\Dominion\Actions\NotesActionRequest;
use OpenDominion\Services\Dominion\QuickstartService;

use OpenDominion\Helpers\DecreeHelper;

use OpenDominion\Models\Dominion;

class QuickstartController extends AbstractDominionController
{
    public function getQuickstart()
    {
        $dominion = $this->getSelectedDominion();
        $quickstartService = app(QuickstartService::class);

        return view('pages.dominion.quickstart', [
            'quickstart' => $quickstartService->generateQuickstartFile($dominion),
            'decreeHelper' => app(DecreeHelper::class),
        ]);
    }
}
