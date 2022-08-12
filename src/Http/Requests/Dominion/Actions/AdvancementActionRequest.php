<?php

namespace OpenDominion\Http\Requests\Dominion\Actions;

use OpenDominion\Http\Requests\Dominion\AbstractDominionRequest;

class AdvancementActionRequest extends AbstractDominionRequest
{
    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            'advancement_id' => 'required|integer|exists:advancements,id',
        ];
    }
}
