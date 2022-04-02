<?php

namespace OpenDominion\Helpers;

use Illuminate\Support\Collection;

use OpenDominion\Models\Deity;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\GameEvent;
use OpenDominion\Models\Realm;
use OpenDominion\Models\Spell;

use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Calculators\Dominion\MilitaryCalculator;

use OpenDominion\Helpers\EventHelper;
use OpenDominion\Helpers\RaceHelper;
use OpenDominion\Helpers\RealmHelper;

class WorldNewsHelper
{
    public function __construct()
    {
        $this->landCalculator = app(LandCalculator::class);
        $this->militaryCalculator = app(MilitaryCalculator::class);

        $this->eventHelper = app(EventHelper::class);
        $this->raceHelper = app(RaceHelper::class);
        $this->realmHelper = app(RealmHelper::class);
    }

    public function getWorldNewsString(Dominion $viewer, GameEvent $event): string
    {
        switch ($event->type)
        {
            case 'barbarian_invasion':
                return $this->generateBarbarianInvasionString($event->source, $event, $viewer);

            case 'deity_completed':
                return $this->generateDeityCompletedString($event->target, $event->source, $viewer);

            case 'deity_renounced':
                return $this->generateDeityRenouncedString($event->target, $event->source, $viewer);

            case 'expedition':
                return $this->generateExpeditionString($event->source, $event, $viewer);

            case 'invasion':
                return $this->generateInvasionString($event->target, $event->source, $event, $viewer);

            case 'new_dominion':
                return $this->generateNewDominionString($event->source, $event->target, $viewer);

            case 'sorcery':
                return $this->generateSorceryString($event->source, $event->target, $event, $viewer);

            case 'theft':
                return $this->generateTheftString($event->source, $event->target, $event, $viewer);

            default:
                return 'No string defined for event type <pre>' . $event->type . ')</pre>.';
        }
    }

    public function generateBarbarianInvasionString(Dominion $dominion, GameEvent $event, Dominion $viewer): string
    {
        /*
             Gilleg's Herd (#1) ransacked a nearby merchant outpost and captured 235 land.
        */

        $string = sprintf(
            '%s %s a nearby %s and captured <b><span class="%s">%s</span></b> land.',
            $this->generateDominionString($dominion, 'barbarian', $viewer),
            $event['data']['type'],
            $event['data']['target'],
            $this->getSpanClass('barbarian'),
            number_format($event['data']['land']),
          );

        return $string;
    }

    public function generateDeityCompletedString(Dominion $dominion, Deity $deity, Dominion $viewer): string
    {
        /*
            Mirnon has accepted the devotion of Dark Elf (#3).
        */

        $deityClass = $this->getSpanClass('other');

        $string = sprintf(
            '<span class="%s">%s</span> has accepted the devotion of %s.',
            $deityClass,
            $deity->name,
            $this->generateDominionString($dominion, 'neutral', $viewer)
          );

        return $string;
    }

    public function generateDeityRenouncedString(Dominion $dominion, Deity $deity, Dominion $viewer): string
    {
        /*
            Winter Soldier (#2) has renounced Bregon.
        */

        $deityClass = $this->getSpanClass('other');

        $string = sprintf(
            '%s has renounced <span class="%s">%s</span>.',
            $this->generateDominionString($dominion, 'neutral', $viewer),
            $deityClass,
            $deity->name,
          );

        return $string;
    }

    public function generateExpeditionString(Dominion $dominion, GameEvent $expedition, Dominion $viewer): string
    {
        /*
            An expedition was sent out by Golden Showers (#2), discovering 4 land.
        */

        $mode = 'other';
        if($dominion->realm->id == $viewer->realm->id)
        {
            $mode = 'green';
        }

        $string = sprintf(
            'An expedition sent out by %s discovered <strong class="%s">%s</strong> land.',
            $this->generateDominionString($dominion, 'neutral', $viewer),
            $this->getSpanClass($mode),
            number_format($expedition['data']['land_discovered_amount'])
          );

        return $string;
    }

    public function generateInvasionString(Dominion $attacker, Dominion $defender, GameEvent $invasion, Dominion $viewer): string
    {
        $landConquered = 0;
        $landDiscovered = 0;

        $isAttackerFriendly = ($attacker->realm->id == $viewer->realm->id);
        $isDefenderFriendly = ($defender->realm->id == $viewer->realm->id);

        if($isSuccessful = $invasion['data']['result']['success'])
        {
            $landConquered += array_sum($invasion['data']['attacker']['landConquered']);
            $landDiscovered += array_sum($invasion['data']['attacker']['landDiscovered']);
        }

        # Friendly attacker successful
        if($isAttackerFriendly and !$isDefenderFriendly and $isSuccessful)
        {
            return sprintf(
                'Victorious in battle, %s conquered <strong class="text-green">%s</strong> land from %s! They also discovered <strong class="text-orange">%s</strong> land.',
                $this->generateDominionString($attacker, 'neutral', $viewer),
                number_format($landConquered),
                $this->generateDominionString($defender, 'neutral', $viewer),
                number_format($landDiscovered)
              );
        }
        # Friendly attacker unsuccessful
        if($isAttackerFriendly and !$isDefenderFriendly and !$isSuccessful)
        {
            return sprintf(
                '%s was beaten back by %s.',
                $this->generateDominionString($attacker, 'neutral', $viewer),
                $this->generateDominionString($defender, 'neutral', $viewer)
              );
        }
        # Friendly defender successful
        if(!$isAttackerFriendly and $isDefenderFriendly and !$isSuccessful)
        {
            return sprintf(
                '%s fended off an attack by %s.',
                $this->generateDominionString($defender, 'neutral', $viewer),
                $this->generateDominionString($attacker, 'neutral', $viewer)
              );
        }
        # Friendly defender unsuccessful
        if(!$isAttackerFriendly and $isDefenderFriendly and $isSuccessful)
        {
            return sprintf(
                '%s conquered <strong class="text-red">%s</strong> land from %s.',
                $this->generateDominionString($attacker, 'neutral', $viewer),
                number_format($landConquered),
                $this->generateDominionString($defender, 'neutral', $viewer)
              );
        }

        # Hostile attacker successful against hostile defender
        if(!$isAttackerFriendly and !$isDefenderFriendly and $isSuccessful)
        {
            return sprintf(
                '%s conquered <strong class="text-orange">%s</strong> land from %s.',
                $this->generateDominionString($attacker, 'neutral', $viewer),
                number_format($landConquered),
                $this->generateDominionString($defender, 'neutral', $viewer)
              );
        }

        # Hostile attacker unsuccessful against hostile defender
        if(!$isAttackerFriendly and !$isDefenderFriendly and $isSuccessful)
        {
            return sprintf(
                '%s fended off an attack by %s.',
                $this->generateDominionString($attacker, 'neutral', $viewer),
                $this->generateDominionString($defender, 'neutral', $viewer)
              );
        }

        # Deathmatch in-realm invasion
        if($isAttackerFriendly and $isDefenderFriendly and $isSuccessful and ($viewer->round->mode == 'deathmatch' or $viewer->round->mode == 'deathmatch-duration'))
        {
            return sprintf(
                '%s conquered <strong class="text-orange">%s</strong> land from %s.',
                $this->generateDominionString($attacker, 'neutral', $viewer),
                number_format($landConquered),
                $this->generateDominionString($defender, 'neutral', $viewer)
              );
        }

        # Deathmatch in-realm invasion
        if($isAttackerFriendly and $isDefenderFriendly and $isSuccessful and ($viewer->round->mode == 'deathmatch' or $viewer->round->mode == 'deathmatch-duration'))
        {
            return sprintf(
                '%s fended off an attack by %s.',
                $this->generateDominionString($attacker, 'neutral', $viewer),
                $this->generateDominionString($defender, 'neutral', $viewer)
              );
        }


        return 'Edge case detected for GameEvent ID ' . $invasion->id;

    }

    public function generateNewDominionString(Dominion $dominion, Realm $realm, Dominion $viewer): string
    {
        /*
            The Barbarian dominion of Ssiwen's Mongrels, led by Commander Ssiwen, was spotted in the Barbarian Horde.
        */

        $mode = 'hostile';
        if(($dominion->realm->id == $viewer->realm->id))
        {
            $mode = 'green';
        }

        $string = sprintf(
            'The <span class="%s">%s</span> dominion of %s was founded by <em>%s</em> %s.',
            $this->getSpanClass($mode),
            $this->raceHelper->getRaceAdjective($dominion->race),
            $this->generateDominionString($dominion, 'neutral', $viewer),
            $dominion->title->name,
            $dominion->ruler_name
          );

        return $string;
    }

    public function generateSorceryString(Dominion $caster, Dominion $target, GameEvent $sorcery, Dominion $viewer): string
    {
        /*
            Mirnon has accepted the devotion of Dark Elf (#3).
        */

        $spell = Spell::where('key', $sorcery['data']['spell_key'])->first();

        # Viewer can see caster if viewer is in same realm as caster, or if viewer is in same realm as target and taret has reveal_ops

        $viewerInvolved = ($caster->realm->id == $viewer->realm->id or $target->realm->id == $viewer->realm->id);

        if(!$viewerInvolved)
        {
            return sprintf(
                '<span class="text-red">%s</span> cast on a dominion in the %s realm.',
                $spell->name,
                $this->generateRealmOnlyString($target->realm)
              );
        }

        $canViewerSeeCaster = false;
        if(($caster->realm->id == $viewer->realm->id) or ($target->realm->id == $viewer->realm->id and $sorcery['data']['target']['reveal_ops']))
        {
            $canViewerSeeCaster = true;
        }

        $spellSpanClass = $this->getSpanClass('hostile');

        if($canViewerSeeCaster)
        {
            $string = sprintf(
              '%s cast <span class="%s">%s</span> on %s.',
              $this->generateDominionString($caster, 'neutral', $viewer),
              $spellSpanClass,
              $spell->name,
              $this->generateDominionString($target, 'neutral', $viewer)
            );
        }
        else
        {
            $string = sprintf(
              '<span class="%s">%s</span> was cast on %s.',
              $spellSpanClass,
              $spell->name,
              $this->generateDominionString($target, 'neutral', $viewer)
            );
        }

        return $string;
    }

    public function generateTheftString(Dominion $thief, Dominion $target, GameEvent $theft, Dominion $viewer): string
    {
        /*
            Spies from Birka (#2) stole 256,000 gold from Zigwheni (#2).
        */

        $viewerInvolved = ($thief->realm->id == $viewer->realm->id or $target->realm->id == $viewer->realm->id);

        if(!$viewerInvolved)
        {
            return sprintf(
                'Theft reported in the %s realm.',
                $this->generateRealmOnlyString($target->realm)
              );
        }

        if($thief->realm->id == $viewer->realm->id)
        {
            $amountClass = $this->getSpanClass('green');
        }
        else
        {
            $amountClass = $this->getSpanClass('hostile');
        }

        $amount = $theft['data']['amount_stolen'];
        $resourceName = $theft['data']['resource']['name'];

        $string = sprintf(
            'Spies from %s stole <b><span class="%s">%s</span></b> %s from %s.',
            $this->generateDominionString($thief, 'neutral', $viewer),
            $amountClass,
            number_format($amount),
            $resourceName,
            $this->generateDominionString($target, 'neutral', $viewer),
          );

        return $string;
    }

    public function generateDominionString(Dominion $dominion, string $mode = "neutral", Dominion $viewer): string
    {

        $string = sprintf(
            '<a href="%s"><span data-toggle="tooltip" data-placement="top" title="<small class=\'text-muted\'>Range:</small> %s%%<br><small class=\'text-muted\'>Faction:</small> %s<br><small class=\'text-muted\'>Status:</small> %s<br><small class=\'text-muted\'>Units returning:</small> %s" class="%s"> %s <a href="%s">(# %s)</a></span>',
            route('dominion.insight.show', [$dominion->id]),
            number_format($this->landCalculator->getTotalLand($dominion)/$this->landCalculator->getTotalLand($viewer)*100,2),
            $dominion->race->name,
            (($dominion->realm->id == $viewer->realm->id) or ($viewer->round->mode == 'deathmatch' or $viewer->round->mode == 'deathmatch-duration')) ? "<small class='text-green'>Friendly</small>" : "<small class='text-red'>Hostile</small>",
            $this->militaryCalculator->hasReturningUnits($dominion) ? "<span class='text-green'>Yes</span>" : "<span class='text-red'>No</span>",
            $this->getSpanClass($mode),
            $dominion->name,
            route('dominion.realm', [$dominion->realm->number]),
            $dominion->realm->number
          );

        return $string;
    }

    public function generateRealmOnlyString(Realm $realm, $mode = 'other'): string
    {
        $string = sprintf(
            '<a href="%s"><span class="%s">%s</span> (# %s)</a>',
            route('dominion.realm', [$realm->number]),
            $this->getSpanClass($mode),
            $this->realmHelper->getAlignmentAdjective($realm->alignment),
            $realm->number
          );

        return $string;
    }

    public function getSpanClass(string $mode = 'neutral'): string
    {
        switch ($mode)
        {
            case 'hostile':
            case 'red':
                return 'text-red';

            case 'friendly':
            case 'neutral':
            case 'aqua':
                return 'text-aqua';

            case 'green':
                return 'text-green';

            case 'barbarian':
            case 'other':
                return 'text-orange';

            case 'purple':
                return 'text-purple';

            default:
                return 'text-aqua';
        }
    }


}
