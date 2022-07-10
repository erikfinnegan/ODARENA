<?php

namespace OpenDominion\Http\Requests\Dominion\Actions;

use OpenDominion\Http\Requests\Dominion\AbstractDominionRequest;

class InsightActionRequest extends AbstractDominionRequest
{
  /**
   * {@inheritdoc}
   */
  public function rules()
  {
      return [
          'target_dominion_id' => 'integer|exists:dominions,id',
          'round_tick' => 'integer',
      ];
  }
}
