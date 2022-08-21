<?php

namespace OpenDominion\Http\Requests\Dominion\Actions;

use OpenDominion\Http\Requests\Dominion\AbstractDominionRequest;

class TitleChangeActionRequest extends AbstractDominionRequest
{
  /**
   * {@inheritdoc}
   */
  public function rules()
  {
      return [
          'title_id' => 'integer|exists:titles,id',
      ];
  }
}
