<?php

namespace OpenDominion\Console\Commands\Game;

use Carbon\Carbon;
use Illuminate\Console\Command;
use OpenDominion\Console\Commands\CommandInterface;
use OpenDominion\Factories\RealmFactory;
use OpenDominion\Factories\RoundFactory;
use OpenDominion\Models\RoundLeague;
use OpenDominion\Helpers\RoundHelper;
use RuntimeException;

use OpenDominion\Services\Dominion\BarbarianService;

class RoundOpenCommand extends Command implements CommandInterface
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'game:round:open
                             {--gamemode : Round game mode)}
                             {--now : Start the round right now (dev & testing only)}
                             {--open : Start the round in +3 days midnight, allowing for immediate registration}
                             {--days= : Start the round in +DAYS days midnight, allowing for more fine-tuning}';

    /** @var string The console command description. */
    protected $description = 'Creates a new round which starts in 5 days';

    /** @var RealmFactory */
    protected $realmFactory;

    /** @var RoundFactory */
    protected $roundFactory;

    /** @var RoundHelper */
    protected $roundHelper;

    /** @var BarbarianService */
    protected $barbarianService;

    /**
     * RoundOpenCommand constructor.
     *
     * @param RoundFactory $roundFactory
     * @param RealmFactory $realmFactory
     */
    public function __construct(
        BarbarianService $barbarianService,
        RealmFactory $realmFactory,
        RoundFactory $roundFactory,
        RoundHelper $roundHelper
    ) {
        parent::__construct();

        $this->roundFactory = $roundFactory;
        $this->roundHelper = $roundHelper;
        $this->realmFactory = $realmFactory;
        $this->barbarianService = $barbarianService;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(): void
    {
        $now = $this->option('now');
        $open = $this->option('open');
        $days = $this->option('days');
        $league = $this->option('league');
        $gameMode = $this->option('gamemode');

        if ($now && (app()->environment() === 'production')) {
            throw new RuntimeException('Option --now may not be used on production');
        }

        if (($now && $open) || ($now && $days) || ($open && $days)) {
            throw new RuntimeException('Options --now, --open and --days are mutually exclusive');
        }

        if(!$gameMode or !in_array($gameMode, $this->roundHelper->getRoundGameModes()))
        {
            throw new RuntimeException('Invalid game mode, must be:', dump($this->roundHelper->getRoundGameModes()));
        }

        if ($now) {
            $startDate = 'now';

        } elseif ($open) {
            $startDate = '+3 days midnight';

        } elseif ($days !== null) {
            if (!ctype_digit($days)) {
                throw new RuntimeException('Option --days=DAYS must be an integer');
            }

            $startDate = "+{$days} days midnight";

        } else {
            $startDate = '+5 days midnight';
        }

        $startDate = new Carbon($startDate);

        /** @var RoundLeague $roundLeague */
        $roundLeague = RoundLeague::where('key', $league)->firstOrFail();

        $this->info("Starting a new {$gameMode} round");

        $round = $this->roundFactory->create(
            $roundLeague,
            $startDate,
            $gameMode
        );

        $this->info("Round {$round->number} created in Era {$roundLeague->key}. The round starts at {$round->start_date}.");

        // Prepopulate round with #1 Barbarian, #2 Commonwealth, #3 Empire, #4 Independent
        if($gameMode !== 'deathmatch')
        {
            $this->realmFactory->create($round, 'npc');
            $this->realmFactory->create($round, 'good');
            $this->realmFactory->create($round, 'evil');
            $this->realmFactory->create($round, 'independent');
        }
        elseif($gameMode == 'deathmatch' or $gameMode == 'deathmatch-duration')
        {
            $this->realmFactory->create($round, 'npc');
            $this->realmFactory->create($round, 'players');
        }

        // Create 18 Barbarians.
        for ($slot = 1; $slot <= 18; $slot++)
        {
            $this->barbarianService->createBarbarian($round);
        }

    }
}
