<?php

namespace OpenDominion\Console\Commands\Game;

use DB;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use OpenDominion\Console\Commands\CommandInterface;
use OpenDominion\Models\Race;
use OpenDominion\Models\RacePerk;
use OpenDominion\Models\RacePerkType;
use OpenDominion\Models\Tech;
use OpenDominion\Models\TechPerk;
use OpenDominion\Models\TechPerkType;
use OpenDominion\Models\Unit;
use OpenDominion\Models\UnitPerk;
use OpenDominion\Models\UnitPerkType;
use OpenDominion\Models\Building;
use OpenDominion\Models\BuildingPerk;
use OpenDominion\Models\BuildingPerkType;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class DataSyncCommand extends Command implements CommandInterface
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'game:data:sync';

    /** @var string The console command description. */
    protected $description = '';

    /** @var Filesystem */
    protected $filesystem;

    /**
     * DataSyncCommand constructor.
     *
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();

        $this->filesystem = $filesystem;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(): void
    {
        DB::transaction(function () {
            $this->syncRaces();
            $this->syncTechs();
            #$this->syncBuildings();
        });
    }

    /**
     * Syncs race, unit and perk data from .yml files to the database.
     */
    protected function syncRaces()
    {
        $files = $this->filesystem->files(base_path('app/data/races'));

        foreach ($files as $file) {
            $data = Yaml::parse($file->getContents(), Yaml::PARSE_OBJECT_FOR_MAP);

            // Race
            $race = Race::firstOrNew(['name' => $data->name])
                ->fill([
                    'alignment' => object_get($data, 'alignment'),
                    'description' => object_get($data, 'description'),
                    'home_land_type' => object_get($data, 'home_land_type'),

                    # ODA
                    'playable' => object_get($data, 'playable', true),
                    'attacking' => object_get($data, 'attacking'),
                    'exploring' => object_get($data, 'exploring'),
                    'converting' => object_get($data, 'converting'),
                ]);

            if (!$race->exists) {
                $this->info("Adding race {$data->name}");
            } else {
                $this->info("Processing race {$data->name}");

                $newValues = $race->getDirty();

                foreach ($newValues as $key => $newValue) {
                    $originalValue = $race->getOriginal($key);

                    $this->info("[Change] {$key}: {$originalValue} -> {$newValue}");
                }
            }

            $race->save();
            $race->refresh();

            // Race Perks
            $racePerksToSync = [];

            foreach (object_get($data, 'perks', []) as $perk => $value) {
                $value = (float)$value;

                $racePerkType = RacePerkType::firstOrCreate(['key' => $perk]);

                $racePerksToSync[$racePerkType->id] = ['value' => $value];

                $racePerk = RacePerk::query()
                    ->where('race_id', $race->id)
                    ->where('race_perk_type_id', $racePerkType->id)
                    ->first();

                if ($racePerk === null) {
                    $this->info("[Add Race Perk] {$perk}: {$value}");
                } elseif ($racePerk->value != $value) {
                    $this->info("[Change Race Perk] {$perk}: {$racePerk->value} -> {$value}");
                }
            }

            $race->perks()->sync($racePerksToSync);

            // Units
            foreach (object_get($data, 'units', []) as $slot => $unitData) {
                $slot++; // Because array indices start at 0

                $unitName = object_get($unitData, 'name');

                $this->info("Unit {$slot}: {$unitName}", OutputInterface::VERBOSITY_VERBOSE);

                $where = [
                    'race_id' => $race->id,
                    'slot' => $slot,
                ];

                $unit = Unit::where($where)->first();

                if ($unit === null) {
                    $unit = Unit::make($where);
                }

                $unit->fill([
                    'name' => $unitName,
                    'cost_platinum' => object_get($unitData, 'cost.platinum', 0),
                    'cost_ore' => object_get($unitData, 'cost.ore', 0),
                    'power_offense' => object_get($unitData, 'power.offense', 0),
                    'power_defense' => object_get($unitData, 'power.defense', 0),
                    'need_boat' => (int)object_get($unitData, 'need_boat', true),
                    'type' => object_get($unitData, 'type'),

                    // New unit cost resources
                    'cost_food' => object_get($unitData, 'cost.food', 0),
                    'cost_mana' => object_get($unitData, 'cost.mana', 0),
                    'cost_gem' => object_get($unitData, 'cost.gem', 0),
                    'cost_lumber' => object_get($unitData, 'cost.lumber', 0),
                    'cost_prestige' => object_get($unitData, 'cost.prestige', 0),
                    'cost_boat' => object_get($unitData, 'cost.boat', 0),
                    'cost_champion' => object_get($unitData, 'cost.champion', 0),
                    'cost_soul' => object_get($unitData, 'cost.soul', 0),
                    'cost_unit1' => object_get($unitData, 'cost.unit1', 0),
                    'cost_unit2' => object_get($unitData, 'cost.unit2', 0),
                    'cost_unit3' => object_get($unitData, 'cost.unit3', 0),
                    'cost_unit4' => object_get($unitData, 'cost.unit4', 0),
                    'cost_spy' => object_get($unitData, 'cost.spy', 0),
                    'cost_wizard' => object_get($unitData, 'cost.wizard', 0),
                    'cost_archmage' => object_get($unitData, 'cost.archmage', 0),
                    'cost_morale' => object_get($unitData, 'cost.morale', 0),
                    'cost_wild_yeti' => object_get($unitData, 'cost.wild_yeti', 0),
                    'static_networth' => object_get($unitData, 'static_networth', 0),
                ]);

                if ($unit->exists) {
                    $newValues = $unit->getDirty();

                    foreach ($newValues as $key => $newValue) {
                        $originalValue = $unit->getOriginal($key);

                        $this->info("[Change] {$key}: {$originalValue} -> {$newValue}");
                    }
                }

                $unit->save();
                $unit->refresh();

                // Unit perks
                $unitPerksToSync = [];

                foreach (object_get($unitData, 'perks', []) as $perk => $value) {
                    $value = (string)$value; // Can have multiple values for a perk, comma separated. todo: Probably needs a refactor later to JSON

                    $unitPerkType = UnitPerkType::firstOrCreate(['key' => $perk]);

                    $unitPerksToSync[$unitPerkType->id] = ['value' => $value];

                    $unitPerk = UnitPerk::query()
                        ->where('unit_id', $unit->id)
                        ->where('unit_perk_type_id', $unitPerkType->id)
                        ->first();

                    if ($unitPerk === null) {
                        $this->info("[Add Unit Perk] {$perk}: {$value}");
                    } elseif ($unitPerk->value != $value) {
                        $this->info("[Change Unit Perk] {$perk}: {$unitPerk->value} -> {$value}");
                    }
                }

                $unit->perks()->sync($unitPerksToSync);
            }
        }
    }

    /**
     * Syncs tech and perk data from .yml file to the database.
     */
    protected function syncTechs()
    {
        $fileContents = $this->filesystem->get(base_path('app/data/techs.yml'));

        $data = Yaml::parse($fileContents, Yaml::PARSE_OBJECT_FOR_MAP);

        foreach ($data as $techKey => $techData) {
            // Tech
            $tech = Tech::firstOrNew(['key' => $techKey])
                ->fill([
                    'name' => $techData->name,
                    'prerequisites' => object_get($techData, 'requires', []),
                    'cost_multiplier' => $techData->cost_multiplier,
                    'enabled' => (int)object_get($techData, 'enabled', 1),
                ]);

            if (!$tech->exists) {
                $this->info("Adding tech {$techData->name}");
            } else {
                $this->info("Processing tech {$techData->name}");

                $newValues = $tech->getDirty();

                foreach ($newValues as $key => $newValue) {
                    $originalValue = $tech->getOriginal($key);

                    $this->info("[Change] {$key}: {$originalValue} -> {$newValue}");
                }
            }

            $tech->save();
            $tech->refresh();

            // Tech Perks
            $techPerksToSync = [];

            foreach (object_get($techData, 'perks', []) as $perk => $value) {
                $value = (float)$value;

                $techPerkType = TechPerkType::firstOrCreate(['key' => $perk]);

                $techPerksToSync[$techPerkType->id] = ['value' => $value];

                $techPerk = TechPerk::query()
                    ->where('tech_id', $tech->id)
                    ->where('tech_perk_type_id', $techPerkType->id)
                    ->first();

                if ($techPerk === null) {
                    $this->info("[Add Tech Perk] {$perk}: {$value}");
                } elseif ($techPerk->value != $value) {
                    $this->info("[Change Tech Perk] {$perk}: {$techPerk->value} -> {$value}");
                }
            }

            $tech->perks()->sync($techPerksToSync);
        }
    }


        /**
         * Syncs building and perk data from .yml file to the database.
         */
        protected function syncBuildings()
        {
            $fileContents = $this->filesystem->get(base_path('app/data/buildings.yml'));

            $data = Yaml::parse($fileContents, Yaml::PARSE_OBJECT_FOR_MAP);

            foreach ($data as $buildingKey => $buildingData) {
                // Building
                $building = Building::firstOrNew(['key' => $buildingKey])
                    ->fill([
                        'name' => $buildingData->name,
                        'excluded_races' => object_get($buildingData, 'excluded_races', []),
                        'exclusive_races' => object_get($buildingData, 'exclusive_races', []),
                        'land_type' => object_get($buildingData, 'land_type'),
                    ]);

                if (!$building->exists) {
                    $this->info("Adding building {$buildingData->name}");
                } else {
                    $this->info("Processing building {$buildingData->name}");

                    $newValues = $building->getDirty();

                    foreach ($newValues as $key => $newValue) {
                        $originalValue = $building->getOriginal($key);

                        $this->info("[Change] {$key}: {$originalValue} -> {$newValue}");
                    }
                }

                $building->save();
                $building->refresh();

                // Building Perks
                $buildingPerksToSync = [];

                foreach (object_get($buildingData, 'perks', []) as $perk => $value)
                {
                    $value = (float)$value;

                    $buildingPerkType = BuildingPerkType::firstOrCreate(['key' => $perk]);

                    $buildingPerksToSync[$buildingPerkType->id] = ['value' => $value];

                    $buildingPerk = BuildingPerk::query()
                        ->where('building_id', $building->id)
                        ->where('building_perk_type_id', $buildingPerkType->id)
                        ->first();

                    if ($buildingPerk === null) {
                        $this->info("[Add Building Perk] {$perk}: {$value}");
                    } elseif ($buildingPerk->value != $value) {
                        $this->info("[Change Building Perk] {$perk}: {$buildingPerk->value} -> {$value}");
                    }
                }

                $building->perks()->sync($buildingPerksToSync);
            }
        }

}
