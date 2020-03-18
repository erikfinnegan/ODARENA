<?php

namespace OpenDominion\Helpers;

class OpsHelper
{
    /*
    public function operationSuccessChance(float $selfRatio, float $targetRatio, float $multiplier, bool $isInvasionSpell = false): float
    {
        if($isInvasionSpell)
        {
          return 1;
        }
        $ratio = $selfRatio / $targetRatio;
        $successRate = 0.8 ** (2 / (($ratio * $multiplier) ** 1.2));
        return clamp($successRate, 0, 1);
    }
    */

    # RVV DID THIS:
    /**
    * Error function (also called the Gauss error function)
    * Value is computed using
    * "Numerical Recipes in Fortran 77: The Art of Scientific Computing"
    * (ISBN 0-521-43064-X), 1992, page 214, Cambridge University Press.
    * Maximum error is 1.2 * 10^-7
    *
    * https://en.wikipedia.org/wiki/Error_function
    *
    * @param float $x
    * @return float
    */
    public function erf($x){
        $t =1 / (1 + 0.5 * abs($x));
        $tau = $t * exp(
            - $x * $x
            - 1.26551223
            + 1.00002368 * $t
            + 0.37409196 * $t * $t
            + 0.09678418 * $t * $t * $t
            - 0.18628806 * $t * $t * $t * $t
            + 0.27886807 * $t * $t * $t * $t * $t
            - 1.13520398 * $t * $t * $t * $t * $t * $t
            + 1.48851587 * $t * $t * $t * $t * $t * $t * $t
            - 0.82215223 * $t * $t * $t * $t * $t * $t * $t * $t
            + 0.17087277 * $t * $t * $t * $t * $t * $t * $t * $t * $t);

        if ($x >= 0) {
            return 1 - $tau;
        } else {
            return $tau - 1;
        }
    }

    public function operationSuccessChance(float $selfRatio, float $targetRatio, float $multiplier, bool $isInvasionSpell = false): float
    {
        if ($isInvasionSpell)
        {
            return 1;
        }

        $ratio = $selfRatio / $targetRatio;
        $successRate = 0.8 ** (2 / (($ratio * $multiplier) ** 1.2));
        return clamp($successRate, 0, 1);
    }

    public function blackOperationSuccessChance(float $selfRatio, float $targetRatio, bool $isInvasionSpell = false): float
    {
        $ratioRelative = $selfRatio / $targetRatio;
        if($isInvasionSpell)
        {
          $ratioRelative = $selfRatio / 0.001;
        }
        $ratioDifference = $selfRatio - $targetRatio;
        $steepness = 1 / 2.5;
        $shift = 0;

        $successRate = ($this->erf(($ratioRelative - $shift) * $steepness) + 1) / 2;

        return clamp($successRate, 0, 1);
    }

}
