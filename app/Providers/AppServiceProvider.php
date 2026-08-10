<?php

namespace App\Providers;

use App\Models\Hardware\Reader;
use App\Models\Hardware\Source;
use App\Observers\AccessReaderObserver;
use App\Observers\AccessSourceObserver;
use App\Services\AccessControl\AccessControlCapabilityRegistry;
use App\Services\AccessControl\AccessControlConfigurationRegistry;
use App\Services\AccessControl\AccessControlSettingsRepository;
use App\Services\AccessControl\DiagnosticsNavigationRegistry;
use App\Services\AccessControl\HealthCheckRegistry;
use App\Services\AccessControl\HttpSourceConnectionTester;
use App\Services\AccessControl\SourceConnectionTesterRegistry;
use App\Services\Supervisor\SupervisorProgramRegistry;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AccessControlCapabilityRegistry::class, function (): AccessControlCapabilityRegistry {
            $registry = new AccessControlCapabilityRegistry;

            $registry->registerBindingAdapterType('mqtt', 'MQTT');
            $registry->registerBindingAdapterType('http', 'HTTP');
            $registry->registerBindingAdapterType('script', 'SCRIPT');

            $registry->registerSourceType('mqtt', 'MQTT');
            $registry->registerSourceType('http', 'HTTP');
            $registry->registerSourceType('script', 'SCRIPT');

            return $registry;
        });

        $this->app->singleton(SourceConnectionTesterRegistry::class);
        $this->app->singleton(DiagnosticsNavigationRegistry::class);
        $this->app->singleton(HttpSourceConnectionTester::class);
        $this->app->singleton(AccessControlSettingsRepository::class);
        $this->app->singleton(SupervisorProgramRegistry::class);
        $this->app->singleton(HealthCheckRegistry::class);
        $this->app->singleton(AccessControlConfigurationRegistry::class, function (): AccessControlConfigurationRegistry {
            $registry = new AccessControlConfigurationRegistry;

            $registry->registerField(
                key: 'access_control.default_source_type',
                label: 'Default Source Type',
                type: 'string',
                description: 'Default source type used when creating sources.',
                section: 'core',
                sectionLabel: 'Core',
                package: null,
                default: 'mqtt',
            );

            $registry->registerField(
                key: 'mqtt_base_topic',
                label: 'MQTT Base Topic',
                type: 'string',
                description: 'Base MQTT topic prefix used for access-control publishes/subscriptions.',
                section: 'mqtt',
                sectionLabel: 'MQTT',
                package: null,
                default: 'access_control',
            );

            $registry->registerField(
                key: 'mqtt_command_suffix',
                label: 'MQTT Command Suffix',
                type: 'string',
                description: 'MQTT suffix for command topics.',
                section: 'mqtt',
                sectionLabel: 'MQTT',
                package: null,
                default: 'cmd',
            );

            $registry->registerField(
                key: 'mqtt_state_suffix',
                label: 'MQTT State Suffix',
                type: 'string',
                description: 'MQTT suffix for state topics.',
                section: 'mqtt',
                sectionLabel: 'MQTT',
                package: null,
                default: 'state',
            );

            $registry->registerField(
                key: 'mqtt_events_suffix',
                label: 'MQTT Events Suffix',
                type: 'string',
                description: 'MQTT suffix for events topics.',
                section: 'mqtt',
                sectionLabel: 'MQTT',
                package: null,
                default: 'events',
            );

            $registry->registerField(
                key: 'push_dedupe_seconds',
                label: 'Push Dedupe Window (Seconds)',
                type: 'float',
                description: 'Duplicate suppression window for push events.',
                section: 'mqtt',
                sectionLabel: 'MQTT',
                package: null,
                default: 2.5,
            );

            $registry->registerField(
                key: 'supervisor.auto_rebuild',
                label: 'Supervisor Auto Rebuild',
                type: 'boolean',
                description: 'Automatically rebuild Supervisor config after reader/source changes.',
                section: 'supervisor',
                sectionLabel: 'Supervisor',
                package: null,
                default: true,
            );

            $registry->registerField(
                key: 'supervisor.auto_apply',
                label: 'Supervisor Auto Apply',
                type: 'boolean',
                description: 'Automatically apply Supervisor changes after rebuild.',
                section: 'supervisor',
                sectionLabel: 'Supervisor',
                package: null,
                default: false,
            );

            $registry->registerField(
                key: 'supervisor.apply_after_rebuild',
                label: 'Supervisor Apply After Rebuild',
                type: 'boolean',
                description: 'Default behavior for rebuild command apply flag.',
                section: 'supervisor',
                sectionLabel: 'Supervisor',
                package: null,
                default: true,
            );

            $registry->registerField(
                key: 'supervisor.command_timeout_seconds',
                label: 'Supervisor Command Timeout (Seconds)',
                type: 'float',
                description: 'Timeout for supervisorctl apply commands.',
                section: 'supervisor',
                sectionLabel: 'Supervisor',
                package: null,
                default: 30,
            );

            $registry->registerField(
                key: 'supervisor.fail_fast',
                label: 'Supervisor Fail Fast',
                type: 'boolean',
                description: 'Stop apply command sequence on first failure.',
                section: 'supervisor',
                sectionLabel: 'Supervisor',
                package: null,
                default: true,
            );

            return $registry;
        });

        $this->app->afterResolving(SourceConnectionTesterRegistry::class, function (SourceConnectionTesterRegistry $registry): void {
            $registry->register($this->app->make(HttpSourceConnectionTester::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Route::resourceParameters([
            'area-permissions' => 'areaPermission',
        ]);

        Reader::observe(AccessReaderObserver::class);
        Source::observe(AccessSourceObserver::class);
    }
}
