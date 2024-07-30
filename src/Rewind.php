<?php

namespace Laragear\Rewind;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilderContract;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Traits\Conditionable;
use Laragear\Rewind\Events\StateCreated;
use Laragear\Rewind\Events\StateDeleted;
use Laragear\Rewind\Events\StateRetrieved;
use Laragear\Rewind\Events\StatesCleared;
use Laragear\Rewind\Events\StatesPruned;

class Rewind
{
    use Conditionable;

    /**
     * Determine if the rewind logic should run.
     *
     * @internal Developers should use Model::withoutCreatingStates(callable $callback) instead.
     */
    public static bool $enabled = true;

    /**
     * The rewind relation to query.
     */
    protected MorphMany $relation;

    /**
     * Create a new rewind instance.
     */
    public function __construct(protected Model $target)
    {
        $this->relation = $this->target->morphMany(Models\RewindState::class, 'rewindable');
    }

    /**
     * Rewinds the model into a previous saved state.
     */
    public function to(int $id, string|array $only = []): Model
    {
        $attributes = $this->find($id)->getAttributes();

        if ($only) {
            $attributes = Arr::only($attributes, $only);
        }

        $this->target->setAttributesFromRewindState($attributes); // @phpstan-ignore-line

        return $this->target;
    }

    /**
     * Rewinds the model to the latest state.
     */
    public function toLatest(string|array $only = null): Model
    {
        $attributes = $this->findLatest()->getAttributes();

        if ($only) {
            $attributes = Arr::only($attributes, $only);
        }

        $this->target->setAttributesFromRewindState($attributes); // @phpstan-ignore-line

        return $this->target;
    }

    /**
     * Rewinds the model to the oldest state.
     */
    public function toOldest(string|array $only = null): Model
    {
        $attributes = $this->findOldest()->getAttributes();

        if ($only) {
            $attributes = Arr::only($attributes, $only);
        }

        $this->target->setAttributesFromRewindState($attributes); // @phpstan-ignore-line

        return $this->target;
    }

    /**
     * Returns the count of all states.
     */
    public function count(): int
    {
        return $this->queryStates()->count();
    }

    /**
     * Check if there is any state for the model.
     */
    public function exists(): bool
    {
        return $this->queryStates()->exists();
    }

    /**
     * Check if there is no state for the model.
     */
    public function missing(): bool
    {
        return ! $this->exists();
    }

    /**
     * Return the given state ID as a new model instance.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function find(int $id): Model
    {
        $state = $this->queryStates()  // @phpstan-ignore-line
            ->findOrFail($id, ['rewindable_type', 'data'])
            ->instanceRewindable();

        StateRetrieved::dispatch($this->target, $state);

        return $state;
    }

    /**
     * Return the latest state as a new model instance.
     */
    public function findLatest(): Model
    {
        return $this->queryStates()->orderByDesc('id')->firstOrFail([ // @phpstan-ignore-line
            'rewindable_type', 'data'
        ])->instanceRewindable();
    }

    /**
     * Return the oldest state as a new model instance.
     */
    public function findOldest(): Model
    {
        return $this->queryStates()->orderBy('id')->firstOrFail([ // @phpstan-ignore-line
            'rewindable_type', 'data'
        ])->instanceRewindable();
    }

    /**
     * Creates a new raw Eloquent Query Builder for the states.
     *
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function query(): BuilderContract
    {
        return $this->relation->newQuery(); // @phpstan-ignore-line
    }

    /**
     * Create a new query for the model states scoped to the item limits.
     */
    protected function queryStates(): EloquentBuilderContract
    {
        return $this->query()->withGlobalScope('limited', new Scopes\LimitStatesScope($this->target));
    }

    /**
     * Return all the past model instances.
     */
    public function all(): Collection
    {
        return $this->queryStates() // @phpstan-ignore-line
            ->get(['rewindable_type', 'data'])
            ->map(static function (Models\RewindState $model): Model { // @phpstan-ignore-line
                return $model->instanceRewindable();
            });
    }

    /**
     * Remove a given state by its ID.
     */
    public function delete(int $id): void
    {
        $this->query()->whereKey($id)->delete();

        StateDeleted::dispatch($this->target, $id);
    }

    /**
     * Remove the latest state, may include kept ones.
     */
    public function deleteLatest(bool $includeKept = false): void
    {
        /** @var \Laragear\Rewind\Models\RewindState|null $state */
        $state = $this->query()->orderByDesc('id')->first(['id', 'is_kept']);

        if ($state && (!$state->is_kept || $state->is_kept === $includeKept)) {
            $this->delete($state->getKey());
        }
    }

    /**
     * Remove the oldest state, may include kept ones.
     */
    public function deleteOldest(bool $includeKept = false): void
    {
        /** @var \Laragear\Rewind\Models\RewindState|null $state */
        $state = $this->query()->orderBy('id')->first(['id', 'is_kept']);

        if ($state && (!$state->is_kept || $state->is_kept === $includeKept)) {
            $this->delete($state->getKey());
        }
    }

    /**
     * Clear all previous states, may include kept ones.
     */
    public function clear(bool $includeKept = false): void
    {
        $this->query()
            ->unless($includeKept)->whereNot('is_kept', true)
            ->delete();

        StatesCleared::dispatch($this->target, $includeKept);
    }

    /**
     * Clear all previous states, included kept ones.
     */
    public function forceClear(): void
    {
        $this->clear(true);
    }

    /**
     * Pushes the current model state on top of the states stack, returning a new Model instance.
     */
    public function create(bool $keep = false, bool $prune = true, bool $includeKept = false): Model
    {
        /** @var \Laragear\Rewind\Models\RewindState $state */
        $state = $this->relation->make([
            'data' => $this->target->getAttributesForRewindState(), // @phpstan-ignore-line
            'is_kept' => $keep,
        ]);

        if (static::$enabled) {
            $state->save();

            StateCreated::dispatch($this->target, $state);

            if ($prune) {
                $this->prune($includeKept);
            }
        }

        return $state->instanceRewindable();
    }

    /**
     * Prune old model states (that are virtually outside the set limits).
     */
    public function prune(bool $includeKept = false): void
    {
        // If the target model has no set limit, we will just not execute this.
        if ($this->target->rewindLimit()) { // @phpstan-ignore-line
            $this->query()->whereNotIn('id', // @phpstan-ignore-line
                $this->queryStates()
                ->select('id')
                ->unless($includeKept)
                ->whereNot('is_kept', true)
            )->withoutGlobalScopes()->delete();

            StatesPruned::dispatch($this->target, $includeKept);
        }
    }
}
