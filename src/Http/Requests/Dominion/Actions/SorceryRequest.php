<?php

namespace OpenDominion\Http\Requests\Dominion\Actions;

use OpenDominion\Http\Requests\Dominion\AbstractDominionRequest;

class SorceryRequest extends AbstractDominionRequest
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            'spell' => 'required',
            'target_dominion' => 'required',
            'wizard_strength' => 'required',

            'spell' => 'integer|exists:spells,id',
            'target_dominion' => 'integer|exists:dominions,id',

            'enhancement_resource' => 'integer|exists:resources,id',
            'enhancement_amount' => 'integer',

            'wizard_strength' => 'integer',

        ];
    }
}
