<?php

namespace Laragear\Rewind;

use Illuminate\Support\ServiceProvider;
use function method_exists;
use function now;

class RewindServiceProvider extends ServiceProvider
{
    public const MIGRATIONS = __DIR__.'/../database/migrations';

    /**
     * Boot the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        if (method_exists($this, 'publishesMigrations')) {
            $this->publishesMigrations([static::MIGRATIONS => $this->app->databasePath('migrations')], 'migrations');
        } else {
            $this->publishes([
                static::MIGRATIONS.'/0000_00_00_000000_create_rewind_states_table.php' =>
                    $this->app->databasePath(
                        'migrations/'.now()->addSecond()->format('Y_m_d_Hmi').'_create_rewind_states_table.php'
                    )
            ], 'migrations');
        }
    }
}
