<?php

namespace OpenDominion\Console\Commands\Game;

use Illuminate\Console\Command;
use OpenDominion\Console\Commands\CommandInterface;
use OpenDominion\Services\Dominion\TickService;
use OpenDominion\Models\Round;

class PrecalculateCommand extends Command implements CommandInterface
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'game:precalculate';

    /** @var string The console command description. */
    protected $description = 'Precalculate the tick';

    /** @var TickService */
    protected $tickService;

    /**
     * GameTickCommand constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->tickService = app(TickService::class);
    }

    /**
     * {@inheritdoc}
     */
#    public function handle(): void
#    {
#        $this->tickService->tickHourly();
#
#        if (now()->hour === 0) {
#            $this->tickService->tickDaily();
#        }
#    }

    public function handle(): void
    {
        $activeRounds = Round::active()->get();
        foreach ($activeRounds as $round)
        {
            $dominions = $round->activeDominions()->get();
            foreach($dominions as $dominion)
            {
                $this->info("[Round {$round->number}, Tick {$round->ticks}] Precalculating {$dominion->name}");
                $this->tickService->precalculateTick($dominion);
            }
        }


    }


}
