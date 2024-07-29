<?php

namespace Tests\Fixtures;

class TestModelAttributesFromRewindState extends TestModel
{
    protected $table = 'test_models';

    public function setAttributesFromRewindState(array $attributes): void
    {
        $this->attributes = ['foo' => 'bar'];
    }
}
