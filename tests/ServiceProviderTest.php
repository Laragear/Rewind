<?php

namespace Tests;

use Illuminate\Support\ServiceProvider;
use Laragear\Rewind\RewindServiceProvider;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use function method_exists;
use function now;

class ServiceProviderTest extends TestCase
{
    public function stopTime(): void
    {
        $this->freezeSecond();
    }

    /**
     * @define-env stopTime
     */
    #[DefineEnvironment('stopTime')]
    public function test_publishes_migrations(): void
    {
        static::assertArrayHasKey('migrations', ServiceProvider::$publishGroups);

        if (method_exists(ServiceProvider::class, 'publishesMigrations')) {
            static::assertArrayHasKey(RewindServiceProvider::MIGRATIONS, ServiceProvider::$publishGroups['migrations']);
        } else {
            $format = now()->addSecond()->format('Y_m_d_Hmi') .  '_create_rewind_states_table.php';

            static::assertArrayHasKey(RewindServiceProvider::MIGRATIONS . '/0000_00_00_000000_create_rewind_states_table.php', ServiceProvider::$publishGroups['migrations']);

            static::assertSame($this->app->databasePath("migrations/$format"), ServiceProvider::$publishGroups['migrations'][RewindServiceProvider::MIGRATIONS . '/0000_00_00_000000_create_rewind_states_table.php']);
        }
    }
}
