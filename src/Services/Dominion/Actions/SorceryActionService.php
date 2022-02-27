<?php

namespace OpenDominion\Services\Dominion\Actions;

use DB;
use Exception;
use LogicException;
use Illuminate\Support\Carbon;
use OpenDominion\Calculators\Dominion\ImprovementCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\PopulationCalculator;
use OpenDominion\Calculators\Dominion\RangeCalculator;
use OpenDominion\Calculators\Dominion\ResourceCalculator;
use OpenDominion\Calculators\Dominion\SorceryCalculator;
use OpenDominion\Calculators\Dominion\SpellCalculator;
use OpenDominion\Calculators\NetworthCalculator;
use OpenDominion\Exceptions\GameException;
use OpenDominion\Helpers\OpsHelper;
use OpenDominion\Helpers\RaceHelper;
use OpenDominion\Helpers\LandHelper;
use OpenDominion\Helpers\SpellHelper;
use OpenDominion\Helpers\ImprovementHelper;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\GameEvent;
use OpenDominion\Models\Improvement;
use OpenDominion\Models\Resource;
use OpenDominion\Models\Spell;
use OpenDominion\Models\Tech;
use OpenDominion\Models\Unit;

use OpenDominion\Services\Dominion\HistoryService;
use OpenDominion\Services\Dominion\ProtectionService;
use OpenDominion\Services\Dominion\ResourceService;
use OpenDominion\Services\Dominion\StatsService;
use OpenDominion\Services\Dominion\QueueService;
use OpenDominion\Services\NotificationService;
use OpenDominion\Traits\DominionGuardsTrait;

use OpenDominion\Calculators\Dominion\SpellDamageCalculator;

class SorceryActionService
{
    public function __construct()
    {
        $this->improvementCalculator = app(ImprovementCalculator::class);
        $this->landCalculator = app(LandCalculator::class);
        $this->militaryCalculator = app(MilitaryCalculator::class);
        $this->networthCalculator = app(NetworthCalculator::class);
        $this->notificationService = app(NotificationService::class);
        $this->landHelper = app(LandHelper::class);
        $this->opsHelper = app(OpsHelper::class);
        $this->populationCalculator = app(PopulationCalculator::class);
        $this->protectionService = app(ProtectionService::class);
        $this->queueService = app(QueueService::class);
        $this->raceHelper = app(RaceHelper::class);
        $this->improvementHelper = app(ImprovementHelper::class);
        $this->rangeCalculator = app(RangeCalculator::class);
        $this->resourceCalculator = app(ResourceCalculator::class);
        $this->resourceService = app(ResourceService::class);
        $this->sorceryCalculator = app(SorceryCalculator::class);
        $this->spellCalculator = app(SpellCalculator::class);
        $this->spellHelper = app(SpellHelper::class);
        $this->spellDamageCalculator = app(SpellDamageCalculator::class);
        $this->statsService = app(StatsService::class);
    }

    public function performSorcery(Dominion $caster, Dominion $target, Spell $spell, int $wizardStrength, Resource $enhancementResource = null, int $enhancementAmount = 0): array
    {
        DB::transaction(function () use ($caster, $target, $spell, $wizardStrength, $enhancementResource, $enhancementAmount)
        {
            # BEGIN VALIDATION
            if(!$this->sorceryCalculator->canPerformSorcery($caster))
            {
                throw new GameException('Your wizards are too weak to perform sorcery.');
            }

            if(isset($target) and $target->getSpellPerkValue('stasis'))
            {
                throw new GameException('A magical stasis surrounds the Qurrian lands, making it impossible for your wizards to cast spells on them.');
            }
            if($caster->getSpellPerkValue('stasis'))
            {
                throw new GameException('You cannot cast spells while you are in stasis.');
            }
            if($caster->protection_ticks !== 0)
            {
                throw new GameException('You cannot perform sorcery while in protection.');
            }

            if ($spell->enabled !== 1)
            {
                throw new LogicException("Spell {$spell->name} is not enabled.");
            }

            if (!$this->spellCalculator->canCastSpell($caster, $spell))
            {
                throw new GameException("You are not able to cast {$spell->name}.");
            }

            if ($caster->wizard_strength <= 0 or ($caster->wizard_strength - $wizardStrength) < 0)
            {
                throw new GameException("Your wizards are too weak to perform such sorcery. You would need {$wizardStrength}% wizard strength but only have {$caster->wizard_strength}%.");
            }

            $manaCost = $this->sorceryCalculator->getSorcerySpellManaCost($caster, $spell, $wizardStrength);
            $casterManaAmount = $this->resourceCalculator->getAmount($caster, 'mana');

            if ($manaCost > $casterManaAmount)
            {
                throw new GameException("You do not have enough mana to perform such sorcery. You would need " . number_format($manaCost) . " mana but only have " . number_format($casterManaAmount) . ".");
            }

            if ($target === null)
            {
                throw new GameException("You must select a target when performing sorcery.");
            }

            if ($this->protectionService->isUnderProtection($caster))
            {
                throw new GameException("You cannot perform sorcery while under protection");
            }

            if ($this->protectionService->isUnderProtection($target))
            {
                throw new GameException("You cannot perform sorcery against targets under protection");
            }

            if (!$this->rangeCalculator->isInRange($caster, $target) and $spell->class !== 'invasion')
            {
                throw new GameException("You cannot cast spells on targets not in your range");
            }

            if ($caster->id === $target->id)
            {
                throw new GameException("You cannot perform sorcery on yourself");
            }

            if ($caster->realm->id === $target->realm->id and ($caster->round->mode == 'standard' or $caster->round->mode == 'standard-duration'))
            {
                throw new GameException("You cannot perform sorcery on other dominions in your realm in standard rounds");
            }

            if ($caster->round->id !== $target->round->id)
            {
                throw new GameException('Nice try, but you cannot cast spells cross-round');
            }
            # END VALIDATION

            $this->sorcery = [
                'class' => $spell->class,
                'spell_key' => $spell->key,
                'caster' => [
                        'enhancement_resource' => $enhancementResource,
                        'enhancement_amount' => $enhancementResource,
                        'fog' => $caster->getSpellPerkValue('fog_of_war') ? true : false,
                        'mana_cost' => $manaCost,
                        'mana_current' => $casterManaAmount,
                        'wizard_strength_current' => $caster->wizard_strength,
                        'wizard_strength_spent' => $wizardStrength,
                        'wizard_ratio' => $this->militaryCalculator->getWizardRatio($caster, 'offense')
                    ],
                'target' => [
                        'crypt_bodies' => 0,
                        'fog' => $target->getSpellPerkValue('fog_of_war') ? true : false,
                        'reveal_ops' => $target->getSpellPerkValue('reveal_ops') ? true : false,
                        'wizard_strength_current' => $target->wizard_strength,
                        'wizard_ratio' => $this->militaryCalculator->getWizardRatio($target, 'defense')
                    ],
                'damage' => [],
            ];

            // Surreal Perception
            $sourceDominionId = null;
            if ($this->sorcery['target']['reveal_ops'])
            {
                $sourceDominionId = $caster->id;
            }

            if($spell->class == 'passive')
            {
                # NOT FINISHED

                $duration = $this->sorceryCalculator->getSorcerySpellDuration($caster, $target, $spell, $enhancementResource, $enhancementAmount);

                $this->statsService->updateStat($caster, 'magic_sorcery_success', 1);
                $this->statsService->updateStat($caster, 'magic_sorcery_duration', $duration);

                if ($this->spellCalculator->isSpellActive($target, $spell->key))
                {
                    DB::transaction(function () use ($caster, $target, $spell)
                    {
                        $dominionSpell = DominionSpell::where('dominion_id', $target->id)->where('spell_id', $spell->id)
                        ->update(['duration' => $duration]);

                        $target->save([
                            'event' => HistoryService::EVENT_ACTION_CAST_SPELL,
                            'action' => $spell->key
                        ]);
                    });
                }
                else
                {
                    DB::transaction(function () use ($caster, $target, $spell)
                    {
                        DominionSpell::create([
                            'dominion_id' => $target->id,
                            'caster_id' => $caster->id,
                            'spell_id' => $spell->id,
                            'duration' => $duration
                        ]);

                        $caster->save([
                            'event' => HistoryService::EVENT_ACTION_CAST_SPELL,
                            'action' => $spell->key
                        ]);
                    });
                }
                $this->notificationService
                    ->queueNotification('received_hostile_spell', [
                        'sourceDominionId' => $caster->id,
                        'spellKey' => $spell->key,
                    ])
                    ->sendNotifications($target, 'irregular_dominion');
            }
            elseif($spell->class == 'active')
            {
                dump($caster->name . ' is casting ' . $spell->name. ' at ' . $target->name . ', costing ' . number_format($manaCost) . ' mana, having ' . number_format($casterManaAmount) . '.');

                foreach($spell->perks as $perk)
                {
                    $spellPerkValues = $spell->getActiveSpellPerkValues($spell->key, $perk->key);

                    if($perk->key === 'kill_peasants')
                    {
                        $baseDamage = (float)$spellPerkValues / 100;
                        $sorcerySpellDamageMultiplier = $this->sorceryCalculator->getSorcerySpellDamageMultiplier($caster, $target, $spell, $wizardStrength, $enhancementResource, $enhancementAmount, $perk->key);
                        $spellDamageMultiplier = $this->spellDamageCalculator->getDominionHarmfulSpellDamageModifier($target, $caster, $spell, 'draftees');

                        $damage = $baseDamage * $sorcerySpellDamageMultiplier * $spellDamageMultiplier;

                        $damageDealt = min($target->peasants * $damage, $target->peasants);
                        $damageDealt = floor($damageDealt);

                        $target->peasants -= $damageDealt;
                        #$result[] = sprintf('%s %s', number_format($damageDealt), str_plural($this->raceHelper->getPeasantsTerm($target->race), $damage));

                        $this->statsService->updateStat($caster, 'magic_peasants_killed', $damageDealt);
                        $this->statsService->updateStat($target, 'magic_peasants_lost', $damageDealt);

                        # For Empire, add killed draftees go in the crypt
                        if($target->realm->alignment === 'evil')
                        {
                            $target->realm->crypt += $damageDealt;
                            $this->sorcery['target']['crypt_bodies'] += $damageDealt;
                        }

                        $this->sorcery['damage'][$perk->key] = [
                            'sorcery_spell_damage_multiplier' => $sorcerySpellDamageMultiplier,
                            'spell_damage_multiplier' => $spellDamageMultiplier,
                            'damage' => $damage,
                            'damage_dealt' => $damageDealt,
                            'peasants' => $target->peasants,
                        ];

                        $verb = 'kills';
                    }

                    if($perk->key === 'kill_draftees')
                    {
                        $baseDamage = (float)$spellPerkValues / 100;
                        $sorcerySpellDamageMultiplier = $this->sorceryCalculator->getSorcerySpellDamageMultiplier($target, $caster, $spell, $wizardStrength, $enhancementResource, $enhancementAmount, $perk->key);
                        $damageMultiplier = $this->spellDamageCalculator->getDominionHarmfulSpellDamageModifier($target, $caster, $spell, 'draftees');

                        $damage = $baseDamage * $sorcerySpellDamageMultiplier * $spellDamageMultiplier;

                        $damageDealt = min($target->peasants * $damage, $target->peasants);
                        $damageDealt = floor($damageDealt);

                        $target->peasants -= $damageDealt;
                        #$result[] = sprintf('%s %s', number_format($damage), str_plural($this->raceHelper->getPeasantsTerm($target->race), $damage));

                        $this->statsService->updateStat($caster, 'magic_draftees_killed', $damage);
                        $this->statsService->updateStat($target, 'magic_draftees_lost', $damageDealt);

                        # For Empire, add killed draftees go in the crypt
                        if($target->realm->alignment === 'evil')
                        {
                            $target->realm->crypt += $damageDealt;
                            $this->sorcery['target']['crypt_bodies'] += $damageDealt;
                        }

                        $this->sorcery['damage'][$perk->key] = [
                            'sorcery_spell_damage_multiplier' => $sorcerySpellDamageMultiplier,
                            'spell_damage_multiplier' => $spellDamageMultiplier,
                            'damage' => $damage,
                            'damage_dealt' => $damageDealt,
                            'military_draftees' => $target->military_draftees,
                        ];

                        $verb = 'kills';
                    }

                    if($perk->key === 'disband_spies')
                    {
                        $baseDamage = (float)$spellPerkValues / 100;
                        $sorcerySpellDamageMultiplier = $this->sorceryCalculator->getSorcerySpellDamageMultiplier($target, $caster, $spell, $wizardStrength, $enhancementResource, $enhancementAmount, $perk->key);
                        $damageMultiplier = $this->spellDamageCalculator->getDominionHarmfulSpellDamageModifier($target, $caster, $spell, 'spies');

                        $damage = $baseDamage * $sorcerySpellDamageMultiplier * $spellDamageMultiplier;

                        $damageDealt = min($target->peasants * $damage, $target->peasants);
                        $damageDealt = floor($damageDealt);

                        $target->peasants -= $damageDealt;

                        $this->statsService->updateStat($caster, 'magic_spies_killed', $damage);
                        $this->statsService->updateStat($target, 'spies_lost', $damage);

                        # For Empire, add killed draftees go in the crypt
                        if($target->realm->alignment === 'evil')
                        {
                            $target->realm->crypt += $damageDealt;
                            $this->sorcery['target']['crypt_bodies'] += $damageDealt;
                        }

                        $this->sorcery['damage'][$perk->key] = [
                            'sorcery_spell_damage_multiplier' => $sorcerySpellDamageMultiplier,
                            'spell_damage_multiplier' => $spellDamageMultiplier,
                            'damage' => $damage,
                            'damage_dealt' => $damageDealt,
                            'military_spies' => $target->military_spies,
                        ];

                        $verb = 'kills';
                    }

                    if($perk->key === 'destroy_resource')
                    {
                        $resourceKey = $spellPerkValues[0];
                        $resource = Resource::where('key', $resourceKey)->firstOrFail();
                        $baseDamage = (float)$spellPerkValues[1] / 100;

                        $sorcerySpellDamageMultiplier = $this->sorceryCalculator->getSorcerySpellDamageMultiplier($caster, $target, $spell, $wizardStrength, $enhancementResource, $enhancementAmount, $perk->key);
                        $damageMultiplier = $this->spellDamageCalculator->getDominionHarmfulSpellDamageModifier($target, $caster, $spell, 'draftees');

                        $damage = $baseDamage * $sorcerySpellDamageMultiplier * $spellDamageMultiplier;

                        $targetResourceAmount = $this->resourceCalculator->getAmount($target, $resourceKey);

                        $damageDealt = min($targetResourceAmount * $damage, $targetResourceAmount);
                        $damageDealt = floor($damageDealt);

                        $this->resourceService->updateResources($target, [$resourceKey => $damageDealt*-1]);

                        $this->statsService->updateStat($caster, ($resourceKey . '_destroyed'), $damage);
                        $this->statsService->updateStat($target, ($resourceKey . '_lost'), $damage);

                        $this->sorcery['damage'][$perk->key] = [
                            'sorcery_spell_damage_multiplier' => $sorcerySpellDamageMultiplier,
                            'spell_damage_multiplier' => $spellDamageMultiplier,
                            'damage' => $damage,
                            'damage_dealt' => $damageDealt,
                            'resource_key' => $resourceKey,
                            'resource_name' => $resource->name,
                            'target_resource_amount' => $targetResourceAmount,
                        ];

                        $verb = 'destroys';
                    }

                    if($perk->key === 'kill_faction_units_percentage')
                    {
                        $faction = $spellPerkValues[0];
                        $slot = (int)$spellPerkValues[1];
                        $baseDamage = (float)$spellPerkValues[2] / 100;

                        if($target->race->name !== $faction)
                        {
                            $baseDamage = 0;
                        }

                        $sorcerySpellDamageMultiplier = $this->sorceryCalculator->getSorcerySpellDamageMultiplier($caster, $target, $spell, $wizardStrength, $enhancementResource, $enhancementAmount, $perk->key);
                        $spellDamageMultiplier = $this->spellDamageCalculator->getDominionHarmfulSpellDamageModifier($target, $caster, $spell, ('military_unit'.$slot));

                        $damage = $baseDamage * $sorcerySpellDamageMultiplier * $spellDamageMultiplier;

                        $damageDealt = min($target->{'military_unit'.$slot} * $ratio * $damageMultiplier, $this->resourceCalculator->getAmount($target, $resourceKey));
                        $damageDealt = floor($damageDealt);

                        $targetUnitAmount = $target->{'military_unit'.$slot};
                        $target->{'military_unit'.$slot} -= $damageDealt;

                        $unit = $target->race->units->filter(function ($unit) use ($slot) {
                            return ($unit->slot == $slot);
                        })->first();

                        #$result[] = sprintf('%s %s', number_format($damage), str_plural(dominion_attr_display($unit->name, $damageDealt), $damageDealt));

                        $this->statsService->updateStat($caster, 'units_killed', $damage);
                        $this->statsService->updateStat($target, ('unit' . $slot . '_lost'), $damage);

                        $this->sorcery['damage'][$perk->key] = [
                            'sorcery_spell_damage_multiplier' => $sorcerySpellDamageMultiplier,
                            'spell_damage_multiplier' => $spellDamageMultiplier,
                            'damage' => $damage,
                            'damage_dealt' => $damageDealt,
                            'unit_name' => $unit->name,
                            'target_unit_amount' => $targetUnitAmount,
                        ];

                    }

                    # Decrease morale
                    if($perk->key === 'decrease_morale')
                    {
                        $baseDamage = (int)$spellPerkValues / 100;

                        $sorcerySpellDamageMultiplier = $this->sorceryCalculator->getSorcerySpellDamageMultiplier($caster, $target, $spell, $wizardStrength, $enhancementResource, $enhancementAmount, $perk->key);
                        $spellDamageMultiplier = $this->spellDamageCalculator->getDominionHarmfulSpellDamageModifier($target, $caster, $spell, 'morale');

                        $damage = $baseDamage * $sorcerySpellDamageMultiplier * $spellDamageMultiplier;

                        $damageDealt = min($target->morale * $damage, $target->morale);
                        $damageDealt = floor($damageDealt);

                        $target->morale -= $damageDealt;
                        #$result[] = sprintf('%s%%', $damage);

                        $this->statsService->updateStat($caster, 'magic_damage_morale', $damage);
                        $verb = 'weakens morale by';
                    }

                    if($perk->key === 'improvements_damage')
                    {
                        $baseDamage = (float)$spellPerkValues / 100;

                        $totalImprovementPoints = $this->improvementCalculator->getDominionImprovementTotalAmountInvested($target);
                        $targetImprovements = $this->improvementCalculator->getDominionImprovements($target);

                        $sorcerySpellDamageMultiplier = $this->sorceryCalculator->getSorcerySpellDamageMultiplier($caster, $target, $spell, $wizardStrength, $enhancementResource, $enhancementAmount, $perk->key);
                        $spellDamageMultiplier = $this->spellDamageCalculator->getDominionHarmfulSpellDamageModifier($target, $caster, $spell, 'improvements');

                        $damage = $baseDamage * $sorcerySpellDamageMultiplier * $spellDamageMultiplier;

                        $damageDealt = min($totalImprovementPoints * $damage, $totalImprovementPoints);
                        $damageDealt = floor($damageDealt);

                        if($damageDealt > 0)
                        {
                            foreach($targetImprovements as $targetImprovement)
                            {
                                $improvement = Improvement::where('id', $targetImprovement->improvement_id)->first();
                                $improvementDamage[$improvement->key] = floor($damageDealt * ($this->improvementCalculator->getDominionImprovementAmountInvested($target, $improvement) / $totalImprovementPoints));
                            }

                            $this->improvementCalculator->decreaseImprovements($target, $improvementDamage);
                        }

                        $this->statsService->updateStat($caster, 'magic_damage_improvements', $damageDealt);

                        #$result[] = sprintf('%s %s', number_format($damageDealt), dominion_attr_display('improvement', $damage));

                        $this->sorcery['damage'][$perk->key] = [
                            'sorcery_spell_damage_multiplier' => $sorcerySpellDamageMultiplier,
                            'spell_damage_multiplier' => $spellDamageMultiplier,
                            'damage' => $damage,
                            'damage_dealt' => $damageDealt,
                            'total_improvement_points' => $totalImprovementPoints
                        ];

                        $verb = 'damages';
                    }

                    if($perk->key === 'resource_theft')
                    {
                        $resourceKey = $spellPerkValues[0];
                        $resource = Resource::where('key', $resourceKey)->first();

                        $sorcerySpellDamageMultiplier = $this->sorceryCalculator->getSorcerySpellDamageMultiplier($caster, $target, $spell, $wizardStrength, $enhancementResource, $enhancementAmount, $perk->key);
                        $spellDamageMultiplier = $this->spellDamageCalculator->getDominionHarmfulSpellDamageModifier($target, $caster, $spell, 'theft');

                        $baseDamage = (float)$spellPerkValues[1] / 100;
                        $theftRatio = $baseDamage * $sorcerySpellDamageMultiplier * $spellDamageMultiplier;

                        $damageDealt = $this->getTheftAmount($caster, $target, $spell, $resourceKey, $theftRatio);

                        $this->resourceService->updateResources($target, [$resourceKey => $damageDealt*-1]);
                        $this->resourceService->updateResources($caster, [$resourceKey => $damageDealt]);

                        $this->statsService->updateStat($caster, ($resourceKey .  '_stolen'), $damageDealt);
                        $this->statsService->updateStat($target, ($resourceKey . '_lost'), $damageDealt);

                        $this->sorcery['damage'][$perk->key] = [
                            'sorcery_spell_damage_multiplier' => $sorcerySpellDamageMultiplier,
                            'spell_damage_multiplier' => $spellDamageMultiplier,
                            'damage' => $theftRatio,
                            'theft_ratio' => $theftRatio,
                            'damage_dealt' => $damageDealt,
                            'resource_key' => $resourceKey,
                            'resource_name' => $resource->name
                        ];
                    }
                }
                #END PERK FOREACH
            }

            $this->sorceryEvent = GameEvent::create([
                'round_id' => $caster->round_id,
                'source_type' => Dominion::class,
                'source_id' => $caster->id,
                'target_type' => Dominion::class,
                'target_id' => $target->id,
                'type' => 'sorcery',
                'data' => $this->sorcery,
                'tick' => $caster->round->ticks
            ]);

            $this->notificationService->queueNotification('sorcery', [
                '_routeParams' => [(string)$this->sorceryEvent->id],
                'caster_dominion_id' => $caster->id,
                'data' => $this->sorcery,
            ]);

            $target->save([
                'event' => HistoryService::EVENT_ACTION_SORCERY,
                'action' => $spell->key
            ]);

            $caster->save([
                'event' => HistoryService::EVENT_ACTION_SORCERY,
                'action' => $spell->key
            ]);

            #dd($this->sorcery, $this->sorceryEvent);
        });

        $this->notificationService->sendNotifications($target, 'irregular_dominion');

        $message = sprintf(
            'You perform %s sorcery on %s (#%s)!',
            $spell->name,
            $target->name,
            $target->realm->number
        );

        $alertType = 'success';

        return [
            'message' => $message,
            'alert-type' => $alertType,
            'redirect' => route('dominion.event', [$this->sorceryEvent->id])
        ];

    }

    protected function calculateXpGain(Dominion $dominion, Dominion $target, int $damage): int
    {
        if($damage === 0 or $damage === NULL)
        {
            return 0;
        }
        else
        {
            $landRatio = $this->rangeCalculator->getDominionRange($dominion, $target) / 100;
            $base = 30;

            return $base * $landRatio;
        }
    }

    protected function getTheftAmount(Dominion $dominion, Dominion $target, Spell $spell, string $resourceKey, float $ratio): int
    {
        if($spell->scope !== 'hostile')
        {
            return 0;
        }

        if($resource == 'draftees')
        {
            $resourceString = 'military_draftees';
            $availableResource = $target->military_draftees;
        }
        elseif($resource == 'peasants')
        {
            $resourceString = 'peasants';
            $availableResource = $target->peasants;
        }
        else
        {
            $resourceString = 'resource_'.$resource;
            $availableResource = $this->resourceCalculator->getAmount($target, $resourceKey);
        }

        $availableResource = $target->{$resourceString};

        // Unit theft protection
        for ($slot = 1; $slot <= 4; $slot++)
        {
            if($theftProtection = $target->race->getUnitPerkValueForUnitSlot($slot, 'protects_resource_from_theft'))
            {
                if($theftProtection[0] == $resource)
                {
                    $availableResource -= $target->{'military_unit'.$slot} * $theftProtection[1];
                }

            }
        }

        $availableResource = max(0, $availableResource);

        $theftAmount = min($availableResource * $ratio, $availableResource);

        /*
        # The stealer can increase
        $thiefModifier = 1;
        $thiefModifier += $dominion->getTechPerkMultiplier('amount_stolen');
        $thiefModifier += $dominion->getDeityPerkMultiplier('amount_stolen');
        $thiefModifier += $dominion->race->getPerkMultiplier('amount_stolen');

        $theftAmount *= $thiefModifier;

        # But the target can decrease, which comes afterwards
        $targetModifier = 0;
        $targetModifier += $target->getSpellPerkMultiplier($resource . '_theft');
        $targetModifier += $target->getSpellPerkMultiplier('all_theft');
        $targetModifier += $target->getBuildingPerkMultiplier($resource . '_theft_reduction');

        $theftAmount *= (1 + $targetModifier);
        */

        dd($theftAmount, $availableResource);

        $theftAmount = max(0, $theftAmount);

        return $theftAmount;
    }

  }
