<?php

namespace OpenDominion\Http\Requests\Dominion\Actions;

use OpenDominion\Http\Requests\Dominion\AbstractDominionRequest;

class TheftRequest extends AbstractDominionRequest
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        $rules['resource'] = 'integer|exists:resources,id';
        $rules['target_dominion'] = 'integer|exists:dominions,id';
        for ($i = 1; $i <= 4; $i++)
        {
            $rules['unit.' . $i] = 'integer|nullable|min:0';
        }

        return $rules;
    }
}
