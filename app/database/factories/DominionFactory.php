<?php

use Faker\Generator as Faker;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(\OpenDominion\Models\Dominion::class, function (Faker $faker) {
    return [
        'name' => $faker->unique()->name,
        'prestige' => 250,
        'peasants' => 1300,
        'peasants_last_hour' => 0,
        'draft_rate' => 10,
        'morale' => 100,
        'spy_strength' => 100,
        'wizard_strength' => 100,
        'resource_gold' => 100000,
        'resource_food' => 15000,
        'resource_lumber' => 15000,
        'resource_mana' => 0,
        'resource_ore' => 0,
        'resource_gems' => 10000,
        'resource_tech' => 0,
        'resource_champion' => 0,
        'resource_soul' => 0,
        'resource_blood' => 0,
        'improvement_tissue' => 0,
        'military_draftees' => 100,
        'military_unit1' => 0,
        'military_unit2' => 150,
        'military_unit3' => 0,
        'military_unit4' => 0,
        'military_spies' => 25,
        'military_wizards' => 25,
        'military_archmages' => 0,
        'land_plain' => 110,
        'land_mountain' => 20,
        'land_swamp' => 20,
        'land_cavern' => 20,
        'land_forest' => 40,
        'land_hill' => 20,
        'land_water' => 20,
    ];
});
