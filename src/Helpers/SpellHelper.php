<?php

namespace OpenDominion\Helpers;

use Illuminate\Support\Collection;
use OpenDominion\Models\Race;
use OpenDominion\Models\Spell;

# ODA
use OpenDominion\Models\Dominion;

class SpellHelper
{
/*
    public function getSpellInfo(string $spellKey): array
    {
        $spell = Spell::where('key', $spellKey)->first();

        $spellInfo = [
            'name' => $spell->name,
            'scope' => $spell->scope,
            'class' => $spell->class,
            'cost' => $spell->cost,
            'duration' => $spell->duration,
            'cooldown' => $spell->cooldown
        ];

        return $spellInfo;
    }
*/
    public function getSpellInfo(string $spellKey, Dominion $dominion, bool $isInvasionSpell = false, bool $isViewOnly = false): array
    {
        return $this->getSpells($dominion, $isInvasionSpell, $isViewOnly)->filter(function ($spell) use ($spellKey) {
            return ($spell['key'] === $spellKey);
        })->first();
    }

    public function isSelfSpell(string $spellKey, Dominion $dominion): bool
    {
        return $this->getSelfSpells($dominion)->filter(function ($spell) use ($spellKey) {
            return ($spell['key'] === $spellKey);
        })->isNotEmpty();
    }

    public function isOffensiveSpell(string $spellKey, Dominion $dominion = null, bool $isInvasionSpell = false, bool $isViewOnly = false): bool
    {
        return $this->getOffensiveSpells($dominion, $isInvasionSpell, $isViewOnly)->filter(function ($spell) use ($spellKey) {
            return ($spell['key'] === $spellKey);
        })->isNotEmpty();
    }

    public function isInfoOpSpell(string $spellKey): bool
    {
        return $this->getInfoOpSpells()->filter(function ($spell) use ($spellKey) {
            return ($spell['key'] === $spellKey);
        })->isNotEmpty();
    }

    public function isHostileSpell(string $spellKey, Dominion $dominion, bool $isInvasionSpell = false, bool $isViewOnly = false): bool
    {
        return $this->getHostileSpells($dominion, $isInvasionSpell, $isViewOnly)->filter(function ($spell) use ($spellKey) {
            return ($spell['key'] === $spellKey);
        })->isNotEmpty();
    }

    public function isBlackOpSpell(string $spellKey, bool $isViewOnly = false): bool
    {
        return $this->getBlackOpSpells($dominion, $isViewOnly)->filter(function ($spell) use ($spellKey) {
            return ($spell['key'] === $spellKey);
        })->isNotEmpty();
    }

    public function getSpells(Dominion $dominion, bool $isInvasionSpell = false, bool $isViewOnly = false): Collection
    {

        return $this->getSelfSpells($dominion, $isViewOnly)
            ->merge($this->getOffensiveSpells($dominion, $isInvasionSpell, $isViewOnly));

    }

    public function getSelfSpells(?Dominion $dominion, bool $isViewOnly = false): Collection
    {
        $spells = collect(array_filter([
            [
                'name' => "Gaia's Watch",
                'description' => '+10% food production',
                'key' => 'gaias_watch',
                'mana_cost' => 2,
                'duration' => 12*4,
                'cooldown' => 0,
            ],
            [
                'name' => 'Mining Strength',
                'description' => '+10% ore production',
                'key' => 'mining_strength',
                'mana_cost' => 2,
                'duration' => 12*4,
                'cooldown' => 0,
            ],
            [
                'name' => 'Harmony',
                'description' => '+50% population growth',
                'key' => 'harmony',
                'mana_cost' => 2.5,
                'duration' => 12*4,
                'cooldown' => 0,
            ],
            [
                'name' => 'Fool\'s Gold',
                'description' => 'Platinum theft protection',
                'key' => 'fools_gold',
                'mana_cost' => 5,
                'duration' => 10*4,
                'cooldown' => 20,
            ],
            [
                'name' => 'Surreal Perception',
                'description' => 'Reveals the dominion casting offensive spells or committing spy ops against you for 8 hours',
                'key' => 'surreal_perception',
                'mana_cost' => 4,
                'duration' => 8*4,
                'cooldown' => 0,
            ],
            [
                'name' => 'Energy Mirror',
                'description' => '25% chance to reflect incoming offensive spells',
                'key' => 'energy_mirror',
                'mana_cost' => 3,
                'duration' => 8*4,
                'cooldown' => 0,
            ],
            [
                'name' => 'Aura',
                'description' => 'Reduces damage from offensive spells by 20%.',
                'key' => 'aura',
                'mana_cost' => 3,
                'duration' => 12*2,
                'cooldown' => 0,
            ]
        ]));

        if($dominion !== null or ($isViewOnly === true))
        {
            $racialSpell = $this->getRacialSelfSpell($dominion);
            $spells->push($racialSpell);

            if((isset($dominion) and $dominion->race->name === 'Cult') or $isViewOnly)
            {
                $cultSpells = collect([
                    [
                        'name' => 'Persuasion',
                        'description' => 'Captured spies and espionage units join the Cult as spies. It takes two ticks to complete persuasion.',
                        'key' => 'persuasion',
                        'mana_cost' => 3,
                        'duration' => 12*4,
                    ],
                    [
                        'name' => 'Cogency',
                        'description' => 'Wizardry casualties are saved and join the Cult as wizards. It takes eight ticks for the new wizards to be ready.',
                        'key' => 'cogency',
                        'mana_cost' => 3,
                        'duration' => 12*4,
                    ],
                    [
                        'name' => 'Menticide',
                        'description' => 'Units affected by Mind Control join the Cult permanently as Initiates after the invasion. The menticide procedure takes 12 ticks.',
                        'key' => 'menticide',
                        'mana_cost' => 4,
                        'duration' => 2,
                    ],
                ]);

                $spells = $spells->concat($cultSpells);
            }

        }

        return $spells;
    }


    public function getRacialSelfSpell(Dominion $dominion)
    {
        $raceName = $dominion->race->name;
        return $this->getRacialSelfSpells()->filter(function ($spell) use ($raceName) {
            return $spell['races']->contains($raceName);
        })->first();
    }

    public function getRacialSelfSpellForScribes(?Race $race)
    {

        $raceName = $race->name;
        return $this->getRacialSelfSpells()->filter(function ($spell) use ($raceName) {
            return $spell['races']->contains($raceName);
        })->first();
    }


    public function getRacialSelfSpells(): Collection
    {
        return collect([
            [
                'name' => 'Divine Intervention',
                'description' => '+10% offensive power and allows you to kill immortal Undead, Demon, and Afflicted.',
                'key' => 'divine_intervention',
                'mana_cost' => 10,
                'duration' => 12*4,
                'races' => collect(['Sacred Order']),
            ],
            [
                'name' => "Miner's Sight",
                'description' => '+10% ore and +5% gem production',
                'key' => 'miners_sight',
                'mana_cost' => 8,
                'duration' => 12*4,
                'races' => collect(['Dwarf', 'Gnome', 'Artillery']),
            ],
            [
                'name' => 'Killing Rage',
                'description' => '+10% offensive power',
                'key' => 'killing_rage',
                'mana_cost' => 8,
                'duration' => 12*4,
                'races' => collect(['Goblin']),
            ],
            [
                'name' => 'Alchemist Flame',
                'description' => '+30 alchemy platinum production',
                'key' => 'alchemist_flame',
                'mana_cost' => 10,
                'duration' => 12*4,
                'races' => collect(['Firewalker']),
            ],
            [
                'name' => 'Blizzard',
                'description' => '+5% defensive strength, immune to theft',
                'key' => 'blizzard',
                'mana_cost' => 8,
                'duration' => 12*4,
                'races' => collect(['Icekin', 'Snow Elf', 'Yeti']),
            ],
            [
                'name' => 'Bloodrage',
                'description' => '+10% offensive strength, +10% offensive casualties',
                'key' => 'bloodrage',
                'mana_cost' => 8,
                'duration' => 12*4,
                'races' => collect(['Orc', 'Black Orc']),
            ],
            [
                'name' => 'Enchanted Blades',
                'description' => 'Enemy casualties increased by (WPA * 0.05)%.',
                'key' => 'enchanted_blades',
                'mana_cost' => 10,
                'duration' => 6,
                'races' => collect(['Dark Elf']),
            ],
            [
                'name' => 'Defensive Frenzy',
                'description' => '+10% defensive strength',
                'key' => 'defensive_frenzy',
                'mana_cost' => 8,
                'duration' => 12*4,
                'races' => collect(['Halfling']),
            ],
            [
                'name' => 'Howling',
                'description' => '+10% offensive strength',
                'key' => 'howling',
                'mana_cost' => 10,
                'duration' => 12*4,
                'races' => collect(['Kobold']),
            ],
            [
                'name' => 'Warsong',
                'description' => '+10% offensive power',
                'key' => 'warsong',
                'mana_cost' => 8,
                'duration' => 12*4,
                'races' => collect(['Wood Elf']),
            ],
            [
                'name' => 'Regeneration',
                'description' => '25% fewer casualties',
                'key' => 'regeneration',
                'mana_cost' => 10,
                'duration' => 12*4,
                'races' => collect(['Troll', 'Lizardfolk']),
            ],
            [
                'name' => 'Parasitic Hunger',
                'description' => '+50% conversion rate',
                'key' => 'parasitic_hunger',
                'mana_cost' => 10,
                'duration' => 12*4,
                'races' => collect(['Lycanthrope']),
            ],
            [
                'name' => 'Gaia\'s Blessing',
                'description' => '+20% food production, +10% lumber production',
                'key' => 'gaias_blessing',
                'mana_cost' => 10,
                'duration' => 12*4,
                'races' => collect(['Sylvan']),
            ],
            [
                'name' => 'Nightfall',
                'description' => '+5% offensive power',
                'key' => 'nightfall',
                'mana_cost' => 8,
                'duration' => 12*4,
                'races' => collect(['Nox']),
            ],
            [
                'name' => 'Campaign',
                'description' => '+25% land discovered on successful attacks',
                'key' => 'campaign',
                'mana_cost' => 8,
                'duration' => 1*4,
                'races' => collect(['Nomad']),
            ],
            [
                'name' => 'Swarming',
                'description' => 'Double drafting speed (2% instead of 1%)',
                'key' => 'swarming',
                'mana_cost' => 6,
                'duration' => 12*4,
                'races' => collect(['Ants']),
            ],
            [
                'name' => '𒉡𒌋𒆷',
                'description' => 'Void defensive modifiers immune to Temples.',
                'key' => 'voidspell',
                'mana_cost' => 8,
                'duration' => 12*4,
                'races' => collect(['Void']),
            ],
            [
                'name' => 'Metabolism',
                'description' => 'Double raw food production. Some enemy units killed become food.',
                'key' => 'metabolism',
                'mana_cost' => 8,
                'duration' => 6*4, # 24 ticks / 6 hours
                'cooldown' => 36, # Once every day and a half.
                'races' => collect(['Growth']),
            ],
            [
                'name' => 'Ambush',
                'description' => 'For every 5% Forest you have, removes 1% of target\'s raw defensive power (max 10% reduction).',
                'key' => 'ambush',
                'mana_cost' => 2,
                'duration' => 1*4,
                'cooldown' => 18, # Once every 18 hours.
                'races' => collect(['Beastfolk']),
            ],
            /*
            [
                'name' => 'Coastal Cannons',
                'description' => '+1% Defensive Power for every 1% Water. Max +20%.',
                'key' => 'coastal_cannons',
                'mana_cost' => 10,
                'duration' => 12*4,
                'races' => collect(['Armada']),
            ],
            */
            [
                'name' => 'Winds of Fortune',
                'description' => 'Units return two ticks faster.',
                'key' => 'winds_of_fortune',
                'mana_cost' => 10,
                'duration' => 2,
                'cooldown' => 96,
                'races' => collect(['Armada']),
            ],
            [
                'name' => 'Spiral Architecture',
                'description' => '+25% value for investments into castle improvements performed when active.',
                'key' => 'spiral_architecture',
                'mana_cost' => 8,
                'duration' => 12*4,
                'races' => collect(['Imperial Gnome']),
            ],
            [
                'name' => 'Fimbulwinter',
                'description' => '+10% defensive power, +20% offensive casualties for yourself.',
                'key' => 'fimbulwinter',
                'mana_cost' => 8,
                'duration' => 12*4,
                'races' => collect(['Norse']),
            ],
            [
                'name' => 'Desecration',
                'description' => 'Triples enemy draftees casualties.',
                'key' => 'desecration',
                'mana_cost' => 8,
                'duration' => 12*4,
                'races' => collect(['Afflicted']),
            ],
            [
                'name' => 'Infernal Fury',
                'description' => 'Increases enemy casualties by 20% on successful invasions over 75% and against all invading dominions.',
                'key' => 'infernal_fury',
                'mana_cost' => 6,
                'duration' => 6*4,
                'races' => collect(['Demon']),
            ],
            [
                'name' => 'Aurora',
                'description' => 'Reduces unit training times by 4 ticks.',
                'key' => 'aurora',
                'mana_cost' => 6,
                'duration' => 6*4, # Half a day
                'races' => collect(['Lux']),
            ],
            /*
            [
                'name' => 'Gryphon\'s Call',
                'description' => 'Quadruple yeti trapping. No offensive power bonus from Gryphon Nests.',
                'key' => 'gryphons_call',
                'mana_cost' => 2,
                'duration' => 24,
                'races' => collect(['Yeti']),
            ],
            */
            [
                'name' => 'Maelstrom',
                'description' => 'Increases offensive casualties by 50% against invading forces.',
                'key' => 'maelstrom',
                'mana_cost' => 6,
                'duration' => 12*4,
                'races' => collect(['Merfolk']),
            ],
            [
                'name' => 'Portal',
                'description' => 'Must be cast in order to send units on attack. Portal closes quickly and should be used immediately.',
                'key' => 'portal',
                'mana_cost' => 12,
                'duration' => 1,
                'cooldown' => 6, # Every 6 hours.
                'races' => collect(['Dimensionalists']),
            ],
            /*
            [
                'name' => 'Underground Caves',
                'description' => 'Psilocybe experience point production replaced by gem production (10x).',
                'key' => 'underground_caves',
                'mana_cost' => 5,
                'duration' => 12*4,
                'races' => collect(['Myconid']),
            ],
            */
            [
                'name' => 'Defensive Warts',
                'description' => 'Stops Amanita land generation and increases their defensive power (see unit description).',
                'key' => 'defensive_warts',
                'mana_cost' => 5,
                'duration' => 18,
                'races' => collect(['Myconid']),
            ],
            [
                'name' => 'Chitin',
                'description' => '+15% defensive power if attacker is affected by Insect Swarm swarm.',
                'key' => 'chitin',
                'mana_cost' => 10,
                'duration' => 12*4,
                'races' => collect(['Swarm']),
            ],
            [
                'name' => 'Rainy Season',
                'description' => '+100% defensive power, +100% population growth, +50% food production, +50% lumber production, cannot invade or explore, and no boat, ore, or gem production',
                'key' => 'rainy_season',
                'mana_cost' => 12,
                'duration' => 24*4, # Lasts one day
                'cooldown' => 24*7, # Every seven days
                'races' => collect(['Simian']),
            ],
            [
                'name' => 'Retribution',
                'description' => '+20% offensive power if target has recently invaded your realm (in the last six hours).',
                'key' => 'retribution',
                'mana_cost' => 4,
                'duration' => 1, # One tick
                'races' => collect(['Jagunii','Kerranad']),
            ],
            [
                'name' => 'Aether',
                'description' => '+10% offensive power and defensive power if your military is composed of equal amounts of every Elemental unit.',
                'key' => 'aether',
                'mana_cost' => 6,
                'duration' => 12*4,
                'races' => collect(['Elementals']),
            ],
            [
                'name' => 'Spawning Pool',
                'description' => 'For every 1% swamp lands you have, you gain 1% additional Spawns trained.',
                'key' => 'spawning_pool',
                'mana_cost' => 10,
                'duration' => 12*2, # Six hours
                'races' => collect(['Marshling']),
            ],
            [
                'name' => 'Highway Robbery',
                'description' => 'Increases offensive power by 5% against merchant caravans.',
                'key' => 'highway_robbery',
                'mana_cost' => 1,
                'duration' => 3, # Three ticks (45 minutes)
                'races' => collect(['Barbarian']),
            ],
            [
                'name' => 'Primordial Wrath',
                'description' => '+100% offensive power, +50% defensive power',
                'key' => 'primordial_wrath',
                'mana_cost' => 20,
                'duration' => 2, # Two ticks
                'races' => collect(['Monster']),
            ],
            [
                'name' => 'Mind Control',
                'description' => 'During an invasion, some of the invading units will fight for you.',
                'key' => 'mind_control',
                'mana_cost' => 12,
                'duration' => 2, # Two ticks
                'races' => collect(['Cult']),
            ],
            [
                'name' => 'Call To Arms',
                'description' => 'Training costs reduced by 10%, training times reduced by six ticks, and +20% ore and platinum production.',
                'key' => 'call_to_arms',
                'mana_cost' => 10,
                'duration' => 12,
                'cooldown' => 24, # 24 hours, 1.5 days
                'races' => collect(['Human']),
            ],
            [
                'name' => 'Fine Arts',
                'description' => '+5% platinum production, +5% gem production',
                'key' => 'fine_arts',
                'mana_cost' => 10,
                'duration' => 12*4,
                'races' => collect(['Vampires']),
            ],
            [
                'name' => 'Dark Rites',
                'description' => 'Taking bodies from the Imperial Crypt, every ten Wraith turn up to one body into a Skeleton, until the spell expires or until there are no bodies left in the crypt. Only Wraiths at home perform the Dark Rites.',
                'key' => 'dark_rites',
                'mana_cost' => 10,
                'duration' => 12*2,
                'races' => collect(['Undead']),
            ],
            [
                'name' => 'Furnace Maws',
                'description' => 'Destroy up to 10% additional buildings when successfully invading someone, if buildings are built with lumber. Dragons must account for at least 90% of the offensive power.',
                'key' => 'furnace_maws',
                'mana_cost' => 6,
                'duration' => 2,
                'races' => collect(['Dragon']),
            ],
            [
                'name' => 'Stasis',
                'description' => 'Freezes time. No production, cannot take actions, and cannot have actions taken against it. Returning units continue their journey home but cannot complete while Stasis is in effect.',
                'key' => 'stasis',
                'mana_cost' => 1.5,
                'duration' => 2,
                'races' => collect(['Qur']),
            ],
            [
                'name' => 'Feral Hunger',
                'description' => '+10% offensive power, no conversions.',
                'key' => 'feral_hunger',
                'mana_cost' => 4,
                'duration' => 6,
                'races' => collect(['Weres']),
            ],
            /*
            [
                'name' => 'Imperial Guard',
                'description' => '+20% defensive power if all units are at home.',
                'key' => 'imperial_guard',
                'mana_cost' => 4,
                'duration' => 6,
                'races' => collect(['Legion']),
            ],
            */
            [
                'name' => 'Shroud',
                'description' => 'Spies are twice as strong on offense and immortal',
                'key' => 'shroud',
                'mana_cost' => 2,
                'duration' => 24,
                'races' => collect(['Spirit']),
            ],
        ]);
    }

    public function getOffensiveSpells(Dominion $dominion, bool $isInvasionSpell = false, bool $isViewOnly = false): Collection
    {

      # Return invasion spells only when specifically asked to.
      if($isInvasionSpell or $isViewOnly)
      {
      return $this->getInfoOpSpells()
          ->merge($this->getBlackOpSpells($dominion, $isViewOnly))
          ->merge($this->getInvasionSpells($dominion, Null, $isViewOnly));
      }
      else
      {
        return $this->getInfoOpSpells()
            ->merge($this->getBlackOpSpells($dominion));
      }
    }

    public function getInfoOpSpells(): Collection
    {
        return collect([
            [
                'name' => 'Clear Sight',
                'description' => 'Reveal status screen',
                'key' => 'clear_sight',
                'mana_cost' => 0.25,
            ],
            [
                'name' => 'Vision',
                'description' => 'Reveal advancements',
                'key' => 'vision',
                'mana_cost' => 0.25,
            ],
            [
                'name' => 'Revelation',
                'description' => 'Reveal active spells',
                'key' => 'revelation',
                'mana_cost' => 0.5,
            ],
//            [
//                'name' => 'Clairvoyance',
//                'description' => 'Reveal realm town crier',
//                'key' => 'clairvoyance',
//                'mana_cost' => 1.2,
//            ],
//            [
//                'name' => 'Disclosure',
//                'description' => 'Reveal wonder',
//                'key' => 'disclosure',
//                'mana_cost' => 1.2,
//            ],
        ]);
    }

    public function getHostileSpells(?Dominion $dominion, bool $isInvasionSpell = false, bool $isViewOnly = false): Collection
    {
        if($isInvasionSpell or $isViewOnly)
        {
          return $this->getBlackOpSpells($dominion)
              ->merge($this->getInvasionSpells($dominion, Null, $isViewOnly));
        }
        else
        {
          return $this->getBlackOpSpells($dominion);
        }
    }

    # Available all the time (after first day).
    public function getBlackOpSpells(?Dominion $dominion, bool $isViewOnly = false): Collection
    {
        $blackOpSpells = collect([
            [
                'name' => 'Plague',
                'description' => 'Slows population growth by 25%.',
                'key' => 'plague',
                'mana_cost' => 3,
                'duration' => 12*2,
            ],
            [
                'name' => 'Insect Swarm',
                'description' => 'Slows food production by 5%.',
                'key' => 'insect_swarm',
                'mana_cost' => 3,
                'duration' => 12*2,
            ],
            [
                'name' => 'Great Flood',
                'description' => 'Slows boat production by 25%.',
                'key' => 'great_flood',
                'mana_cost' => 3,
                'duration' => 12*2,
            ],
            [
                'name' => 'Earthquake',
                'description' => 'Slows ore and diamond mine production by 5%.',
                'key' => 'earthquake',
                'mana_cost' => 3,
                'duration' => 12*2,
            ],
        ]);

        if((isset($dominion) and $dominion->race->name === 'Cult') or $isViewOnly)
        {
            $cultSpells = collect([
                [
                    'name' => 'Enthralling',
                    'description' => 'Some of the units and draftees released by the target join you as Thralls. The units take between 6 and 12 ticks to arrive.',
                    'key' => 'enthralling',
                    'mana_cost' => 1,
                    'duration' => 4,
                ],
                [
                    'name' => 'Treachery',
                    'description' => "The target's spies return some of what they steal to you.",
                    'key' => 'treachery',
                    'mana_cost' => 2,
                    'duration' => 6,
                ],
            ]);

            $blackOpSpells = $blackOpSpells->concat($cultSpells);
        }

        # Formerly War spells
        $warSpells = collect([
            [
                'name' => 'Lightning Bolt',
                'description' => 'Destroy the target\'s improvements (0.20% base damage).',
                'key' => 'lightning_bolt',
                'mana_cost' => 1,
                'decreases' => [
                    'improvement_markets',
                    'improvement_keep',
                    #'improvement_towers',
                    'improvement_spires',
                    'improvement_forges',
                    'improvement_walls',
                    'improvement_harbor',
                    'improvement_armory',
                    'improvement_infirmary',
                    'improvement_workshops',
                    'improvement_observatory',
                    'improvement_cartography',
                    'improvement_hideouts',
                    'improvement_forestry',
                    'improvement_refinery',
                    'improvement_granaries',
                    'improvement_tissue',
                ],
                'percentage' => 0.20,
                'max_damage_per_wizard' => 10,
            ],
            [
                'name' => 'Fireball',
                'description' => 'Burn target\'s peasants and food (0.50% base damage).',
                'key' => 'fireball',
                'mana_cost' => 1,
                'decreases' => ['peasants', 'resource_food'],
                'percentage' => 0.50,
                'max_damage_per_wizard' => 10,
            ],
            [
                'name' => 'Disband Spies',
                'description' => 'Disband target\'s spies (1% base damage).',
                'key' => 'disband_spies',
                'mana_cost' => 1,
                'decreases' => ['military_spies'],
                'percentage' => 1,
            ],
        ]);

        $blackOpSpells = $blackOpSpells->concat($warSpells);

        return $blackOpSpells;

    }

    /*
    *
    * These spells are automatically cast during invasion based on conditions:
    * - Type: is $dominion the attacker or defender?
    * - Invasion successful? True (must be successful), False (must be unsuccessful), or Null (can be either).
    * - OP relative to DP? Null = not checked. Float = OP/DP must be this float or greater.
    *
    * @param Dominion $dominion - the caster
    * @param Dominion $target - the target
    *
    */
    public function getInvasionSpells(Dominion $dominion, ?Dominion $target = Null, bool $isViewOnly = false): Collection
    {
        if($dominion->race->name == 'Afflicted' or $isViewOnly)
        {
          return collect([
              [
                  'name' => 'Pestilence',
                  'description' => 'Peasants die and return to the Afflicted as Abominations.',
                  'key' => 'pestilence',
                  'type' => 'offense',
                  'invasion_must_be_successful' => Null,
                  'op_dp_ratio' => 0.50,
                  'duration' => 12,
                  'mana_cost' => 0,
              ],
              [
                  'name' => 'Great Fever',
                  'description' => 'No population growth, -10% platinum production, -20% food production.',
                  'key' => 'great_fever',
                  'type' => 'offense',
                  'invasion_must_be_successful' => True,
                  'op_dp_ratio' => Null,
                  'duration' => 12,
                  'mana_cost' => 0,
              ],
              [
                  'name' => 'Unhealing Wounds',
                  'description' => '+50% casualties, +15% food consumption.',
                  'key' => 'unhealing_wounds',
                  'type' => 'defense',
                  'invasion_must_be_successful' => Null,
                  'op_dp_ratio' => Null,
                  'duration' => 12,
                  'mana_cost' => 0,
              ],
          ]);
        }
        else
        {
          return collect([]);
        }
    }

    # ROUND 37

    public function getSpellClass(Spell $spell)
    {
        $classes = [
            'active'  => 'Impact',
            'passive' => 'Aura',
            'invasion'=> 'Invasion',
            'info'    => 'Information'
        ];

        return $classes[$spell->class];
    }

    public function getSpellScope(Spell $spell)
    {
        $scopes = [
            'self'      => 'Self',
            'friendly'  => 'Friendly',
            'hostile'   => 'Hostile'
        ];

        return $scopes[$spell->scope];
    }

    public function getSpellEffectsString(Spell $spell): array
    {

        $effectStrings = [];

        $spellEffects = [

            // Info
            'clear_sight' => 'Reveal status screen',
            'vision' => 'Reveal advancements',
            'revelation' => 'Reveal active spells',

            // Production
            'ore_production' => '%s%% ore production',
            'mana_production' => '%s%% mana production',
            'lumber_production' => '%s%% lumber production',
            'food_production' => '%s%% food production',
            'gem_production' => '%s%% gem production',
            'platinum_production' => '%s%% platinum production',
            'boat_production' => '%s%% boat production',
            'tech_production' => '%s%% XP generation',

            'alchemy_production' => '+%s platinum production per alchemy',

            'food_production_raw' => '%s%% raw food production',

            'food_production_docks' => '%s%% food production from Docks',

            // Military
            'drafting' => '+%s%% drafting',
            'training_time' => '%s ticks training time for military units (does not include Spies, Wizards, or Archmages)',
            'training_costs' => '+%s%% unit training costs',

            'additional_units_trained_from_land' => '1%% extra %1$s%% for every %3$s%% %2$s.',

            'faster_return' => 'Units return %s ticks faster from invasions',

            'increase_morale' => 'Restores target morale by %s%% (up to maximum of 100%%).',
            'decrease_morale' => 'Lowers target morale by %s%% (minimum 0%%).',


            'kills_draftees' => 'Kills %1$s%% of the target\'s draftees.',

            'kills_faction_units_percentage' => 'Kills %3$s%% of %1$s %2$s.',
            'kills_faction_units_amount' => 'Kills %3$s%s of %1$s %2$s.',

            'cannot_send_boats' => 'Cannot send boats.',
            'boats_sunk' => '%s%% boats lost to sinking.',

            'summon_units_from_land' => 'Summon up to %2$s %1$s per acre of %3$s.',

            // Improvements
            'improvements_damage' => 'Destroys %s%% of the target\'s improvements.',

            // Population
            'population_growth' => '%s%% population growth rate',
            'kills_peasants' => 'Kills %1$s%% of the target\'s peasants.',

            // Resources
            'destroys_resource' => 'Destroys %2$s%% of the target\'s %1$s.',

            'resource_conversion' => 'Converts %3$s%% of your %1$s to %2$s at a rate of %4$s:1.',

            // Magic
            'damage_from_spells' => '%s%% damage from spells',
            'chance_to_reflect_spells' => '%s%% chance to reflect spells',
            'reveal_ops' => 'Reveals the dominion casting spells or spying on you',
            'damage_from_fireballs' => '%s%% damage from fireballs',
            'damage_from_lightning_bolts' => '%s%% damage from lightning bolts',

            // Espionage
            'disband_spies' => 'Disbands %s%% of enemy spies.',
            'spy_strength' => '%s%% spy strength',
            'immortal_spies' => 'Spies become immortal',

            'platinum_theft' => '%s%% platinum lost to theft.',
            'mana_theft' => '%s%% mana lost to theft.',
            'lumber_theft' => '%s%% lumber lost to theft.',
            'ore_theft' => '%s%% ore lost to theft.',
            'gems_theft' => '%s%% gems lost to theft.',
            'all_theft' => '%s%% resources lost to theft',

            // Conversions
            'conversions' => '%s%% conversions',
            'converts_crypt_bodies' => 'Every %1$s %2$s raise %3$s %4$s from the Crypt per tick (limited to bodies available in the Crypt).',
            'convert_enemy_casualties_to_food' => 'Enemy casualties converted to food.',
            'no_conversions' => 'No enemy units are converted.',

            // Casualties
            'increases_enemy_draftee_casualties' => '%s%% enemy draftee casualties',
            'increases_casualties_on_offense' => '%s%% enemy casualties when invading',
            'increases_casualties_on_defense' => '%s%% enemy casualties when defending',

            'casualties' => '%s%% casualties',
            'offensive_casualties' => '%s%% casualties suffered when invading',
            'defensive_casualties' => '%s%% casualties suffered when defending',

            // OP/DP
            'offensive_power' => '%s%% offensive power',
            'defensive_power' => '%s%% defensive power',

            'offensive_power_on_retaliation' => '%s%% offensive power if target recently invaded your realm',

            'defensive_power_vs_insect_swarm' => '%s%% offensive power if attacker has Insect Swarm',

            'reduces_target_raw_defense_from_land' => 'Targets raw defensive power lowered by %1$s%% for every %3$s%% forest, max %4$s%% reduction ',# 1,5,forest,10 # -1% raw DP, per 5% forest, max -10%

            'increases_casualties_on_offense_from_wizard_ratio' => 'Enemy casualties increased by %s%% for every 1 wizard ratio.',

            'immune_to_temples' => 'Defensive modifiers are not affected by Temples.',

            // Improvements
            'improvements' => '%s%% improvement points from investments made while spell is active',

            // Explore
            'land_discovered' => '%s%% land discovered on successful invasions',
            'stop_land_generation' => 'Stops land generation from units',

            // Special
            'opens_portal' => 'Opens a portal required to teleport otherwordly units to enemy lands',

            'burns_extra_buildings' => 'Destroy up to 10%% additional buildings when successfully invading someone, if buildings are built with lumber. Dragons must account for at least 90%% of the offensive power.',

            'stasis' => 'Freezes time. No production, cannot take actions, and cannot have actions taken against it. Units returning from battle continue to return but do not finish and arrive home until Stasis is over.',

            'mind_control' => 'When defending, each Mystic takes control of one invading unit\'s mind. Mindcontrolled units provide 2 raw DP. Only units which have the attribute Sentient and neither of the attributes Ammunition, Equipment, Magical, Massive, Mechanical, Mindless, Ship, or Wise can be mindcontrolled.',

        ];

        foreach ($spell->perks as $perk)
        {
            if (!array_key_exists($perk->key, $spellEffects))
            {
                //\Debugbar::warning("Missing perk help text for unit perk '{$perk->key}'' on unit '{$unit->name}''.");
                continue;
            }

            $perkValue = $perk->pivot->value;

            // Handle array-based perks
            $nestedArrays = false;

            // todo: refactor all of this
            // partially copied from Race::getUnitPerkValueForUnitSlot
            if (str_contains($perkValue, ','))
            {
                $perkValue = explode(',', $perkValue);

                foreach ($perkValue as $key => $value)
                {
                    if (!str_contains($value, ';'))
                    {
                        continue;
                    }

                    $nestedArrays = true;
                    $perkValue[$key] = explode(';', $value);
                }
            }

            // Special case for pairings
            if ($perk->key === 'defense_from_pairing' || $perk->key === 'offense_from_pairing' || $perk->key === 'pairing_limit')
            {
                $slot = (int)$perkValue[0];
                $pairedUnit = $race->units->filter(static function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $perkValue[0] = $pairedUnit->name;
                if (isset($perkValue[2]) && $perkValue[2] > 0)
                {
                    $perkValue[0] = str_plural($perkValue[0]);
                }
                else
                {
                    $perkValue[2] = 1;
                }
            }

            // Special case for returns faster if pairings
            if ($perk->key === 'faster_return_if_paired')
            {
                $slot = (int)$perkValue[0];
                $pairedUnit = $race->units->filter(static function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $perkValue[0] = $pairedUnit->name;
                if (isset($perkValue[2]) && $perkValue[2] > 0)
                {
                    $perkValue[0] = str_plural($perkValue[0]);
                }
                else
                {
                    $perkValue[2] = 1;
                }
            }

            // Special case for pairing_limit_increasable
            if ($perk->key === 'pairing_limit_increasable')
            {
                $slot = (int)$perkValue[0];
                $pairedUnit = $race->units->filter(static function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $perkValue[0] = $pairedUnit->name;
            }

            // Special case for conversions
            if ($perk->key === 'conversion' or $perk->key === 'displaced_peasants_conversion' or $perk->key === 'casualties_conversion')
            {
                $unitSlotsToConvertTo = array_map('intval', str_split($perkValue));
                $unitNamesToConvertTo = [];

                foreach ($unitSlotsToConvertTo as $slot) {
                    $unitToConvertTo = $race->units->filter(static function ($unit) use ($slot) {
                        return ($unit->slot === $slot);
                    })->first();

                    $unitNamesToConvertTo[] = str_plural($unitToConvertTo->name);
                }

                $perkValue = generate_sentence_from_array($unitNamesToConvertTo);
            }
            if($perk->key === 'staggered_conversion')
            {
                foreach ($perkValue as $index => $conversion) {
                    [$convertAboveLandRatio, $slots] = $conversion;

                    $unitSlotsToConvertTo = array_map('intval', str_split($slots));
                    $unitNamesToConvertTo = [];

                    foreach ($unitSlotsToConvertTo as $slot) {
                        $unitToConvertTo = $race->units->filter(static function ($unit) use ($slot) {
                            return ($unit->slot === $slot);
                        })->first();

                        $unitNamesToConvertTo[] = str_plural($unitToConvertTo->name);
                    }

                    $perkValue[$index][1] = generate_sentence_from_array($unitNamesToConvertTo);
                }
            }
            if($perk->key === 'strength_conversion')
            {
                $limit = (float)$perkValue[0];
                $under = (int)$perkValue[1];
                $over = (int)$perkValue[2];

                $underLimitUnit = $race->units->filter(static function ($unit) use ($under)
                    {
                        return ($unit->slot === $under);
                    })->first();

                $overLimitUnit = $race->units->filter(static function ($unit) use ($over)
                    {
                        return ($unit->slot === $over);
                    })->first();

                $perkValue = [$limit, str_plural($underLimitUnit->name), str_plural($overLimitUnit->name)];
            }
            if($perk->key === 'passive_conversion')
            {
                $slotFrom = (int)$perkValue[0];
                $slotTo = (int)$perkValue[1];
                $rate = (float)$perkValue[2];
                $building = (string)$perkValue[3];

                $unitFrom = $race->units->filter(static function ($unit) use ($slotFrom)
                    {
                        return ($unit->slot === $slotFrom);
                    })->first();

                $unitTo = $race->units->filter(static function ($unit) use ($slotTo)
                    {
                        return ($unit->slot === $slotTo);
                    })->first();

                $perkValue = [$unitFrom->name, $unitTo->name, $rate, $building];
            }
            if($perk->key === 'value_conversion')
            {
                $multiplier = (float)$perkValue[0];
                $convertToSlot = (int)$perkValue[1];

                $unitToConvertTo = $race->units->filter(static function ($unit) use ($convertToSlot)
                    {
                        return ($unit->slot === $convertToSlot);
                    })->first();

                $perkValue = [$multiplier, str_plural($unitToConvertTo->name)];
            }

            if($perk->key === 'plunders')
            {
                foreach ($perkValue as $index => $plunder) {
                    [$resource, $amount] = $plunder;

                    $perkValue[$index][1] = generate_sentence_from_array([$amount]);
                }
            }

            // Special case for dies_into, wins_into ("change_into"), fends_off_into
            if ($perk->key === 'dies_into' or $perk->key === 'wins_into' or $perk->key === 'fends_off_into')
            {
                $unitSlotsToConvertTo = array_map('intval', str_split($perkValue));
                $unitNamesToConvertTo = [];

                foreach ($unitSlotsToConvertTo as $slot) {
                    $unitToConvertTo = $race->units->filter(static function ($unit) use ($slot) {
                        return ($unit->slot === $slot);
                    })->first();

                    $unitNamesToConvertTo[] = $unitToConvertTo->name;
                }

                $perkValue = generate_sentence_from_array($unitNamesToConvertTo);
            }

            // Special case for returns faster if pairings
            if ($perk->key === 'dies_into_multiple')
            {
                $slot = (int)$perkValue[0];
                $pairedUnit = $race->units->filter(static function ($unit) use ($slot) {
                    return ($unit->slot === $slot);
                })->first();

                $amount = (int)$perkValue[1];

                $perkValue[0] = $pairedUnit->name;
                if (isset($perkValue[1]) && $perkValue[1] > 0)
                {
                    $perkValue[0] = str_plural($perkValue[0]);
                }
                else
                {
                    $perkValue[1] = 1;
                }
            }

            // Special case for unit_production
            if ($perk->key === 'unit_production')
            {
                $unitSlotToProduce = intval($perkValue[0]);

                $unitToProduce = $race->units->filter(static function ($unit) use ($unitSlotToProduce) {
                    return ($unit->slot === $unitSlotToProduce);
                })->first();

                $unitNameToProduce[] = str_plural($unitToProduce->name);

                $perkValue = generate_sentence_from_array($unitNameToProduce);
            }


            /*****/

            if($perk->key === 'kills_faction_units_percentage' or $perk->key === 'kills_faction_units_amount')
            {
                $faction = (string)$perkValue[0];
                $slot = (int)$perkValue[1];
                $percentage = (float)$perkValue[2];

                $race = Race::where('name', $faction)->first();

                $unit = $race->units->filter(static function ($unit) use ($slot)
                    {
                        return ($unit->slot === $slot);
                    })->first();

                $perkValue = [$faction, str_plural($unit->name), $percentage];
            }

            if($perk->key === 'summon_units_from_land')
            {
                $unitSlots = (array)$perkValue[0];
                $maxPerAcre = (float)$perkValue[1];
                $landType = (string)$perkValue[2];

                // Rue the day this perk is used for other factions.
                $race = Race::where('name', 'Weres')->firstOrFail();

                foreach ($unitSlots as $index => $slot)
                {
                    $slot = (int)$slot;
                    $unit = $race->units->filter(static function ($unit) use ($slot)
                        {
                            return ($unit->slot === $slot);
                        })->first();


                    $units[$index] = str_plural($unit->name);
                }

                $unitsString = generate_sentence_from_array($units);

                $perkValue = [$unitsString, $maxPerAcre, $landType];
                $nestedArrays = false;

            }

            if($perk->key === 'converts_crypt_bodies')
            {
                $race = Race::where('name', 'Undead')->firstOrFail();

                $performingUnits = (int)$perkValue[0];
                $performingUnitSlot = (int)$perkValue[1];
                $unitsCreated = (int)$perkValue[2];
                $unitCreatedSlot = (int)$perkValue[3];

                # Get the performing unit
                $performingUnit = $race->units->filter(static function ($unit) use ($performingUnitSlot)
                        {
                            return ($unit->slot === $performingUnitSlot);
                        })->first();

                # Get the performing unit
                $createdUnit = $race->units->filter(static function ($unit) use ($unitCreatedSlot)
                        {
                            return ($unit->slot === $unitCreatedSlot);
                        })->first();
                #$unitsString = generate_sentence_from_array([$createdUnit, $createdUnit]);

                $perkValue = [$performingUnits, $performingUnit->name, $unitsCreated, $createdUnit->name];

                #$perkValue = [$unitsString, $maxPerAcre, $landType];
            }



            /*****/

            if (is_array($perkValue))
            {
                if ($nestedArrays)
                {
                    foreach ($perkValue as $nestedKey => $nestedValue)
                    {
                        foreach($nestedValue as $key => $value)
                        {
                            $nestedValue[$key] = ucwords(str_replace('level','level ',str_replace('_', ' ',$value)));
                        }
                        $effectStrings[] = vsprintf($spellEffects[$perk->key], $nestedValue);
                    }
                }
                else
                {
                    #var_dump($perkValue);
                    foreach($perkValue as $key => $value)
                    {
                        $perkValue[$key] = ucwords(str_replace('_', ' ',$value));
                    }
                    $effectStrings[] = vsprintf($spellEffects[$perk->key], $perkValue);
                }
            }
            else
            {
                $perkValue = str_replace('_', ' ',ucwords($perkValue));
                $effectStrings[] = sprintf($spellEffects[$perk->key], $perkValue);
            }
        }

        return $effectStrings;
    }

    public function getExclusivityString(Spell $spell): string
    {

        $exclusivityString = '<br><small class="text-muted">';

        if($exclusives = count($spell->exclusive_races))
        {
            foreach($spell->exclusive_races as $raceName)
            {
                $exclusivityString .= $raceName;
                if($exclusives > 1)
                {
                    $exclusivityString .= ', ';
                }
                $exclusives--;
            }

            $exclusivityString .= ' only';
        }
        elseif($excludes = count($spell->excluded_races))
        {
            $exclusivityString .= 'All except ';
            foreach($spell->excluded_races as $raceName)
            {
                $exclusivityString .= $raceName;
                $exclusives--;
            }
        }

        $exclusivityString .= '</small>';

        return $exclusivityString;

    }

}
