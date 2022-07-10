<?php

namespace OpenDominion\Http\Requests\Dominion\API;

use OpenDominion\Http\Requests\Dominion\AbstractDominionRequest;

class SorceryCalculationRequest extends AbstractDominionRequest
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            'spell' => 'integer|exists:spells,id',
            'wizard_strength' => 'integer|min:0',
        ];
    }
}
