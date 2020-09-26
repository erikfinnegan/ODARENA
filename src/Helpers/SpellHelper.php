<?php

namespace OpenDominion\Helpers;

use Illuminate\Support\Collection;
use OpenDominion\Models\Race;
# ODA
use OpenDominion\Models\Dominion;


class SpellHelper
{

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

    public function isWarSpell(string $spellKey, Dominion $dominion): bool
    {
        return $this->getWarSpells($dominion)->merge($this->getRacialWarSpells($dominion))->filter(function ($spell) use ($spellKey) {
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
                'name' => 'Gaia\'s Watch',
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
                'description' => 'Platinum theft protection for 40 ticks, 22 hour recharge',
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
                'duration' => 8*4,# * $this->militaryCalculator->getWizardRatio($target, 'defense'),
                'cooldown' => 0,
            ],
            [
                'name' => 'Energy Mirror',
                'description' => '20% chance to reflect incoming offensive spells for 8 hours',
                'key' => 'energy_mirror',
                'mana_cost' => 3,
                'duration' => 8*4,
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
                'races' => collect(['Firewalker','Spirit']),
            ],
            [
                'name' => 'Blizzard',
                'description' => '+5% defensive strength',
                'key' => 'blizzard',
                'mana_cost' => 8,
                'duration' => 12*4,
                'races' => collect(['Icekin', 'Snow Elf']),
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
                'description' => 'Double raw food production.',
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
            [
                'name' => 'Coastal Cannons',
                'description' => '+1% Defensive Power for every 1% Water. Max +20%.',
                'key' => 'coastal_cannons',
                'mana_cost' => 10,
                'duration' => 12*4,
                'races' => collect(['Armada']),
            ],
            [
                'name' => 'Spiral Architecture',
                'description' => '+10% value for investments into castle improvements performed when active.',
                'key' => 'spiral_architecture',
                'mana_cost' => 8,
                'duration' => 12*4,
                'races' => collect(['Imperial Gnome']),
            ],
            [
                'name' => 'Fimbulwinter',
                'description' => '+10% Defensive Power, +15% casualties.',
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
            [
                'name' => 'Gryphon\'s Call',
                'description' => 'Quadruple yeti trapping. No offensive power bonus from Gryphon Nests.',
                'key' => 'gryphons_call',
                'mana_cost' => 2,
                'duration' => 24,
                'races' => collect(['Yeti']),
            ],
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
                'name' => 'Call To Arms',
                'description' => 'Training times reduced by 2 for every recent invasion (max -8 ticks).',
                'key' => 'call_to_arms',
                'mana_cost' => 10,
                'duration' => 12*4,
                'races' => collect(['Legion II']),
                #'races' => collect(['Legion', 'Legion II', 'Legion III', 'Legion IV', 'Legion V', 'Legion VI']),
            ],
            */
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
            /*
            [
                'name' => 'Chitin',
                'description' => 'Cocoons receive 1 DP each. Unaffected by Unholy Ghost or Dragon\'s Roar.',
                'key' => 'chitin',
                'mana_cost' => 10,
                'duration' => 12*4,
                'races' => collect(['Swarm']),
            ],
            */
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
                'mana_cost' => 12,
                'duration' => 12,
                'cooldown' => 36, # 36 hours, 1.5 days
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
                'description' => 'Taking bodies from the Imperial Crypt, every ten Wraith turn up to one body into a Skeleton and every ten Revenants turn up to one body in a Ghoul, until the spell expires or until there are no bodies left in the crypt. Only Wraiths and Reverents currently at home perform Dark Rites.',
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
        ]);
    }

    public function getOffensiveSpells(Dominion $dominion, bool $isInvasionSpell = false, bool $isViewOnly = false): Collection
    {

      # Return invasion spells only when specifically asked to.
      if($isInvasionSpell or $isViewOnly)
      {
      return $this->getInfoOpSpells()
          ->merge($this->getBlackOpSpells($dominion, $isViewOnly))
          ->merge($this->getWarSpells($dominion, $isViewOnly))
          ->merge($this->getInvasionSpells($dominion, Null, $isViewOnly));
      }
      else
      {
        return $this->getInfoOpSpells()
            ->merge($this->getBlackOpSpells($dominion))
            ->merge($this->getWarSpells($dominion))
            ->merge($this->getRacialWarSpells($dominion));
      }
    }

    public function getInfoOpSpells(): Collection
    {
        return collect([
            [
                'name' => 'Clear Sight',
                'description' => 'Reveal status screen',
                'key' => 'clear_sight',
                'mana_cost' => 0.3,
            ],
            [
                'name' => 'Vision',
                'description' => 'Reveal advancements',
                'key' => 'vision',
                'mana_cost' => 0.5,
            ],
            [
                'name' => 'Revelation',
                'description' => 'Reveal active spells',
                'key' => 'revelation',
                'mana_cost' => 0.75,
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
              ->merge($this->getWarSpells($dominion))
              ->merge($this->getInvasionSpells($dominion, Null, $isViewOnly));
        }
        else
        {
          return $this->getBlackOpSpells($dominion)
              ->merge($this->getWarSpells($dominion))
              ->merge($this->getRacialWarSpells($dominion));
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

        return $blackOpSpells;

    }

    # War only.
    public function getWarSpells(?Dominion $dominion): Collection
    {
        $spells = collect([
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

        return $spells;
    }


    public function getRacialWarSpells(): Collection
    {
        return collect([
            [
                'name' => 'Purification',
                'description' => 'Eradicates Abominations. Only effective against the Afflicted.',
                'key' => 'purification',
                'mana_cost' => 3,
                'decreases' => [
                    'military_unit1',
                ],
                'duration' => NULL,
                'percentage' => 1,
                'races' => collect(['Sacred Order', 'Sylvan']),
            ],
            [
                'name' => 'Solar Flare',
                'description' => 'Eradicates Imps. Only effective against the Nox.',
                'key' => 'solar_flare',
                'mana_cost' => 3,
                'decreases' => [
                    'military_unit1',
                ],
                'duration' => NULL,
                'percentage' => 5,
                'races' => collect(['Lux']),
            ],
            [
                'name' => 'Proselytize',
                'description' => 'Converts some of targets units to join you',
                'key' => 'proselytize',
                'mana_cost' => 0.5,
                'duration' => NULL,
                'percentage' => 5,
                'races' => collect(['Cult']),
            ],
            [
                'name' => 'Solar Eclipse',
                'description' => '-20% food production, -20% mana production',
                'key' => 'solar_eclipse',
                'mana_cost' => 3,
                'duration' => 12*3,
                'percentage' => NULL,
                'races' => collect(['Nox']),
            ],
            [
                'name' => 'Frozen Shores',
                'description' => 'Freezes water and target cannot send out boats. No food production from docks.',
                'key' => 'frozen_shores',
                'mana_cost' => 6,
                'duration' => 4,
                'percentage' => NULL,
                'races' => collect(['Icekin']),
            ],
            [
                'name' => 'Pyroclast',
                'description' => 'Twice as affective as a Fireball and leaves a lingering forest fire reducing lumber production.',
                'key' => 'pyroclast',
                'mana_cost' => 3,
                'duration' => 6,
                'decreases' => [
                    'peasants',
                    'resource_food'],
                'percentage' => 1,
                'races' => collect(['Firewalker']),
            ],
          ]);
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

    /*
    *
    * These spells can be cast on friendly dominions:
    *
    * @param Dominion $dominion - the caster
    * @param Dominion $target - the target
    *
    */
    public function getFriendlySelfSpells(): Collection
    {
        return collect([
            [
                'name' => 'Magical Aura',
                'description' => '-10% casualties, +25% population growth rate',
                'key' => 'magical_aura',
                'mana_cost' => 5,
                'duration' => 12*2,
                'cooldown' => 12*4,
                'races' => collect(['Sacred Order', 'Sylvan']),
            ],
            [
                'name' => 'Iceshield',
                'description' => '-25% damage from fireballs and lightning bolts',
                'key' => 'iceshield',
                'mana_cost' => 5,
                'duration' => 12*2,
                'cooldown' => 0,
                'races' => collect(['Icekin']),
            ],
          ]);
    }

}
