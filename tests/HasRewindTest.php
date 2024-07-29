<?php

namespace Tests;

use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Laragear\Rewind\Events\StateCreated;
use Laragear\Rewind\Events\StatesPruned;
use Laragear\Rewind\Models\RewindState;
use Tests\Fixtures\TestModel;
use Tests\Fixtures\TestModelAttributesFromRewindState;
use function now;

class HasRewindTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->app
            ->make(SchemaBuilder::class)
            ->create('test_models', static function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->json('invites')->default('"[]"');
                $table->string('private_notes')->default('');
                $table->timestamp('starts_at');
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
            });
    }

    public function test_created_model_created_state(): void
    {
        $event = Event::fake([StateCreated::class, StatesPruned::class]);

        $now = now();

        /** @var \Tests\Fixtures\TestModel $model */
        $model = TestModel::create([
            'title' => 'test_title',
            'starts_at' => $now
        ]);

        $this->assertDatabaseHas(RewindState::class, [
            'rewindable_type' => TestModel::class,
            'rewindable_id' => 1,
            'data->title' => 'test_title',
            'data->starts_at' => $now->toJSON(),
            'data->updated_at' => $now->toDateTimeString(),
            'data->created_at' => $now->toDateTimeString(),
        ]);

        $event->assertDispatched(
            StateCreated::class, static function (StateCreated $event) use ($model): bool {
            return $event->model->is($model)
                && $event->state->data['id'] === $model->getKey();
        });

        $event->assertNotDispatched(StatesPruned::class);
    }

    public function test_update_model_creates_state(): void
    {
        /** @var \Tests\Fixtures\TestModel $model */
        $model = TestModel::create([
            'title' => 'test_title',
            'starts_at' => $startsAt = now()
        ]);

        $event = Event::fake([StateCreated::class, StatesPruned::class]);

        $this->freezeSecond();

        $model->update([
            'title' => 'updated_title'
        ]);

        $this->assertDatabaseHas(RewindState::class, [
            'id' => 2,
            'rewindable_type' => TestModel::class,
            'rewindable_id' => 1,
            'data->title' => 'updated_title',
            'data->starts_at' => $startsAt->toJSON(),
            'data->updated_at' => now()->toDateTimeString(),
            'data->created_at' => now()->toDateTimeString(),
        ]);

        $event->assertDispatched(StateCreated::class, static function (StateCreated $event) use ($model): bool {
            return $event->model->is($model)
                && $event->state->data['id'] === $model->getKey();
        });

        $event->assertDispatched(StatesPruned::class, static function (StatesPruned $event) use ($model): bool {
            return $event->model->is($model);
        });
    }

    public function test_without_pushing_states(): void
    {
        $event = Event::fake([StateCreated::class, StatesPruned::class]);

        $result = TestModel::withoutCreatingStates(static function (): string {
            TestModel::create([
                'title' => 'test_title',
                'starts_at' => now()
            ])->update([
                'test_title'
            ]);

            return 'ok';
        });

        static::assertSame('ok', $result);

        $this->assertDatabaseEmpty(RewindState::class);

        $event->assertNotDispatched(StateCreated::class);
        $event->assertNotDispatched(StatesPruned::class);
    }

    public function test_sets_rewind_limit_as_date_and_prunes_old_state(): void
    {
        $class = new class extends TestModel {
            protected $table = 'test_models';

            public function rewindLimit(): DateTimeInterface|int|array|null|false
            {
                return now()->subDay();
            }

            public function getMorphClass()
            {
                return TestModel::class;
            }
        };

        $class->fill([
            'title' => 'test_title',
            'starts_at' => now(),
        ])->save();

        $this->travelTo(now()->addDays(2));

        $class->update(['title' => 'updated_title']);

        $this->assertDatabaseCount(RewindState::class, 1);
        $this->assertDatabaseMissing(RewindState::class, ['id' => 1]);
    }

    public function test_sets_rewind_limit_as_amount_and_date_and_prunes_old_state(): void
    {
        $class = new class extends TestModel {
            protected $table = 'test_models';

            public function rewindLimit(): DateTimeInterface|int|array|null|false
            {
                return [2, now()->subDay()];
            }

            public function getMorphClass()
            {
                return TestModel::class;
            }
        };

        $class->fill([
            'title' => 'test_title',
            'starts_at' => now(),
        ])->save();

        $this->travelTo(now()->addDays(2));

        $class->update(['title' => 'updated_title']);
        $class->update(['title' => 'new_title']);
        $class->update(['title' => 'last_title']);

        $this->assertDatabaseCount(RewindState::class, 2);
        $this->assertDatabaseMissing(RewindState::class, [['id' => 1], ['id' => 2]]);
    }

    public function test_sets_rewind_limit_as_falsy_and_does_not_prune_old_states(): void
    {
        $event = Event::fake([StatesPruned::class]);

        $class = new class extends TestModel {
            protected $table = 'test_models';

            public function rewindLimit(): DateTimeInterface|int|array|null|false
            {
                return false;
            }

            public function getMorphClass()
            {
                return TestModel::class;
            }
        };

        $class->fill([
            'title' => 'test_title',
            'starts_at' => now(),
        ])->save();

        $this->travelTo(now()->addDays(2));

        $class->update(['title' => 'updated_title']);
        $class->update(['title' => 'new_title']);
        $class->update(['title' => 'last_title']);

        $this->assertDatabaseCount(RewindState::class, 4);

        $event->assertNotDispatched(StatesPruned::class);
    }

    public function test_doesnt_creates_rewind_state_on_created(): void
    {
        $event = Event::fake([StateCreated::class, StatesPruned::class]);

        $class = new class extends TestModel {
            protected $table = 'test_models';

            public function shouldCreateRewindStateOnCreated(): bool
            {
                return false;
            }

            public function getMorphClass()
            {
                return 'test_class';
            }
        };

        $class->fill([
            'title' => 'test_title',
            'starts_at' => now(),
        ])->save();

        $this->assertDatabaseEmpty(RewindState::class);

        $event->assertNotDispatched(StateCreated::class);
        $event->assertNotDispatched(StatesPruned::class);
    }

    public function test_doesnt_creates_rewind_state_on_updated(): void
    {
        $class = new class extends TestModel {
            protected $table = 'test_models';

            public function shouldCreateRewindStateOnUpdated(): bool
            {
                return false;
            }

            public function getMorphClass()
            {
                return TestModel::class;
            }
        };

        $class->fill([
            'title' => 'test_title',
            'starts_at' => now(),
        ])->save();

        $event = Event::fake([StateCreated::class, StatesPruned::class]);

        $class->update(['title' => 'updated_title']);

        $this->assertDatabaseCount(RewindState::class, 1);

        $event->assertNotDispatched(StateCreated::class);
        $event->assertNotDispatched(StatesPruned::class);
    }

    public function test_doesnt_prune_old_rewind_states_on_updated(): void
    {
        $class = new class extends TestModel {
            protected $table = 'test_models';

            public function shouldPruneOldRewindStatesOnUpdated(): bool
            {
                return false;
            }

            public function getMorphClass()
            {
                return TestModel::class;
            }
        };

        $class->fill([
            'title' => 'test_title',
            'starts_at' => now(),
        ])->save();

        $event = Event::fake([StateCreated::class, StatesPruned::class]);

        $class->update(['title' => 'updated_title']);

        $this->assertDatabaseCount(RewindState::class, 2);

        $event->assertDispatched(StateCreated::class);
        $event->assertNotDispatched(StatesPruned::class);
    }

    public function test_keeps_first_rewind_state(): void
    {
        $class = new class extends TestModel {
            protected $table = 'test_models';

            public function shouldKeepFirstRewindState(): bool
            {
                return true;
            }

            public function getMorphClass()
            {
                return TestModel::class;
            }
        };

        $class->fill([
            'title' => 'test_title',
            'starts_at' => now(),
        ])->save();

        $this->travelTo(now()->addDays(2));

        $class->update(['title' => 'updated_title']);

        $this->assertDatabaseCount(RewindState::class, 2);
        $this->assertDatabaseHas(RewindState::class, ['id' => 1, 'data->title' => 'test_title']);
    }

    public function test_sends_attributes_to_rewind_state_using_arrayable(): void
    {
        $class = new class extends TestModel {
            protected $table = 'test_models';

            public function getAttributesForRewindState(): Arrayable|array
            {
                return new Collection([
                    'foo' => 'bar'
                ]);
            }

            public function getMorphClass()
            {
                return TestModel::class;
            }
        };

        $class->fill([
            'title' => 'test_title',
            'starts_at' => now(),
        ])->save();

        $this->assertDatabaseHas(RewindState::class, [
            'data->foo' => 'bar'
        ]);
    }

    public function test_sets_attributes_from_rewind_state(): void
    {
        $model = TestModelAttributesFromRewindState::create([
            'title' => 'test_title',
            'starts_at' => now(),
        ]);

        $model->rewind()->to(1);

        static::assertSame(['foo' => 'bar'], $model->getAttributes());
    }
}
