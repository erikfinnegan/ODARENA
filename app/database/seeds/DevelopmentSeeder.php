<?php

use Illuminate\Database\Seeder;
use OpenDominion\Factories\DominionFactory;
use OpenDominion\Factories\RealmFactory;
use OpenDominion\Factories\RoundFactory;
use OpenDominion\Helpers\BarbarianHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\Race;
use OpenDominion\Models\Round;
use OpenDominion\Models\RoundLeague;
use OpenDominion\Models\User;

class DevelopmentSeeder extends Seeder
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'dev:seed';
 
    public function run(): void
    {
        DB::transaction(function () {
            $this->createBarbarianUsers();
            $this->createTestUsers();
            $this->createRoundLeague();
        });
    }

    protected function createBarbarianUsers(): void
    {
        $barbarianHelper = app(RoundFactory::class);
        $barbarianNames = $barbarianHelper->getBarbarianReservedNames();
        $i = 1;

        foreach($barbarianNames as $barbarianName)
        {
            User::create([
                'email' => 'barbarian' . $i . '@odarena.local',
                'password' => bcrypt('test1234'),
                'display_name' => $barbarianName,
                'activated' => true,
                'activation_code' => str_random(),
            ]);

            $i++;
        }
    }

    protected function createTestUsers(): void
    {
        $races = Race::where('enabled',1)->where('name', '!=', 'Barbarian')->get();

        foreach($races as $race)
        {
            $raceKey = str_replace(' ', '', strtolower($race->name));

            User::create([
                'email' => $raceKey . '@odarena.local',
                'password' => bcrypt('test1234'),
                'display_name' => $race->name,
                'activated' => true,
                'activation_code' => str_random(),
            ]);
        }
    }

    protected function createRoundLeague(): RoundLeague
    {
        return RoundLeague::create([
            'name' => 'Test Round',
            'description' => 'Test Round'
        ]);
    }
}
