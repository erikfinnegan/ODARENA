<?php

namespace OpenDominion\Http\Requests\Dominion\Actions;

use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Http\Requests\Dominion\AbstractDominionRequest;

class ReleaseActionRequest extends AbstractDominionRequest
{
    /** @var UnitHelper */
    protected $unitHelper;

    /**
     * ReleaseActionRequest constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->unitHelper = app(UnitHelper::class);
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        $rules = [];

        $rules['train.unit1'] = 'integer|nullable|min:0';
        $rules['train.unit2'] = 'integer|nullable|min:0';
        $rules['train.unit3'] = 'integer|nullable|min:0';
        $rules['train.unit4'] = 'integer|nullable|min:0';
        $rules['train.unit5'] = 'integer|nullable|min:0';
        $rules['train.unit6'] = 'integer|nullable|min:0';
        $rules['train.unit7'] = 'integer|nullable|min:0';
        $rules['train.unit8'] = 'integer|nullable|min:0';
        $rules['train.unit9'] = 'integer|nullable|min:0';
        $rules['train.unit10'] = 'integer|nullable|min:0';
        $rules['train.spies'] = 'integer|nullable|min:0';
        $rules['train.wizards'] = 'integer|nullable|min:0';
        $rules['train.archmages'] = 'integer|nullable|min:0';

        return $rules;
    }
}
