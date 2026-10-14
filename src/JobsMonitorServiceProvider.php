<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use MikhailMelnikov\JobsMonitor\Contracts\ActorResolver;
use MikhailMelnikov\JobsMonitor\Support\GuardActorResolver;

/**
 * Registers the admin-agnostic core: config, translations and default bindings.
 */
class JobsMonitorServiceProvider extends ServiceProvider
{
    /**
     * Register the package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/jobs-monitor.php', 'jobs-monitor');

        // bindIf: an admin panel integration may already have bound its own resolver
        $this->app->bindIf(
            ActorResolver::class,
            static fn (Application $app): ActorResolver => new GuardActorResolver(
                $app->make(AuthFactory::class),
                $app->make('config')->get('jobs-monitor.actor_guard')
            )
        );
    }

    /**
     * Bootstrap the package: translations and publishable resources.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'jobs-monitor');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/jobs-monitor.php' => $this->app->configPath('jobs-monitor.php'),
            ], 'jobs-monitor-config');

            $this->publishes([
                __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/jobs-monitor'),
            ], 'jobs-monitor-lang');
        }
    }
}
