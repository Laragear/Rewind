<?php

namespace Tests\Events;

use Illuminate\Support\Facades\Event;
use Laragear\Rewind\Events\StateRestored;
use Tests\Fixtures\TestModel;
use Tests\TestCase;

class StateRestoredTest extends TestCase
{
    public function test_dispatches(): void
    {
        $model = new TestModel(['title' => 'test_title']);

        $event = Event::fake(StateRestored::class);

        StateRestored::dispatch($model);

        $event->assertDispatched(StateRestored::class, function (StateRestored $event) use ($model): bool {
            return $event->model === $model;
        });
    }
}
