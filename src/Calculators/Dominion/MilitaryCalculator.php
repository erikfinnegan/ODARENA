<?php

namespace OpenDominion\Calculators\Dominion;

use OpenDominion\Models\Dominion;
use OpenDominion\Models\GameEvent;
use OpenDominion\Models\Unit;
use Log;

use OpenDominion\Services\Dominion\GovernmentService;
use OpenDominion\Services\Dominion\QueueService;

# ODA
use Illuminate\Support\Carbon;

class MilitaryCalculator
{
    /**
     * @var float Number of boats protected per dock
     */
    protected const BOATS_PROTECTED_PER_DOCK = 2.5;

    /** @var BuildingCalculator */
    protected $buildingCalculator;

    /** @var GovernmentService */
    protected $governmentService;

    /** @var ImprovementCalculator */
    protected $improvementCalculator;

    /** @var LandCalculator */
    protected $landCalculator;

    /** @var PrestigeCalculator */
    private $prestigeCalculator;

    /** @var QueueService */
    protected $queueService;

    /** @var SpellCalculator */
    protected $spellCalculator;

    /** @var bool */
    protected $forTick = false;

    /**
     * MilitaryCalculator constructor.
     *
     * @param BuildingCalculator $buildingCalculator
     * @param ImprovementCalculator $improvementCalculator
     * @param LandCalculator $landCalculator
     * @param PrestigeCalculator $prestigeCalculator
     * @param QueueService $queueService
     * @param SpellCalculator $spellCalculator
     */
    public function __construct(
        BuildingCalculator $buildingCalculator,
        GovernmentService $governmentService,
        ImprovementCalculator $improvementCalculator,
        LandCalculator $landCalculator,
        PrestigeCalculator $prestigeCalculator,
        QueueService $queueService,
        SpellCalculator $spellCalculator
        )
    {
        $this->buildingCalculator = $buildingCalculator;
        $this->governmentService = $governmentService;
        $this->improvementCalculator = $improvementCalculator;
        $this->landCalculator = $landCalculator;
        $this->prestigeCalculator = $prestigeCalculator;
        $this->queueService = $queueService;
        $this->spellCalculator = $spellCalculator;
    }

    /**
     * Toggle if this calculator should include the following hour's resources.
     */
    public function setForTick(bool $value)
    {
        $this->forTick = $value;
        $this->queueService->setForTick($value);
    }

    /**
     * Returns the Dominion's offensive power.
     *
     * @param Dominion $dominion
     * @param Dominion|null $target
     * @param float|null $landRatio
     * @param array|null $units
     * @return float
     */
    public function getOffensivePower(
        Dominion $dominion,
        Dominion $target = null,
        float $landRatio = null,
        array $units = null,
        array $calc = []
    ): float
    {
        $op = ($this->getOffensivePowerRaw($dominion, $target, $landRatio, $units, $calc) * $this->getOffensivePowerMultiplier($dominion, $target));

        return ($op * $this->getMoraleMultiplier($dominion));
    }

    /**
     * Returns the Dominion's raw offensive power.
     *
     * @param Dominion $dominion
     * @param Dominion|null $target
     * @param float|null $landRatio
     * @param array|null $units
     * @return float
     */
    public function getOffensivePowerRaw(
        Dominion $dominion,
        Dominion $target = null,
        float $landRatio = null,
        array $units = null,
        array $calc = []
    ): float
    {
        $op = 0;

        foreach ($dominion->race->units as $unit) {
            $powerOffense = $this->getUnitPowerWithPerks($dominion, $target, $landRatio, $unit, 'offense', $calc, $units);
            $numberOfUnits = 0;

            if ($units === null) {
                $numberOfUnits = (int)$dominion->{'military_unit' . $unit->slot};
            } elseif (isset($units[$unit->slot]) && ((int)$units[$unit->slot] !== 0)) {
                $numberOfUnits = (int)$units[$unit->slot];
            }

            if ($numberOfUnits !== 0) {
                $bonusOffense = $this->getBonusPowerFromPairingPerk($dominion, $unit, 'offense', $units);
                $powerOffense += $bonusOffense / $numberOfUnits;
            }

            $op += ($powerOffense * $numberOfUnits);
        }

        return $op;
    }

    /**
     * Returns the Dominion's offensive power multiplier.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getOffensivePowerMultiplier(Dominion $dominion, Dominion $target = null): float
    {
        $multiplier = 0;

        // Building: Gryphon Nests
        $multiplier += $this->getGryphonNestMultiplier($dominion);

        // Improvement: Forges
        $multiplier += $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'forges');

        // Racial Bonus
        $multiplier += $dominion->race->getPerkMultiplier('offense');

        // Techs
        $multiplier += $dominion->getTechPerkMultiplier('offense');

        // Spell
        $multiplier += $this->getSpellMultiplier($dominion, $target, 'offense');

        // Prestige
        $multiplier += $this->prestigeCalculator->getPrestigeMultiplier($dominion);

        // Beastfolk: Plains increases OP
        if($dominion->race->name == 'Beastfolk')
        {
          $multiplier += 0.20 * ($dominion->{"land_plain"} / $this->landCalculator->getTotalLand($dominion));
        }

        return (1 + $multiplier);
    }

    /**
     * Returns the Dominion's offensive power ratio per acre of land.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getOffensivePowerRatio(Dominion $dominion): float
    {
        return ($this->getOffensivePower($dominion) / $this->landCalculator->getTotalLand($dominion));
    }

    /**
     * Returns the Dominion's raw offensive power ratio per acre of land.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getOffensivePowerRatioRaw(Dominion $dominion): float
    {
        return ($this->getOffensivePowerRaw($dominion) / $this->landCalculator->getTotalLand($dominion));
    }

    /**
     * Returns the Dominion's defensive power.
     *
     * @param Dominion $dominion
     * @param Dominion|null $target
     * @param float|null $landRatio
     * @param array|null $units
     * @param float $multiplierReduction
     * @param bool $ignoreDraftees
     * @param bool $isAmbush
     * @return float
     */
    public function getDefensivePower(
        Dominion $dominion,               # 1
        Dominion $target = null,          # 2
        float $landRatio = null,          # 3
        array $units = null,              # 4
        float $multiplierReduction = 0,   # 5
        bool $ignoreDraftees = false,     # 6
        bool $isAmbush = false            # 7
    ): float
    {
        $dp = $this->getDefensivePowerRaw($dominion, $target, $landRatio, $units, $ignoreDraftees, $isAmbush, false);
        $dp *= $this->getDefensivePowerMultiplier($dominion, $multiplierReduction);

        return ($dp * $this->getMoraleMultiplier($dominion));
    }

    /**
     * Returns the Dominion's raw defensive power.
     *
     * @param Dominion $dominion
     * @param Dominion|null $target
     * @param float|null $landRatio
     * @param array|null $units
     * @param bool $ignoreDraftees
     * @param bool $isAmbush
     * @return float
     */
    public function getDefensivePowerRaw(
        Dominion $dominion,
        Dominion $target = null,
        float $landRatio = null,
        array $units = null,
        float $multiplierReduction = 0,
        bool $ignoreDraftees = false,
        bool $isAmbush = false,
        bool $ignoreRawDpFromBuildings = false
    ): float
    {
        $dp = 0;

        // Values
        $minDPPerAcre = 10; # LandDP
        $forestHavenDpPerPeasant = 0.75;
        $peasantsPerForestHaven = 20;


        # If draftees are ignored, we lower DP per draftee to 0.
        if($ignoreDraftees)
        {
            $dpPerDraftee = 0;
        }
        else
        {
            $dpPerDraftee = 1;

            # Some draftees are weaker (Ants, Growth), and some draftees
            # count as no DP. If no DP, draftees do not participate in battle.
            if($dominion->race->getPerkValue('draftee_dp'))
            {
                $dpPerDraftee = $dominion->race->getPerkValue('draftee_dp');
                if($dominion->race->name == 'Swarm' and $this->spellCalculator->isSpellActive($dominion, 'chitin'))
                {
                    $dpPerDraftee = 1;
                }
            }
        }

        # If DP per draftee is 0, ignore them (no casualties).
        if($dpPerDraftee == 0)
        {
          $ignoreDraftees = True;
        }

        // Military
        foreach ($dominion->race->units as $unit)
        {
            $powerDefense = $this->getUnitPowerWithPerks($dominion, $target, $landRatio, $unit, 'defense', null, $units);

            $numberOfUnits = 0;

            if ($units === null)
            {
                $numberOfUnits = (int)$dominion->{'military_unit' . $unit->slot};
            }
            elseif (isset($units[$unit->slot]) && ((int)$units[$unit->slot] !== 0))
            {
                $numberOfUnits = (int)$units[$unit->slot];
            }

            if ($numberOfUnits !== 0)
            {
                $bonusDefense = $this->getBonusPowerFromPairingPerk($dominion, $unit, 'defense', $units);
                $powerDefense += $bonusDefense / $numberOfUnits;
            }

            $dp += ($powerDefense * $numberOfUnits);
        }

        // Draftees
        if (!$ignoreDraftees or isset($units['draftees']))
        {

            if ($units !== null && isset($units[0]))
            {
                $dp += ((int)$units[0] * $dpPerDraftee);
            }
            elseif ($units !== null && isset($units['draftees']))
            {
                $dp += ((int)$units['draftees'] * $dpPerDraftee);
            }
            else
            {
                $dp += ($dominion->military_draftees * $dpPerDraftee);
            }
        }

        if (!$ignoreRawDpFromBuildings)
        {
            // Building: Forest Havens
            $dp += min(
                ($dominion->peasants * $forestHavenDpPerPeasant),
                ($dominion->building_forest_haven * $forestHavenDpPerPeasant * $peasantsPerForestHaven)
            );

            if($dominion->race->getPerkValue('defense_per_ziggurat'))
            {
                $dp += $dominion->building_ziggurat * $dominion->race->getPerkValue('defense_per_ziggurat');
            }
        }

        // Beastfolk: Ambush (reduce raw DP by 2 x Forest %, max -10)
        if($isAmbush)
        {
          $forestRatio = $target->{'land_forest'} / $this->landCalculator->getTotalLand($target);
          $forestRatioModifier = $forestRatio / 5;
          $ambushReduction = min($forestRatioModifier, 0.10);
          $dp = $dp * (1 - $ambushReduction);
        }

        // Attacking Forces skip land-based defenses
        if ($units !== null)
        {
            return $dp;
        }

        return max(
            $dp,
            ($minDPPerAcre * $this->landCalculator->getTotalLand($dominion))
        );
    }

    /**
     * Returns the Dominion's defensive power multiplier.
     *
     * @param Dominion $dominion
     * @param float $multiplierReduction
     * @return float
     */
    public function getDefensivePowerMultiplier(Dominion $dominion, float $multiplierReduction = 0): float
    {
        $multiplier = 0;

        // Building: Guard Towers
        $multiplier += $this->getGuardTowerMultiplier($dominion);

        // Improvement: Forges
        $multiplier += $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'walls');

        // Racial Bonus
        $multiplier += $dominion->race->getPerkMultiplier('defense');

        // Techs
        $multiplier += $dominion->getTechPerkMultiplier('defense');

        // Spell
        $multiplier += $this->getSpellMultiplier($dominion, null, 'defense');

        // Beastfolk: Plains increases DP
        if($dominion->race->name == 'Beastfolk')
        {
            $multiplier += 0.2 * ($dominion->{"land_hill"} / $this->landCalculator->getTotalLand($dominion));
        }

        // Simian: defense_from_forest
        if($dominion->race->getPerkValue('defense_from_forest'))
        {
            $multiplier += $dominion->race->getPerkValue('defense_from_forest') * ($dominion->{'land_forest'} / $this->landCalculator->getTotalLand($dominion));
        }

        // Multiplier reduction when we want to factor in temples from another dominion
        $multiplier = max(($multiplier - $multiplierReduction), 0);

        return (1 + $multiplier);
    }

    /**
     * Returns the Dominion's defensive power ratio per acre of land.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getDefensivePowerRatio(Dominion $dominion): float
    {
        return ($this->getDefensivePower($dominion) / $this->landCalculator->getTotalLand($dominion));
    }

    /**
     * Returns the Dominion's raw defensive power ratio per acre of land.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getDefensivePowerRatioRaw(Dominion $dominion): float
    {
        return ($this->getDefensivePowerRaw($dominion) / $this->landCalculator->getTotalLand($dominion));
    }

    public function getUnitPowerWithPerks(
        Dominion $dominion,
        ?Dominion $target,
        ?float $landRatio,
        Unit $unit,
        string $powerType,
        ?array $calc = [],
        array $units = null
    ): float
    {
        $unitPower = $unit->{"power_$powerType"};

        $unitPower += $this->getUnitPowerFromLandBasedPerk($dominion, $unit, $powerType);
        $unitPower += $this->getUnitPowerFromBuildingBasedPerk($dominion, $unit, $powerType);
        $unitPower += $this->getUnitPowerFromRawWizardRatioPerk($dominion, $unit, $powerType);
        $unitPower += $this->getUnitPowerFromRawSpyRatioPerk($dominion, $unit, $powerType);
        $unitPower += $this->getUnitPowerFromModWizardRatioPerk($dominion, $unit, $powerType);
        $unitPower += $this->getUnitPowerFromModSpyRatioPerk($dominion, $unit, $powerType);
        $unitPower += $this->getUnitPowerFromPrestigePerk($dominion, $unit, $powerType);
        $unitPower += $this->getUnitPowerFromRecentlyInvadedPerk($dominion, $unit, $powerType);
        $unitPower += $this->getUnitPowerFromHoursPerk($dominion, $unit, $powerType);
        $unitPower += $this->getUnitPowerFromMilitaryPercentagePerk($dominion, $unit, $powerType);
        $unitPower += $this->getUnitPowerFromVictoriesPerk($dominion, $unit, $powerType);
        $unitPower += $this->getUnitPowerFromResourcePerk($dominion, $unit, $powerType);

        if ($landRatio !== null) {
            $unitPower += $this->getUnitPowerFromStaggeredLandRangePerk($dominion, $landRatio, $unit, $powerType);
        }

        if ($target !== null || !empty($calc))
        {
            $unitPower += $this->getUnitPowerFromVersusRacePerk($dominion, $target, $unit, $powerType);
            $unitPower += $this->getUnitPowerFromVersusBuildingPerk($dominion, $target, $unit, $powerType, $calc);
            $unitPower += $this->getUnitPowerFromVersusLandPerk($dominion, $target, $unit, $powerType, $calc);
            $unitPower += $this->getUnitPowerFromVersusBarrenLandPerk($dominion, $target, $unit, $powerType, $calc);
            $unitPower += $this->getUnitPowerFromVersusPrestigePerk($dominion, $target, $unit, $powerType, $calc);
            $unitPower += $this->getUnitPowerFromVersusResourcePerk($dominion, $target, $unit, $powerType, $calc);
            $unitPower += $this->getUnitPowerFromMob($dominion, $target, $unit, $powerType, $calc, $units);
        }

        return $unitPower;
    }

    protected function getUnitPowerFromLandBasedPerk(Dominion $dominion, Unit $unit, string $powerType): float
    {
        $landPerkData = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, "{$powerType}_from_land", null);

        if (!$landPerkData) {
            return 0;
        }

        $landType = $landPerkData[0];
        $ratio = (int)$landPerkData[1];
        $max = (int)$landPerkData[2];
        $constructedOnly = false;
        //$constructedOnly = $landPerkData[3]; todo: implement for Nox?
        $totalLand = $this->landCalculator->getTotalLand($dominion);

        if (!$constructedOnly)
        {
            $landPercentage = ($dominion->{"land_{$landType}"} / $totalLand) * 100;
        }
        else
        {
            $buildingsForLandType = $this->buildingCalculator->getTotalBuildingsForLandType($dominion, $landType);

            $landPercentage = ($buildingsForLandType / $totalLand) * 100;
        }

        $powerFromLand = $landPercentage / $ratio;
        $powerFromPerk = min($powerFromLand, $max);

        return $powerFromPerk;
    }

    protected function getUnitPowerFromBuildingBasedPerk(Dominion $dominion, Unit $unit, string $powerType): float
    {
        $buildingPerkData = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, "{$powerType}_from_building", null);

        if (!$buildingPerkData) {
            return 0;
        }

        $buildingType = $buildingPerkData[0];
        $ratio = (int)$buildingPerkData[1];
        $max = (int)$buildingPerkData[2];
        $totalLand = $this->landCalculator->getTotalLand($dominion);
        $landPercentage = ($dominion->{"building_{$buildingType}"} / $totalLand) * 100;

        $powerFromBuilding = $landPercentage / $ratio;
        $powerFromPerk = min($powerFromBuilding, $max);

        return $powerFromPerk;
    }

    protected function getUnitPowerFromRawWizardRatioPerk(Dominion $dominion, Unit $unit, string $powerType): float
    {
        $wizardRatioPerk = $dominion->race->getUnitPerkValueForUnitSlot(
            $unit->slot,
            "{$powerType}_raw_wizard_ratio");

        if (!$wizardRatioPerk) {
            return 0;
        }

        $ratio = (float)$wizardRatioPerk[0];
        $max = (int)$wizardRatioPerk[1];

        $wizardRawRatio = $this->getWizardRatioRaw($dominion, 'offense');
        $powerFromWizardRatio = $wizardRawRatio * $ratio;
        $powerFromPerk = min($powerFromWizardRatio, $max);

        return $powerFromPerk;
    }


    protected function getUnitPowerFromModWizardRatioPerk(Dominion $dominion, Unit $unit, string $powerType): float
    {
        $wizardRatioPerk = $dominion->race->getUnitPerkValueForUnitSlot(
            $unit->slot,
            "{$powerType}_wizard_ratio");

        if (!$wizardRatioPerk) {
            return 0;
        }

        $ratio = (float)$wizardRatioPerk[0];
        $max = (int)$wizardRatioPerk[1];

        $wizardModRatio = $this->getWizardRatio($dominion, 'offense');
        $powerFromWizardRatio = $wizardModRatio * $ratio;
        $powerFromPerk = min($powerFromWizardRatio, $max);

        return $powerFromPerk;
    }

    protected function getUnitPowerFromRawSpyRatioPerk(Dominion $dominion, Unit $unit, string $powerType): float
    {
        $spyRatioPerk = $dominion->race->getUnitPerkValueForUnitSlot(
            $unit->slot,
            "{$powerType}_raw_spy_ratio");

        if(!$spyRatioPerk) {
            return 0;
        }

        $ratio = (float)$spyRatioPerk[0];
        $max = (int)$spyRatioPerk[1];

        $spyRawRatio = $this->getSpyRatioRaw($dominion, 'offense');
        $powerFromSpyRatio = $spyRawRatio * $ratio;
        $powerFromPerk = min($powerFromSpyRatio, $max);

        return $powerFromPerk;
    }


    protected function getUnitPowerFromModSpyRatioPerk(Dominion $dominion, Unit $unit, string $powerType): float
    {
        $spyRatioPerk = $dominion->race->getUnitPerkValueForUnitSlot(
            $unit->slot,
            "{$powerType}_spy_ratio");

        if(!$spyRatioPerk) {
            return 0;
        }

        $ratio = (float)$spyRatioPerk[0];
        $max = (int)$spyRatioPerk[1];

        $spyModRatio = $this->getSpyRatio($dominion, 'offense');
        $powerFromSpyRatio = $spyModRatio * $ratio;
        $powerFromPerk = min($powerFromSpyRatio, $max);

        return $powerFromPerk;
    }

    protected function getUnitPowerFromPrestigePerk(Dominion $dominion, Unit $unit, string $powerType): float
    {
        $prestigePerk = $dominion->race->getUnitPerkValueForUnitSlot(
            $unit->slot,
            "{$powerType}_from_prestige");

        if (!$prestigePerk) {
            return 0;
        }

        $amount = (float)$prestigePerk[0];
        $max = (int)$prestigePerk[1];

        $powerFromPerk = min($dominion->prestige / $amount, $max);

        return $powerFromPerk;
    }

    protected function getUnitPowerFromStaggeredLandRangePerk(Dominion $dominion, float $landRatio = null, Unit $unit, string $powerType): float
    {
        $staggeredLandRangePerk = $dominion->race->getUnitPerkValueForUnitSlot(
            $unit->slot,
            "{$powerType}_staggered_land_range");

        if (!$staggeredLandRangePerk) {
            return 0;
        }

        if ($landRatio === null) {
            $landRatio = 0;
        }

        $powerFromPerk = 0;

        foreach ($staggeredLandRangePerk as $rangePerk) {
            $range = ((int)$rangePerk[0]) / 100;
            $power = (float)$rangePerk[1];

            if ($range > $landRatio) {
                continue;
            }

            $powerFromPerk = $power;
        }

        return $powerFromPerk;
    }

    protected function getUnitPowerFromVersusRacePerk(Dominion $dominion, Dominion $target = null, Unit $unit, string $powerType): float
    {
        if ($target === null) {
            return 0;
        }

        $raceNameFormatted = strtolower($target->race->name);
        $raceNameFormatted = str_replace(' ', '_', $raceNameFormatted);

        $versusRacePerk = $dominion->race->getUnitPerkValueForUnitSlot(
            $unit->slot,
            "{$powerType}_vs_{$raceNameFormatted}");

        return $versusRacePerk;
    }

    protected function getBonusPowerFromPairingPerk(Dominion $dominion, Unit $unit, string $powerType, array $units = null): float
    {
        $pairingPerkData = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, "{$powerType}_from_pairing", null);

        if (!$pairingPerkData)
        {
            return 0;
        }

        $unitSlot = (int)$pairingPerkData[0];
        $amount = (float)$pairingPerkData[1];
        if (isset($pairingPerkData[2]))
        {
            $numRequired = (float)$pairingPerkData[2];
        }
        else
        {
            $numRequired = 1;
        }

        $powerFromPerk = 0;
        $numberPaired = 0;

        if ($units === null)
        {
            $numberPaired = min($dominion->{'military_unit' . $unit->slot}, floor((int)$dominion->{'military_unit' . $unitSlot} / $numRequired));
        }
        elseif (isset($units[$unitSlot]) && ((int)$units[$unitSlot] !== 0))
        {
            $numberPaired = min($units[$unit->slot], floor((int)$units[$unitSlot] / $numRequired));
        }

        $powerFromPerk = $numberPaired * $amount;

        return $powerFromPerk;
    }

    protected function getUnitPowerFromVersusBuildingPerk(Dominion $dominion, Dominion $target = null, Unit $unit, string $powerType, ?array $calc = []): float
    {
        if ($target === null && empty($calc)) {
            return 0;
        }

        $versusBuildingPerkData = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, "{$powerType}_vs_building", null);
        if (!$versusBuildingPerkData) {
            return 0;
        }

        $buildingType = $versusBuildingPerkData[0];
        $ratio = (int)$versusBuildingPerkData[1];
        $max = (int)$versusBuildingPerkData[2];

        $landPercentage = 0;
        if (!empty($calc)) {
            # Override building percentage for invasion calculator
            if (isset($calc["{$buildingType}_percent"])) {
                $landPercentage = (float) $calc["{$buildingType}_percent"];
            }
        } elseif ($target !== null) {
            $totalLand = $this->landCalculator->getTotalLand($target);
            $landPercentage = ($target->{"building_{$buildingType}"} / $totalLand) * 100;
        }

        $powerFromBuilding = $landPercentage / $ratio;
        if ($max < 0) {
            $powerFromPerk = max(-1 * $powerFromBuilding, $max);
        } else {
            $powerFromPerk = min($powerFromBuilding, $max);
        }

        return $powerFromPerk;
    }


    protected function getUnitPowerFromVersusLandPerk(Dominion $dominion, Dominion $target = null, Unit $unit, string $powerType, ?array $calc = []): float
    {
        if ($target === null && empty($calc)) {
            return 0;
        }

        $versusLandPerkData = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, "{$powerType}_vs_land", null);
        if(!$versusLandPerkData) {
            return 0;
        }

        $landType = $versusLandPerkData[0];
        $ratio = (int)$versusLandPerkData[1];
        $max = (int)$versusLandPerkData[2];

        $landPercentage = 0;
        if (!empty($calc)) {
            # Override land percentage for invasion calculator
            if (isset($calc["{$landType}_percent"])) {
                $landPercentage = (float) $calc["{$landType}_percent"];
            }
        } elseif ($target !== null) {
            $totalLand = $this->landCalculator->getTotalLand($target);
            $landPercentage = ($target->{"land_{$landType}"} / $totalLand) * 100;
        }

        $powerFromLand = $landPercentage / $ratio;
        if ($max < 0) {
            $powerFromPerk = max(-1 * $powerFromLand, $max);
        } else {
            $powerFromPerk = min($powerFromLand, $max);
        }

        return $powerFromPerk;
    }

    protected function getUnitPowerFromVersusBarrenLandPerk(Dominion $dominion, Dominion $target = null, Unit $unit, string $powerType, ?array $calc = []): float
    {
        if ($target === null && empty($calc))
        {
            return 0;
        }

        $versusLandPerkData = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, "{$powerType}_vs_barren_land", null);
        if(!$versusLandPerkData)
        {
            return 0;
        }

        $ratio = (int)$versusLandPerkData[0];
        $max = (int)$versusLandPerkData[1];

        $barrenLandPercentage = 0;
        if (!empty($calc))
        {
            # Override land percentage for invasion calculator
            if (isset($calc["barren_land_percent"]))
            {
                $barrenLandPercentage = (float) $calc["barren_land_percent"];
            }
        }
        elseif ($target !== null)
        {
            $totalLand = $this->landCalculator->getTotalLand($target);
            $barrenLand = $this->landCalculator->getTotalBarrenLandForSwarm($target);
            $barrenLandPercentage = ($barrenLand / $totalLand) * 100;
        }


        $powerFromLand = $barrenLandPercentage / $ratio;
        if ($max < 0)
        {
            $powerFromPerk = max(-1 * $powerFromLand, $max);
        }
        else
        {
            $powerFromPerk = min($powerFromLand, $max);
        }

        # No barren bonus vs. Barbarian (for now)
        if($target !== null and $target->race->name == 'Barbarian')
        {
          $powerFromPerk = 0;
        }

        return $powerFromPerk;
    }

    protected function getUnitPowerFromRecentlyInvadedPerk(Dominion $dominion, Unit $unit, string $powerType): float
    {
        $amount = 0;

        if($this->getRecentlyInvadedCount($dominion) > 0)
        {
          $amount = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot,"{$powerType}_if_recently_invaded");
        }

        return $amount;
    }

    protected function getUnitPowerFromHoursPerk(Dominion $dominion, Unit $unit, string $powerType): float
    {

        $hoursPerkData = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, "{$powerType}_per_hour", null);

        if (!$hoursPerkData or !$dominion->round->hasStarted())
        {
            return 0;
        }

        #$hoursSinceRoundStarted = ($dominion->round->start_date)->diffInHours(now());
        $hoursSinceRoundStarted = now()->startOfHour()->diffInHours(Carbon::parse($dominion->round->start_date)->startOfHour());

        $powerPerHour = (float)$hoursPerkData[0];
        $max = (float)$hoursPerkData[1];

        $powerFromHours = $powerPerHour * $hoursSinceRoundStarted;

        $powerFromPerk = min($powerFromHours, $max);

        return $powerFromPerk;
    }

    protected function getUnitPowerFromVersusPrestigePerk(Dominion $dominion, Dominion $target = null, Unit $unit, string $powerType): float
    {
        $prestigePerk = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, $powerType . "vs_prestige");

        if (!$prestigePerk)
        {
            return 0;
        }

        # Check if calcing on Invade page calculator.
        if (!empty($calc))
        {
            if (isset($calc['prestige']))
            {
                $prestige = intval($calc['prestige']);
            }
        }
        # Otherwise, SKARPT LÄGE!
        elseif ($target !== null)
        {
            $prestige = $target->prestige;
        }

        $amount = (int)$prestigePerk[0];
        $max = (int)$prestigePerk[1];

        $powerFromPerk = min($prestige / $amount, $max);

        return $powerFromPerk;
    }


    protected function getUnitPowerFromMilitaryPercentagePerk(Dominion $dominion, Unit $unit, string $powerType): float
    {
        $militaryPercentagePerk = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, $powerType . "_from_military_percentage");

        if (!$militaryPercentagePerk)
        {
            return 0;
        }

        $military = 0;

        # Draftees, Spies, Wizards, and Arch Mages always count.
        $military += $dominion->military_draftees;
        $military += $dominion->military_spies;
        $military += $dominion->military_wizards;
        $military += $dominion->military_archmages;

        # Units in training
        $military += $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_spies');
        $military += $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_wizards');
        $military += $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_archmages');

        # Check each Unit for does_not_count_as_population perk.
        for ($unitSlot = 1; $unitSlot <= 4; $unitSlot++)
        {
          if (!$dominion->race->getUnitPerkValueForUnitSlot($unitSlot, 'does_not_count_as_population'))
          {
            $military += $this->getTotalUnitsForSlot($dominion, $unitSlot);
            $military += $this->queueService->getTrainingQueueTotalByResource($dominion, "military_unit{$unitSlot}");
          }
        }

        $militaryPercentage = min(1, $military / ($military + $dominion->peasants));

        $powerFromPerk = min($militaryPercentagePerk * $militaryPercentage, 1);

        return $powerFromPerk;
    }

    protected function getUnitPowerFromVictoriesPerk(Dominion $dominion, Unit $unit, string $powerType): float
    {
        $victoriesPerk = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, $powerType . "_from_victories");

        if (!$victoriesPerk)
        {
            return 0;
        }

        $victories = $dominion->stat_attacking_success;

        $powerPerVictory = (float)$victoriesPerk[0];
        $max = (float)$victoriesPerk[1];

        $powerFromPerk = min($powerPerVictory * $victories, $max);

        return $powerFromPerk;
    }

    protected function getUnitPowerFromVersusResourcePerk(Dominion $dominion, Dominion $target = null, Unit $unit, string $powerType, ?array $calc = []): float
    {
        if ($target === null && empty($calc))
        {
            return 0;
        }

        $versusResourcePerkData = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, "{$powerType}_vs_resource", null);

        if(!$versusResourcePerkData)
        {
            return 0;
        }

        $resource = (string)$versusResourcePerkData[0];
        $ratio = (int)$versusResourcePerkData[1];
        $max = (int)$versusResourcePerkData[2];

        $targetResources = 0;
        if (!empty($calc))
        {
            # Override resource amount for invasion calculator
            if (isset($calc[$resource]))
            {
                $targetResources = (int)$calc[$resource];
            }
        }
        elseif ($target !== null)
        {
            $targetResources = $target->{'resource_' . $resource};
        }

        $powerFromResource = $targetResources / $ratio;
        if ($max < 0)
        {
            $powerFromPerk = max(-1 * $powerFromResource, $max);
        }
        else
        {
            $powerFromPerk = min($powerFromResource, $max);
        }

        # No resource bonus vs. Barbarian (for now)
        if($target !== null and $target->race->name == 'Barbarian')
        {
          $powerFromPerk = 0;
        }

        return $powerFromPerk;
    }

    protected function getUnitPowerFromResourcePerk(Dominion $dominion, Unit $unit, string $powerType): float
    {

        $fromResourcePerkData = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, "{$powerType}_from_resource", null);

        if(!$fromResourcePerkData)
        {
            return 0;
        }

        $resource = (string)$fromResourcePerkData[0];
        $ratio = (int)$fromResourcePerkData[1];
        $max = (int)$fromResourcePerkData[2];

        $resourceAmount = $targetResources = $dominion->{'resource_' . $resource};

        $powerFromResource = $resourceAmount / $ratio;
        if ($max < 0)
        {
            $powerFromPerk = max(-1 * $powerFromResource, $max);
        }
        elseif($max == 0)
        {
            $powerFromPerk = $powerFromResource;
        }
        else
        {
            $powerFromPerk = min($powerFromResource, $max);
        }

        return $powerFromPerk;
    }


      protected function getUnitPowerFromMob(Dominion $dominion, Dominion $target = null, Unit $unit, string $powerType, ?array $calc = [], array $units = null): float
      {

          if ($target === null && empty($calc))
          {
              return 0;
          }

          $mobPerk = $dominion->race->getUnitPerkValueForUnitSlot($unit->slot, "{$powerType}_mob", null);

          if(!$mobPerk)
          {
              return 0;
          }

          $powerFromPerk = 0;

          if (!empty($calc))
          {
              return 0;
              /*
              # Override resource amount for invasion calculator
              if (isset($calc['opposing_units']))
              {
                  $unitsToBeSent = $calc['unit[1]'] + $calc['unit[4]'];
                  if($unitsToBeSent > $calc['opposing_units'])
                  {
                    $powerFromPerk = $mobPerk[0];
                  }
              }
              */
          }
          elseif ($target !== null)
          {
            # mob_on_offense: Do we ($units) outnumber the defenders ($target)?
            if($powerType == 'offense')
            {
              $targetUnits = 0;
              $targetUnits += $target->draftees;
              $targetUnits += $target->military_unit1;
              $targetUnits += $target->military_unit2;
              $targetUnits += $target->military_unit3;
              $targetUnits += $target->military_unit4;
              if(array_sum($units) > $targetUnits)
              {
                $powerFromPerk = $mobPerk[0];
              }
            }

            # mob_on_offense: Do we ($dominion) outnumber the attackers ($units)?
            if($powerType == 'defense')
            {
              $mobUnits = 0;
              $mobUnits += $dominion->draftees;
              $mobUnits += $dominion->military_unit1;
              $mobUnits += $dominion->military_unit2;
              $mobUnits += $dominion->military_unit3;
              $mobUnits += $dominion->military_unit4;

              Log::debug('$unit->name = ' . $unit->name . '(' . $unit->slot .')');
              Log::debug('$mobUnits = ' . $mobUnits);
              Log::debug('array_sum($units) = ' . array_sum($units));
              Log::debug('***');

              if($mobUnits > array_sum($units))
              {
                $powerFromPerk = $mobPerk[0];
              }
            }
          }

          return $powerFromPerk;
      }


    /**
     * Returns the Dominion's morale modifier.
     *
     * Net OP/DP gets lowered linearly by up to -20% at 0% morale.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getMoraleMultiplier(Dominion $dominion): float
    {
        return 0.90 + $dominion->morale / 1000;
    }

    /**
     * Returns the Dominion's spy ratio.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getSpyRatio(Dominion $dominion, string $type = 'offense'): float
    {
        return ($this->getSpyRatioRaw($dominion, $type) * $this->getSpyRatioMultiplier($dominion));
    }

    /**
     * Returns the Dominion's raw spy ratio.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getSpyRatioRaw(Dominion $dominion, string $type = 'offense'): float
    {
        $spies = $dominion->military_spies;

        // Add units which count as (partial) spies (Lizardfolk Chameleon)
        foreach ($dominion->race->units as $unit) {
            if ($type === 'offense' && $unit->getPerkValue('counts_as_spy_offense')) {
                $spies += floor($dominion->{"military_unit{$unit->slot}"} * (float) $unit->getPerkValue('counts_as_spy_offense'));
            }

            if ($type === 'defense' && $unit->getPerkValue('counts_as_spy_defense')) {
                $spies += floor($dominion->{"military_unit{$unit->slot}"} * (float) $unit->getPerkValue('counts_as_spy_defense'));
            }
        }

        return ($spies / $this->landCalculator->getTotalLand($dominion));
    }

    /**
     * Returns the Dominion's spy ratio multiplier.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getSpyRatioMultiplier(Dominion $dominion): float
    {
        $multiplier = 0;

        // Racial bonus
        $multiplier += $dominion->race->getPerkMultiplier('spy_strength');

        # Hideouts
        $multiplier += $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'hideouts');

        // Tech
        $multiplier += $dominion->getTechPerkMultiplier('spy_strength');

        // Beastfolk: Cavern increases Spy Strength
        if($dominion->race->name == 'Beastfolk')
        {
          $multiplier += 1 * ($dominion->{"land_cavern"} / $this->landCalculator->getTotalLand($dominion));
        }

        return (1 + $multiplier);
    }

    /**
     * Returns the Dominion's spy strength regeneration.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getSpyStrengthRegen(Dominion $dominion): float
    {
        $regen = 4;

        // todo: Spy Master / Dark Artistry tech

        return (float)$regen;
    }

    /**
     * Returns the Dominion's wizard ratio.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getWizardRatio(Dominion $dominion, string $type = 'offense'): float
    {
        return ($this->getWizardRatioRaw($dominion, $type) * $this->getWizardRatioMultiplier($dominion));
    }

    /**
     * Returns the Dominion's raw wizard ratio.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getWizardRatioRaw(Dominion $dominion, string $type = 'offense'): float
    {
        $wizards = $dominion->military_wizards + ($dominion->military_archmages * 2);

        // Add units which count as (partial) spies (Dark Elf Adept)
        foreach ($dominion->race->units as $unit) {
            if ($type === 'offense' && $unit->getPerkValue('counts_as_wizard_offense')) {
                $wizards += floor($dominion->{"military_unit{$unit->slot}"} * (float) $unit->getPerkValue('counts_as_wizard_offense'));
            }

            if ($type === 'defense' && $unit->getPerkValue('counts_as_wizard_defense')) {
                $wizards += floor($dominion->{"military_unit{$unit->slot}"} * (float) $unit->getPerkValue('counts_as_wizard_defense'));
            }
        }

        return ($wizards / $this->landCalculator->getTotalLand($dominion));
    }

    /**
     * Returns the Dominion's wizard ratio multiplier.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getWizardRatioMultiplier(Dominion $dominion): float
    {
        $multiplier = 0;

        // Racial bonus
        $multiplier += $dominion->race->getPerkMultiplier('wizard_strength');

        // Improvement: Towers
        $multiplier += $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'towers');

        // Tech
        $multiplier += $dominion->getTechPerkMultiplier('wizard_strength');

        // Beastfolk: Swamp increases Wizard Strength
        if($dominion->race->name == 'Beastfolk')
        {
          $multiplier += 2 * ($dominion->{"land_swamp"} / $this->landCalculator->getTotalLand($dominion))  * $this->prestigeCalculator->getPrestigeMultiplier($dominion);
        }

        return (1 + $multiplier);
    }

    /**
     * Returns the Dominion's wizard strength regeneration.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getWizardStrengthRegen(Dominion $dominion): float
    {
        $regen = 5;

        // todo: Master of Magi / Dark Artistry tech
        // todo: check if this needs to be a float

        return (float)$regen;
    }

    /**
     * Returns the number of boats protected by a Dominion's docks and harbor improvements.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getBoatsProtected(Dominion $dominion): float
    {
        // Docks
        $boatsProtected = static::BOATS_PROTECTED_PER_DOCK * $dominion->building_dock;
        // Habor
        $boatsProtected *= 1 + $this->improvementCalculator->getImprovementMultiplierBonus($dominion, 'harbor');
        return $boatsProtected;
    }

    /**
     * Gets the total amount of living specialist/elite units for a Dominion.
     *
     * Total amount includes units at home and units returning from battle.
     *
     * @param Dominion $dominion
     * @param int $slot
     * @return int
     */
    public function getTotalUnitsForSlot(Dominion $dominion, int $slot): int
    {
        return (
            $dominion->{'military_unit' . $slot} +
            $this->queueService->getInvasionQueueTotalByResource($dominion, "military_unit{$slot}")
        );
    }

    /**
     * Returns the number of time the Dominion was recently invaded.
     *
     * 'Recent' refers to the past 6 hours.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getRecentlyInvadedCount(Dominion $dominion): int
    {
        // todo: this touches the db. should probably be in invasion or military service instead
        $invasionEvents = GameEvent::query()
            #->where('created_at', '>=', now()->subDay(1))
            ->where('created_at', '>=', now()->subHours(6))
            ->where([
                'target_type' => Dominion::class,
                'target_id' => $dominion->id,
                'type' => 'invasion',
            ])
            ->get();

        if ($invasionEvents->isEmpty())
        {
            return 0;
        }

        $invasionEvents = $invasionEvents->filter(function (GameEvent $event) {
            return !$event->data['result']['overwhelmed'];
        });

        return $invasionEvents->count();
    }

    /**
     * Returns the number of time the Dominion was recently invaded by the attacker.
     *
     * 'Recent' refers to the past 6 hours.
     *
     * @param Dominion $dominion
     * @return int
     */
    public function getRecentlyInvadedCountByAttacker(Dominion $dominion, Dominion $attacker, int $hours = 2): int
    {
        $invasionEvents = GameEvent::query()
            ->where('created_at', '>=', now()->subHours($hours))
            ->where([
                'target_type' => Dominion::class,
                'target_id' => $dominion->id,
                'source_id' => $attacker->id,
                'type' => 'invasion',
            ])
            ->get();

        if ($invasionEvents->isEmpty())
        {
            return 0;
        }

        $invasionEvents = $invasionEvents->filter(function (GameEvent $event)
        {
            return !$event->data['result']['overwhelmed'];
        });

        return $invasionEvents->count();
    }

    /**
     * Checks Dominion was recently invaded by attacker.
     *
     * 'Recent' refers to the past 24 hours.
     *
     * @param Dominion $dominion
     * @param Dominion $attacker
     * @return bool
     */
    public function recentlyInvadedBy(Dominion $dominion, Dominion $attacker): bool
    {
        // todo: this touches the db. should probably be in invasion or military service instead
        $invasionEvents = GameEvent::query()
            ->where('created_at', '>=', now()->subDay(1))
            ->where([
                'target_type' => Dominion::class,
                'target_id' => $dominion->id,
                'source_id' => $attacker->id,
                'type' => 'invasion',
            ])
            ->get();

        if (!$invasionEvents->isEmpty()) {
            return true;
        }

        return false;
    }


    /**
     * Checks if $defender recently invaded $attacker's realm.
     *
     * 'Recent' refers to the past 24 hours.
     *
     * @param Dominion $dominion
     * @param Dominion $attacker
     * @return bool
     */
    public function recentlyInvadedAttackersRealm(Dominion $attacker, Dominion $defender = null): bool
    {
        if($defender)
        {
          $invasionEvents = GameEvent::query()
                              ->join('dominions as source_dominion','game_events.source_id','source_dominion.id')
                              ->join('dominions as target_dominion','game_events.target_id','target_dominion.id')
                              ->where('game_events.created_at', '>=', now()->subHours(6))
                              ->where([
                                  'game_events.type' => 'invasion',
                                  'source_dominion.realm_id' => $defender->realm_id,
                                  'target_dominion.realm_id' => $attacker->realm_id,
                              ])
                              ->get();

            if (!$invasionEvents->isEmpty())
            {
                return true;
            }
            else
            {
              return false;
            }
        }
        else
        {
            return false;
        }

    }


    # ODA functions

    /**
     * Gets the dominion's bonus from Gryphon Nests.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getGryphonNestMultiplier(Dominion $dominion): float
    {
      if ($this->spellCalculator->isSpellActive($dominion, 'gryphons_call'))
      {
          return 0;
      }
      $multiplier = 0;
      $multiplier = ($dominion->building_gryphon_nest / $this->landCalculator->getTotalLand($dominion)) * 2;

      return min($multiplier, 0.40);
    }

    /**
     * Gets the dominion's OP or DP ($power) bonus from spells.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getSpellMultiplier(Dominion $dominion, Dominion $target = null, string $power = null): float
    {

      $multiplier = 0;

      if($power == 'offense')
      {
        // Spell: Bloodrage (+10% OP)
        if ($this->spellCalculator->isSpellActive($dominion, 'bloodrage'))
        {
          $multiplier += 0.10;
        }

        // Spell: Crusade (+10% OP)
        if ($this->spellCalculator->isSpellActive($dominion, 'crusade'))
        {
          $multiplier += 0.10;
        }

        // Spell: Howling (+10% OP)
        if ($this->spellCalculator->isSpellActive($dominion, 'howling'))
        {
          $multiplier += 0.10;
        }

        // Spell: Coastal Cannons
        if ($this->spellCalculator->isSpellActive($dominion, 'killing_rage'))
        {
          $multiplier += 0.10;
        }

        // Spell: Warsong (+10% OP)
        if ($this->spellCalculator->isSpellActive($dominion, 'warsong'))
        {
          $multiplier += 0.10;
        }

        // Spell: Nightfall (+5% OP)
        if ($this->spellCalculator->isSpellActive($dominion, 'nightfall'))
        {
          $multiplier += 0.05;
        }

        // Spell: Aether (+10% OP)
        # Condition: must have equal amounts of every unit.
        if ($this->spellCalculator->isSpellActive($dominion, 'aether'))
        {
          if($dominion->military_unit1 > 0
            and $dominion->military_unit1 == $dominion->military_unit2
            and $dominion->military_unit2 == $dominion->military_unit3
            and $dominion->military_unit3 == $dominion->military_unit4)
            {
              $multiplier += 0.10;
            }
        }

        // Spell: Retribution (+20% OP)
        # Condition: target must have invaded $dominion's realm in the last six hours.
        if ($this->spellCalculator->isSpellActive($dominion, 'retribution'))
        {
          if($this->recentlyInvadedAttackersRealm($dominion, $target))
          {
            $multiplier += 0.20;
          }
        }

      }
      elseif($power == 'defense')
      {
        // Spell: Howling (+10% DP)
        #if ($this->spellCalculator->isSpellActive($dominion, 'howling'))
        #{
        #  $multiplier += 0.10;
        #}

        // Spell: Icekin Blizzard (+5% DP)
        if ($this->spellCalculator->isSpellActive($dominion, 'blizzard'))
        {
          $multiplier += 0.05;
        }

        // Spell: Halfling Defensive Frenzy (+20% DP)
        if ($this->spellCalculator->isSpellActive($dominion, 'defensive_frenzy'))
        {
          $multiplier += 0.10;
        }

        // Spell: Coastal Cannons
        if ($this->spellCalculator->isSpellActive($dominion, 'coastal_cannons'))
        {
          $multiplierFromCoastalCannons = $dominion->{'land_water'} / $this->landCalculator->getTotalLand($dominion);
          $multiplier += min($multiplierFromCoastalCannons,0.20);
        }

        // Spell: Norse Fimbulwinter (+10% DP)
        if ($this->spellCalculator->isSpellActive($dominion, 'fimbulwinter'))
        {
          $multiplier += 0.10;
        }

        // Spell: Simian Rainy Season (+100% DP)
        if ($this->spellCalculator->isSpellActive($dominion, 'rainy_season'))
        {
          $multiplier += 1.00;
        }

        // Spell: Aether (+10% DP)
        # Condition: must have equal amounts of every unit.
        if ($this->spellCalculator->isSpellActive($dominion, 'aether'))
        {
          if($dominion->military_unit1 > 0
            and $dominion->military_unit1 == $dominion->military_unit2
            and $dominion->military_unit2 == $dominion->military_unit3
            and $dominion->military_unit3 == $dominion->military_unit4)
            {
              $multiplier += 0.10;
            }
        }

      }
      else
      {
        $multiplier = 0; # Remove this eventually.
      }

      return $multiplier;

    }

    /**
     * Gets the dominion's bonus from Guard Towers.
     *
     * @param Dominion $dominion
     * @return float
     */
    public function getGuardTowerMultiplier(Dominion $dominion): float
    {
      $multiplier = 0;
      $multiplier = ($dominion->building_guard_tower / $this->landCalculator->getTotalLand($dominion)) * 2;

      return min($multiplier, 0.40);
    }
}
