<?php

namespace OpenDominion\Helpers;

#use OpenDominion\Models\Race;

class BarbarianHelper
{
    public function getBarbarianReservedNames(): array
    {
        return [
            'Logan',
            'Drayette',
            'Broderick',
            'Yaven',
            'Xhavia',
            'Hork',
            'Desmer',
            'Flyrie',
            'Ragnar',
            'O\'Mannu',
            'Al-Mah',
            'Jotta',
            'Zeke',
            'Ivi',
            'Eduin',
            'Rhih',
            'Llotl',
            'Dolff',
            'Onnak',
            'Hyelkix',
            'Sneirn',
            'Sebit',
            'Garij',
            'Wymer',
            'Baskasa',
            'Chyngyry',
            'Khonef',
            'Laccare',
            'Murth',
            'Nidji',
            'Psakh',
            'Qo Isk',
            'Tracc',
            'Umesk',
            'Bhisgat',
            'Sohu',
            'Gilleg',
            'Fhūlj',
            'Ssiwen',
            'Takadagabant',
            'Jeella',
            'Tedé',
            'Cicito',
        ];
    }

    public function isBarbarianReservedName(string $name): bool
    {
        $barbarianNames = $this->getBarbarianReservedNames();

        return in_array($name, $barbarianNames);
    }
}
