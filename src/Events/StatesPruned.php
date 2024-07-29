<?php

namespace Laragear\Rewind\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class StatesPruned
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(public Model $model, public bool $includesKept)
    {
        //
    }
}
