<?php

namespace OpenDominion\Http\Requests\Dominion\Actions;

use OpenDominion\Http\Requests\Dominion\AbstractDominionRequest;

class SabotageRequest extends AbstractDominionRequest
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {

        return [
            'spyop' => 'required',
            'target_dominion' => 'required',

            'spell' => 'integer|exists:spells,id',
            'target_dominion' => 'integer|exists:dominions,id',

            'unit.1' => 'integer|nullable|min:0',
            'unit.2' => 'integer|nullable|min:0',
            'unit.3' => 'integer|nullable|min:0',
            'unit.4' => 'integer|nullable|min:0',
            'unit.spies' => 'integer|nullable|min:0',

        ];
    }
}
