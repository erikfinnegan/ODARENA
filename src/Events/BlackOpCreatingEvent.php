<?php

namespace OpenDominion\Events;

use Illuminate\Queue\SerializesModels;
use OpenDominion\Models\BlackOp;

class BlackOpCreatingEvent
{
    use SerializesModels;

    public $blackOp;

    /**
     * Create a new event instance.
     *
     * @param BlackOp $blackOp
     */
    public function __construct(BlackOp $blackOp)
    {
        $this->blackOp = $blackOp;
    }
}
