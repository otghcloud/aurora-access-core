<?php

namespace OTGH\AccessControl\Core\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use OTGH\AccessControl\Core\Console\Commands\AccessControlHealthCheck;
use OTGH\AccessControl\Core\Console\Commands\CreateInitialAdminUser;
use OTGH\AccessControl\Core\Console\Commands\MonitorReaderPushRequests;
use OTGH\AccessControl\Core\Console\Commands\RebuildAccessControlSupervisorConfig;
use OTGH\AccessControl\Core\Console\Commands\ReconcileReaderLockState;
use OTGH\AccessControl\Core\Console\Commands\SyncReaderMqttState;
use OTGH\AccessControl\Core\Console\Commands\TestReadEvent;
use OTGH\AccessControl\Core\Livewire\Admin\AccessReadersTable;
use OTGH\AccessControl\Core\Livewire\Admin\DashboardLockCards;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Models\Hardware\Source;
use OTGH\AccessControl\Core\Observers\AccessReaderObserver;
use OTGH\AccessControl\Core\Observers\AccessSourceObserver;
use OTGH\AccessControl\Core\Services\AccessControl\AccessControlCapabilityRegistry;
use OTGH\AccessControl\Core\Services\AccessControl\AccessControlConfigurationRegistry;
use OTGH\AccessControl\Core\Services\AccessControl\AccessControlSettingsRepository;
use OTGH\AccessControl\Core\Services\AccessControl\DiagnosticsNavigationRegistry;
use OTGH\AccessControl\Core\Services\AccessControl\HealthCheckRegistry;
use OTGH\AccessControl\Core\Services\AccessControl\HttpSourceConnectionTester;
use OTGH\AccessControl\Core\Services\AccessControl\SourceConnectionTesterRegistry;
use OTGH\AccessControl\Core\Services\Supervisor\SupervisorProgramRegistry;

class AccessCoreServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/mqtt-client.php', 'mqtt-client');

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
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->registerLivewireComponents();

        if (! $this->app->routesAreCached()) {
            Route::middleware('web')->group(__DIR__.'/../../routes/web.php');
            Route::prefix('api')->middleware('api')->group(__DIR__.'/../../routes/api.php');
        }

        $this->app['view']->addLocation(__DIR__.'/../../resources/views');

        if ($this->app->runningInConsole()) {
            require __DIR__.'/../../routes/console.php';

            $this->commands([
                AccessControlHealthCheck::class,
                CreateInitialAdminUser::class,
                MonitorReaderPushRequests::class,
                RebuildAccessControlSupervisorConfig::class,
                ReconcileReaderLockState::class,
                SyncReaderMqttState::class,
                TestReadEvent::class,
            ]);

            $this->publishes([
                __DIR__.'/../../config/mqtt-client.php' => config_path('mqtt-client.php'),
            ], 'aurora-access-core-config');

            $this->publishes([
                __DIR__.'/../../database/migrations' => database_path('migrations'),
            ], 'aurora-access-core-migrations');

            $this->publishes([
                __DIR__.'/../../resources/views' => resource_path('views'),
            ], 'aurora-access-core-views');

            $this->publishes([
                __DIR__.'/../../public/build' => public_path('vendor/aurora-access-core/build'),
            ], 'aurora-access-core-assets');
        }

        Paginator::useBootstrapFive();

        Route::resourceParameters([
            'area-permissions' => 'areaPermission',
        ]);

        Reader::observe(AccessReaderObserver::class);
        Source::observe(AccessSourceObserver::class);
    }

    private function registerLivewireComponents(): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        Livewire::component('admin.dashboard-lock-cards', DashboardLockCards::class);
        Livewire::component('admin.access-readers-table', AccessReadersTable::class);
    }
}
