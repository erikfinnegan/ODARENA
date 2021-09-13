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
use OpenDominion\Models\Spell;
use OpenDominion\Models\SpellPerk;
use OpenDominion\Models\SpellPerkType;
use OpenDominion\Models\Improvement;
use OpenDominion\Models\ImprovementPerk;
use OpenDominion\Models\ImprovementPerkType;
use OpenDominion\Models\Spyop;
use OpenDominion\Models\SpyopPerk;
use OpenDominion\Models\SpyopPerkType;
use OpenDominion\Models\Deity;
use OpenDominion\Models\DeityPerk;
use OpenDominion\Models\DeityPerkType;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use OpenDominion\Models\Stat;
use OpenDominion\Models\Resource;


use OpenDominion\Models\Title;
use OpenDominion\Models\TitlePerk;
use OpenDominion\Models\TitlePerkType;

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
            $this->syncBuildings();
            $this->syncTitles();
            $this->syncSpells();
            $this->syncSpyops();
            $this->syncImprovements();
            $this->syncStats();
            $this->syncResources();
            $this->syncDeities();
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

            $defaultImprovementResources = [
                'gold' => 1,
                'ore' => 2,
                'lumber' => 2,
                'gems' => 12
            ];

            $defaultResources = [
                'gold',
                'food',
                'lumber',
                'ore',
                'gems',
                'mana'
            ];

            $defaultPeasantsProduction = [
              'gold' => 2.7
            ];

            // Race
            $race = Race::firstOrNew(['name' => $data->name])
                ->fill([
                    'alignment' => object_get($data, 'alignment'),
                    'description' => object_get($data, 'description'),
                    'home_land_type' => object_get($data, 'home_land_type'),
                    'playable' => object_get($data, 'playable', 1),
                    'attacking' => object_get($data, 'attacking'),
                    'exploring' => object_get($data, 'exploring'),
                    'converting' => object_get($data, 'converting'),
                    'skill_level' => object_get($data, 'skill_level'),
                    'experimental' => object_get($data, 'experimental', 0),
                    'resources' => object_get($data, 'resources', $defaultResources),
                    'improvement_resources' => object_get($data, 'improvement_resources', $defaultImprovementResources),
                    'land_improvements' => object_get($data, 'land_improvements', NULL),
                    'construction_materials' => object_get($data, 'construction_materials', ['gold','lumber']),
                    'peasants_production' => object_get($data, 'peasants_production', $defaultPeasantsProduction),
                    'peasants_alias' => object_get($data, 'peasants_alias', null),
                    'draftees_alias' => object_get($data, 'draftees_alias', null),

                    'spies_cost' => object_get($data, 'spies_cost', '500,gold'),
                    'wizards_cost' => object_get($data, 'wizards_cost', '500,gold'),
                    'archmages_cost' => object_get($data, 'archmages_cost', '1000,gold'),
                ]);

            if (!$race->exists) {
                $this->info("Adding race {$data->name}");
            } else {
                $this->info("Processing race {$data->name}");

                $newValues = $race->getDirty();
                /*
                foreach ($newValues as $key => $newValue)
                {
                    $originalValue = $race->getOriginal($key);

                    if(is_array($originalValue))
                    {
                        $originalValue = implode(',', $originalValue);
                    }
                    if(is_array($newValue))
                    {
                        $newValue = implode(',', $newValue);
                    }

                    $this->info("[Change] {$key}: {$originalValue} -> {$newValue}");
                }
                */
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
                    'cost_gold' => object_get($unitData, 'cost.gold', 0),
                    'cost_ore' => object_get($unitData, 'cost.ore', 0),
                    'power_offense' => object_get($unitData, 'power.offense', 0),
                    'power_defense' => object_get($unitData, 'power.defense', 0),
                    'training_time' => (int)object_get($unitData, 'training_time', null),
                    'type' => object_get($unitData, 'type'),
                    'cost_food' => object_get($unitData, 'cost.food', 0),
                    'cost_mana' => object_get($unitData, 'cost.mana', 0),
                    'cost_gems' => object_get($unitData, 'cost.gems', 0),
                    'cost_lumber' => object_get($unitData, 'cost.lumber', 0),
                    'cost_prestige' => object_get($unitData, 'cost.prestige', 0),
                    'cost_champion' => object_get($unitData, 'cost.champion', 0),
                    'cost_soul' => object_get($unitData, 'cost.soul', 0),
                    'cost_blood' => object_get($unitData, 'cost.blood', 0),
                    'cost_unit1' => object_get($unitData, 'cost.unit1', 0),
                    'cost_unit2' => object_get($unitData, 'cost.unit2', 0),
                    'cost_unit3' => object_get($unitData, 'cost.unit3', 0),
                    'cost_unit4' => object_get($unitData, 'cost.unit4', 0),
                    'cost_spy' => object_get($unitData, 'cost.spy', 0),
                    'cost_wizard' => object_get($unitData, 'cost.wizard', 0),
                    'cost_archmage' => object_get($unitData, 'cost.archmage', 0),
                    'cost_morale' => object_get($unitData, 'cost.morale', 0),
                    'cost_spy_strength' => object_get($unitData, 'cost.spy_strength', 0),
                    'cost_wizard_strength' => object_get($unitData, 'cost.wizard_strength', 0),
                    'cost_peasant' => object_get($unitData, 'cost.peasant', 0),
                    'cost_brimmer' => object_get($unitData, 'cost.brimmer', 0),
                    'cost_prisoner' => object_get($unitData, 'cost.prisoner', 0),
                    'cost_horse' => object_get($unitData, 'cost.horse', 0),
                    'static_networth' => object_get($unitData, 'static_networth', 0),
                ]);

                if ($unit->exists) {
                    $newValues = $unit->getDirty();

                    foreach ($newValues as $key => $newValue)
                    {
                        $originalValue = $unit->getOriginal($key);

                        if(is_array($originalValue))
                        {
                            $originalValue = implode(',', $originalValue);
                        }
                        if(is_array($newValue))
                        {
                            $newValue = implode(',', $newValue);
                        }

                        $this->info("[Change] {$key}: {$originalValue} -> {$newValue}");
                    }
                }

                $unit->save();
                $unit->refresh();

                // Unit perks
                $unitPerksToSync = [];

                foreach (object_get($unitData, 'perks', []) as $perk => $value)
                {
                    $value = (string)$value; // Can have multiple values for a perk, comma separated. todo: Probably needs a refactor later to JSON

                    $unitPerkType = UnitPerkType::firstOrCreate(['key' => $perk]);

                    $unitPerksToSync[$unitPerkType->id] = ['value' => $value];

                    $unitPerk = UnitPerk::query()
                        ->where('unit_id', $unit->id)
                        ->where('unit_perk_type_id', $unitPerkType->id)
                        ->first();

                    if ($unitPerk === null)
                    {
                        $this->info("[Add Unit Perk] {$perk}: {$value}");
                    }
                    elseif ($unitPerk->value != $value)
                    {
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
                    'level' => $techData->level,
                    'enabled' => (int)object_get($techData, 'enabled', 1),
                ]);

            if (!$tech->exists) {
                $this->info("Adding tech {$techData->name}");
            } else {
                $this->info("Processing tech {$techData->name}");

                $newValues = $tech->getDirty();

                foreach ($newValues as $key => $newValue)
                {
                    $originalValue = $tech->getOriginal($key);

                    if(is_array($originalValue))
                    {
                        $originalValue = implode(',', $originalValue);
                    }
                    if(is_array($newValue))
                    {
                        $newValue = implode(',', $newValue);
                    }

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
                        'land_type' => object_get($buildingData, 'land_type'),
                        'excluded_races' => object_get($buildingData, 'excluded_races', []),
                        'exclusive_races' => object_get($buildingData, 'exclusive_races', []),
                        'enabled' => (int)object_get($buildingData, 'enabled', 1),
                    ]);


                if (!$building->exists) {
                    $this->info("Adding building {$buildingData->name}");
                } else {
                    $this->info("Processing building {$buildingData->name}");

                    $newValues = $building->getDirty();

                    foreach ($newValues as $key => $newValue)
                    {
                        $originalValue = $building->getOriginal($key);

                        if(is_array($originalValue))
                        {
                            $originalValue = implode(',', $originalValue);
                        }
                        if(is_array($newValue))
                        {
                            $newValue = implode(',', $newValue);
                        }

                        $this->info("[Change] {$key}: {$originalValue} -> {$newValue}");
                    }
                }

                $building->save();
                $building->refresh();

                // Building Perks
                $buildingPerksToSync = [];

                foreach (object_get($buildingData, 'perks', []) as $perk => $value)
                {
                    $value = (string)$value;

                    $buildingPerkType = BuildingPerkType::firstOrCreate(['key' => $perk]);

                    $buildingPerksToSync[$buildingPerkType->id] = ['value' => $value];

                    $buildingPerk = BuildingPerk::query()
                        ->where('building_id', $building->id)
                        ->where('building_perk_type_id', $buildingPerkType->id)
                        ->first();

                    if ($buildingPerk === null)
                    {
                        $this->info("[Add Building Perk] {$perk}: {$value}");
                    }
                    elseif ($buildingPerk->value != $value)
                    {
                        $this->info("[Change Building Perk] {$perk}: {$buildingPerk->value} -> {$value}");
                    }
                }

                $building->perks()->sync($buildingPerksToSync);
            }
        }

        /**
         * Syncs titles and perk data from .yml file to the database.
         */
        protected function syncTitles()
        {
            $fileContents = $this->filesystem->get(base_path('app/data/titles.yml'));

            $data = Yaml::parse($fileContents, Yaml::PARSE_OBJECT_FOR_MAP);

            foreach ($data as $titleKey => $titleData) {
                // Title
                $title = Title::firstOrNew(['key' => $titleKey])
                    ->fill([
                        'name' => $titleData->name,
                        'enabled' => (int)object_get($titleData, 'enabled', 1),
                    ]);

                if (!$title->exists) {
                    $this->info("Adding title {$titleData->name}");
                } else {
                    $this->info("Processing title {$titleData->name}");

                    $newValues = $title->getDirty();

                    foreach ($newValues as $key => $newValue)
                    {
                        $originalValue = $title->getOriginal($key);

                        if(is_array($originalValue))
                        {
                            $originalValue = implode(',', $originalValue);
                        }
                        if(is_array($newValue))
                        {
                            $newValue = implode(',', $newValue);
                        }

                        $this->info("[Change] {$key}: {$originalValue} -> {$newValue}");
                    }
                }

                $title->save();
                $title->refresh();

                // Title Perks
                $titlePerksToSync = [];

                foreach (object_get($titleData, 'perks', []) as $perk => $value)
                {
                    $value = (string)$value;

                    $titlePerkType = TitlePerkType::firstOrCreate(['key' => $perk]);

                    $titlePerksToSync[$titlePerkType->id] = ['value' => $value];

                    $titlePerk = TitlePerk::query()
                        ->where('title_id', $title->id)
                        ->where('title_perk_type_id', $titlePerkType->id)
                        ->first();

                    if ($titlePerk === null)
                    {
                        $this->info("[Add Title Perk] {$perk}: {$value}");
                    }
                    elseif ($titlePerk->value != $value)
                    {
                        $this->info("[Change Title Perk] {$perk}: {$titlePerk->value} -> {$value}");
                    }
                }

                $title->perks()->sync($titlePerksToSync);
            }
        }

        /**
         * Syncs spells and perk data from .yml file to the database.
         */
        protected function syncSpells()
        {
            $fileContents = $this->filesystem->get(base_path('app/data/spells.yml'));

            $data = Yaml::parse($fileContents, Yaml::PARSE_OBJECT_FOR_MAP);

            foreach ($data as $spellKey => $spellData) {
                // Spell
                $spell = Spell::firstOrNew(['key' => $spellKey])
                    ->fill([
                        'name' => $spellData->name,
                        'scope' => object_get($spellData, 'scope'),
                        'class' => object_get($spellData, 'class'),
                        'cost' => object_get($spellData, 'cost', 1),
                        'duration' => object_get($spellData, 'duration', 48),
                        'cooldown' => object_get($spellData, 'cooldown', 0),
                        'wizard_strength' => object_get($spellData, 'wizard_strength'),
                        'enabled' => object_get($spellData, 'enabled', 1),
                        'excluded_races' => object_get($spellData, 'excluded_races', []),
                        'exclusive_races' => object_get($spellData, 'exclusive_races', []),
                    ]);

                if (!$spell->exists) {
                    $this->info("Adding spell {$spellData->name}");
                } else {
                    $this->info("Processing spell {$spellData->name}");

                    $newValues = $spell->getDirty();

                    foreach ($newValues as $key => $newValue)
                    {
                        $originalValue = $spell->getOriginal($key);

                        if(is_array($originalValue))
                        {
                            $originalValue = implode(',', $originalValue);
                        }
                        if(is_array($newValue))
                        {
                            $newValue = implode(',', $newValue);
                        }

                        $this->info("[Change] {$key}: {$originalValue} -> {$newValue}");
                    }
                }

                $spell->save();
                $spell->refresh();

                // Spell Perks
                $spellPerksToSync = [];

                foreach (object_get($spellData, 'perks', []) as $perk => $value)
                {
                    $value = (string)$value;

                    $spellPerkType = SpellPerkType::firstOrCreate(['key' => $perk]);

                    $spellPerksToSync[$spellPerkType->id] = ['value' => $value];

                    $spellPerk = SpellPerk::query()
                        ->where('spell_id', $spell->id)
                        ->where('spell_perk_type_id', $spellPerkType->id)
                        ->first();

                    if ($spellPerk === null)
                    {
                        $this->info("[Add Spell Perk] {$perk}: {$value}");
                    }
                    elseif ($spellPerk->value != $value)
                    {
                        $this->info("[Change Spell Perk] {$perk}: {$spellPerk->value} -> {$value}");
                    }
                }

                $spell->perks()->sync($spellPerksToSync);
            }
        }

        /**
         * Syncs spells and perk data from .yml file to the database.
         */
        protected function syncSpyops()
        {
            $fileContents = $this->filesystem->get(base_path('app/data/spyops.yml'));

            $data = Yaml::parse($fileContents, Yaml::PARSE_OBJECT_FOR_MAP);

            foreach ($data as $spyopKey => $spyopData) {
                // Spell
                $spyop = Spyop::firstOrNew(['key' => $spyopKey])
                    ->fill([
                        'name' => $spyopData->name,
                        'scope' => object_get($spyopData, 'scope'),
                        'spy_strength' => object_get($spyopData, 'spy_strength'),
                        'enabled' => object_get($spyopData, 'enabled', 1),
                        'excluded_races' => object_get($spyopData, 'excluded_races', []),
                        'exclusive_races' => object_get($spyopData, 'exclusive_races', []),
                    ]);

                if (!$spyop->exists) {
                    $this->info("Adding spyop {$spyopData->name}");
                } else {
                    $this->info("Processing spyop {$spyopData->name}");

                    $newValues = $spyop->getDirty();

                    foreach ($newValues as $key => $newValue)
                    {
                        $originalValue = $spyop->getOriginal($key);

                        if(is_array($originalValue))
                        {
                            $originalValue = implode(',', $originalValue);
                        }
                        if(is_array($newValue))
                        {
                            $newValue = implode(',', $newValue);
                        }

                        $this->info("[Change] {$key}: {$originalValue} -> {$newValue}");
                    }
                }

                $spyop->save();
                $spyop->refresh();

                // Spyop Perks
                $spyopPerksToSync = [];

                foreach (object_get($spyopData, 'perks', []) as $perk => $value)
                {
                    $value = (string)$value;

                    $spyopPerkType = SpyopPerkType::firstOrCreate(['key' => $perk]);

                    $spyopPerksToSync[$spyopPerkType->id] = ['value' => $value];

                    $spyopPerk = SpyopPerk::query()
                        ->where('spyop_id', $spyop->id)
                        ->where('spyop_perk_type_id', $spyopPerkType->id)
                        ->first();

                    if ($spyopPerk === null)
                    {
                        $this->info("[Add Spyop Perk] {$perk}: {$value}");
                    }
                    elseif ($spyopPerk->value != $value)
                    {
                        $this->info("[Change Spyop Perk] {$perk}: {$spyopPerk->value} -> {$value}");
                    }
                }

                $spyop->perks()->sync($spyopPerksToSync);
            }
        }

        /**
         * Syncs improvements and perk data from .yml file to the database.
         */
        protected function syncImprovements()
        {
            $fileContents = $this->filesystem->get(base_path('app/data/improvements.yml'));

            $data = Yaml::parse($fileContents, Yaml::PARSE_OBJECT_FOR_MAP);

            foreach ($data as $improvementKey => $improvementData) {
                // Spell
                $improvement = Improvement::firstOrNew(['key' => $improvementKey])
                    ->fill([
                        'name' => $improvementData->name,
                        'enabled' => object_get($improvementData, 'enabled', 1),
                        'excluded_races' => object_get($improvementData, 'excluded_races', []),
                        'exclusive_races' => object_get($improvementData, 'exclusive_races', []),
                    ]);

                if (!$improvement->exists) {
                    $this->info("Adding improvement {$improvementData->name}");
                } else {
                    $this->info("Processing improvement {$improvementData->name}");

                    $newValues = $improvement->getDirty();

                    foreach ($newValues as $key => $newValue)
                    {
                        $originalValue = $improvement->getOriginal($key);

                        if(is_array($originalValue))
                        {
                            $originalValue = implode(',', $originalValue);
                        }
                        if(is_array($newValue))
                        {
                            $newValue = implode(',', $newValue);
                        }

                        $this->info("[Change] {$key}: {$originalValue} -> {$newValue}");
                    }
                }

                $improvement->save();
                $improvement->refresh();

                // Spell Perks
                $improvementPerksToSync = [];

                foreach (object_get($improvementData, 'perks', []) as $perk => $value)
                {
                    $value = (string)$value;

                    $improvementPerkType = ImprovementPerkType::firstOrCreate(['key' => $perk]);

                    $improvementPerksToSync[$improvementPerkType->id] = ['value' => $value];

                    $improvementPerk = ImprovementPerk::query()
                        ->where('improvement_id', $improvement->id)
                        ->where('improvement_perk_type_id', $improvementPerkType->id)
                        ->first();

                    if ($improvementPerk === null)
                    {
                        $this->info("[Add Improvement Perk] {$perk}: {$value}");
                    }
                    elseif ($improvementPerk->value != $value)
                    {
                        $this->info("[Change Improvement Perk] {$perk}: {$improvementPerk->value} -> {$value}");
                    }
                }

                $improvement->perks()->sync($improvementPerksToSync);
            }
        }

        /**
         * Syncs stats from .yml file to the database.
         */
        protected function syncStats()
        {
            $fileContents = $this->filesystem->get(base_path('app/data/stats.yml'));

            $data = Yaml::parse($fileContents, Yaml::PARSE_OBJECT_FOR_MAP);

            foreach ($data as $statKey => $statData) {
                // Spell
                $stat = Stat::firstOrNew(['key' => $statKey])
                    ->fill([
                        'name' => $statData->name,
                        'enabled' => object_get($statData, 'enabled', 1)
                    ]);

                if (!$stat->exists) {
                    $this->info("Adding stat {$statData->name}");
                } else {
                    $this->info("Processing stat {$statData->name}");

                    $newValues = $stat->getDirty();

                    foreach ($newValues as $key => $newValue)
                    {
                        $originalValue = $stat->getOriginal($key);

                        if(is_array($originalValue))
                        {
                            $originalValue = implode(',', $originalValue);
                        }
                        if(is_array($newValue))
                        {
                            $newValue = implode(',', $newValue);
                        }

                        $this->info("[Change] {$key}: {$originalValue} -> {$newValue}");
                    }
                }

                $stat->save();
                $stat->refresh();
            }
        }

        /**
         * Syncs resources from .yml file to the database.
         */
        protected function syncResources()
        {
            $fileContents = $this->filesystem->get(base_path('app/data/resources.yml'));

            $data = Yaml::parse($fileContents, Yaml::PARSE_OBJECT_FOR_MAP);

            foreach ($data as $resourceKey => $resourceData) {
                // Resource
                $resource = Resource::firstOrNew(['key' => $resourceKey])
                    ->fill([
                        'name' => $resourceData->name,
                        'enabled' => object_get($resourceData, 'enabled', 1),
                        'buy' => object_get($resourceData, 'buy', null),
                        'sell' => object_get($resourceData, 'sell', null),
                        'excluded_races' => object_get($resourceData, 'excluded_races', []),
                        'exclusive_races' => object_get($resourceData, 'exclusive_races', []),
                    ]);

                if (!$resource->exists) {
                    $this->info("Adding resource {$resourceData->name}");
                } else {
                    $this->info("Processing resource {$resourceData->name}");

                    $newValues = $resource->getDirty();

                    foreach ($newValues as $key => $newValue)
                    {
                        $originalValue = $resource->getOriginal($key);

                        if(is_array($originalValue))
                        {
                            $originalValue = implode(',', $originalValue);
                        }
                        if(is_array($newValue))
                        {
                            $newValue = implode(',', $newValue);
                        }

                        $this->info("[Change] {$key}: {$originalValue} -> {$newValue}");
                    }
                }

                $resource->save();
                $resource->refresh();

            }
        }

      /**
       * Syncs spells and perk data from .yml file to the database.
       */
       protected function syncDeities()
        {
            $fileContents = $this->filesystem->get(base_path('app/data/deities.yml'));

            $data = Yaml::parse($fileContents, Yaml::PARSE_OBJECT_FOR_MAP);

            foreach ($data as $deityKey => $deityData) {
                // Deity
                $deity = Deity::firstOrNew(['key' => $deityKey])
                    ->fill([
                        'name' => $deityData->name,
                        'enabled' => object_get($deityData, 'enabled', 1),
                        'range_multiplier' => object_get($deityData, 'range_multiplier', 0.75),
                        'excluded_races' => object_get($deityData, 'excluded_races', []),
                        'exclusive_races' => object_get($deityData, 'exclusive_races', []),
                    ]);

                if (!$deity->exists) {
                    $this->info("Adding deity {$deityData->name}");
                } else {
                    $this->info("Processing deity {$deityData->name}");

                    $newValues = $deity->getDirty();

                    foreach ($newValues as $key => $newValue)
                    {
                        $originalValue = $deity->getOriginal($key);

                        if(is_array($originalValue))
                        {
                            $originalValue = implode(',', $originalValue);
                        }
                        if(is_array($newValue))
                        {
                            $newValue = implode(',', $newValue);
                        }

                        $this->info("[Change] {$key}: {$originalValue} -> {$newValue}");
                    }
                }

                $deity->save();
                $deity->refresh();

                // Deity Perks
                $deityPerksToSync = [];

                foreach (object_get($deityData, 'perks', []) as $perk => $value)
                {
                    $value = (string)$value;

                    $deityPerkType = DeityPerkType::firstOrCreate(['key' => $perk]);

                    $deityPerksToSync[$deityPerkType->id] = ['value' => $value];

                    $deityPerk = DeityPerk::query()
                        ->where('deity_id', $deity->id)
                        ->where('deity_perk_type_id', $deityPerkType->id)
                        ->first();

                    if ($deityPerk === null)
                    {
                        $this->info("[Add Deity Perk] {$perk}: {$value}");
                    }
                    elseif ($deityPerk->value != $value)
                    {
                        $this->info("[Change Deity Perk] {$perk}: {$deityPerk->value} -> {$value}");
                    }
                }

                $deity->perks()->sync($deityPerksToSync);
            }
        }



}
