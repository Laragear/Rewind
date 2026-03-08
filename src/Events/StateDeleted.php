<?php

namespace Laragear\Rewind\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

readonly class StateDeleted
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(public Model $model, public int $stateId)
    {
        //
    }
}
