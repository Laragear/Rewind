<?php

namespace Laragear\Rewind\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

readonly class StateRestored
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(public Model $model)
    {
        //
    }
}
