<?php

namespace Laragear\Rewind\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Laragear\Rewind\Models\RewindState;

readonly class StateCreated
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(public Model $model, public RewindState $state)
    {
        //
    }
}
