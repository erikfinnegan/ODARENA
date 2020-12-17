<?php

namespace OpenDominion\Http\Controllers;

use OpenDominion\Calculators\Dominion\Actions\TrainingCalculator;
use OpenDominion\Helpers\BuildingHelper;
use OpenDominion\Helpers\EspionageHelper;
use OpenDominion\Helpers\LandHelper;
use OpenDominion\Helpers\RaceHelper;
use OpenDominion\Helpers\SpellHelper;
use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Models\Race;
use OpenDominion\Models\Title;
use OpenDominion\Helpers\TitleHelper;
use OpenDominion\Helpers\TechHelper;
use OpenDominion\Models\Tech;
use OpenDominion\Models\Spell;

class ScribesController extends AbstractController
{
    public function getRaces()
    {
        $races = collect(Race::orderBy('name')->get())->groupBy('alignment')->toArray();
        return view('pages.scribes.factions', [
            'goodRaces' => $races['good'],
            'evilRaces' => $races['evil'],
            'npcRaces' => $races['npc'],
            'independentRaces' => $races['independent'],
        ]);
    }

    public function getRace(string $raceName)
    {
        $raceName = ucwords(str_replace('-', ' ', $raceName));

        $race = Race::where('name', $raceName)->firstOrFail();

        return view('pages.scribes.faction', [
            'landHelper' => app(LandHelper::class),
            'unitHelper' => app(UnitHelper::class),
            'raceHelper' => app(RaceHelper::class),
            'spellHelper' => app(SpellHelper::class),
            'trainingCalculator' => app(TrainingCalculator::class),
            'race' => $race,
        ]);
    }

    public function getConstruction()
    {
        $buildingHelper = app(BuildingHelper::class);

        $buildingTypesPerLandType = $buildingHelper->getBuildingTypesByRace();
        $buildingTypeWithLandType = [];
        foreach ($buildingTypesPerLandType as $landType => $buildingTypes) {
            foreach($buildingTypes as $buildingType) {
                $buildingTypeWithLandType[$buildingType] = $landType;
            }
        }

        $buildingTypeWithLandType['home'] = null;

        ksort($buildingTypeWithLandType);

        $races = collect(Race::where('playable', 1)->orderBy('name')->get())->groupBy('alignment')->toArray();
        return view('pages.scribes.construction', [
            'goodRaces' => $races['good'],
            'evilRaces' => $races['evil'],
            #'npcRaces' => $races['npc'],
            #'independentRaces' => $races['independent'],
            'buildingTypeWithLandType' => $buildingTypeWithLandType,
            'buildingHelper' => $buildingHelper,
            'landHelper' => app(LandHelper::class),
        ]);
    }

    public function getEspionage()
    {
        return view('pages.scribes.espionage', [
            'espionageHelper' => app(EspionageHelper::class)
        ]);
    }

    public function getMagic()
    {
        return view('pages.scribes.magic', [
            'spellHelper' => app(SpellHelper::class)
        ]);
    }

    public function getTitles()
    {
        $titles = collect(Title::orderBy('name')->get());
        return view('pages.scribes.titles', [
            'titles' => $titles,
            'titleHelper' => app(TitleHelper::class),
        ]);
    }

    public function getAdvancements()
    {
        return view('pages.scribes.advancements', [
            'techs' => Tech::all()->where('enabled',1)->keyBy('key')->sortBy('key'),
            'techHelper' => app(TechHelper::class),
        ]);
    }

    public function getSpells()
    {
        return view('pages.scribes.spells', [
            'spells' => Spell::all()->keyBy('key')->sortBy('key'),
            'spellHelper' => app(SpellHelper::class),
        ]);
    }

}
