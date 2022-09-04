<?php

namespace OpenDominion\Http\Requests\Dominion\Actions;

use OpenDominion\Http\Requests\Dominion\AbstractDominionRequest;

class ArtefactActionRequest extends AbstractDominionRequest
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        $rules['target_artefact'] = 'required|integer|exists:realm_artefacts,id';

        for ($i = 1; $i <= 10; $i++)
        {
            $rules['unit.' . $i] = 'integer|nullable|min:0';
        }

        $rules['spell'] = 'integer|nullale|exists:spells,id';
        $rules['wizard_strength'] = 'integer|nullable|min:0';
        $rules['spell']= 'integer|nullale|exists:spells,id';

        return $rules;
    }
}
