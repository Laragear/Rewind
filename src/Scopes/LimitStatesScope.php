<?php

namespace Laragear\Rewind\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Laragear\Rewind\Models\RewindState;

/**
 * @internal
 */
class LimitStatesScope implements Scope
{
    /**
     * Create a new Scope instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model&\Tests\HasRewindTest  $target
     */
    public function __construct(protected Model $target)
    {
        //
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Model&\Laragear\Rewind\HasRewind  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        [$amount, $datetime] = $this->getLimits();

        $builder
            ->orderByDesc('id')
            ->when($amount)->limit($amount)
            ->when($datetime)->where(RewindState::CREATED_AT, '>=', $datetime);
    }

    /**
     * Retrieve and normalize the limits for rewinding.
     *
     * @return array{0: int|null, 1: \DateTimeInterface|null}
     */
    protected function getLimits(): array
    {
        $value = $this->target->rewindLimit();

        if (!$value) {
            return [null, null];
        }

        if (is_array($value)) {
            return is_int($value[0]) ? [$value[0], $value[1]] : [$value[1], $value[0]];
        }

        if (is_int($value)) {
            return [$value, null];
        }

        return [null, $value];
    }
}
