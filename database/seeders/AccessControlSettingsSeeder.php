<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccessControlSettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $defaults = [
            'mqtt_publisher_connection' => 'publisher',
            'mqtt_base_topic' => 'access_control',
            'mqtt_command_suffix' => 'cmd',
            'mqtt_state_suffix' => 'state',
            'mqtt_events_suffix' => 'events',
            'push_dedupe_seconds' => 2.5,
            'opc_monitor_max_runtime_seconds' => 900,
            'supervisor.auto_rebuild' => true,
            'supervisor.auto_apply' => false,
            'supervisor.apply_after_rebuild' => true,
            'supervisor.command_timeout_seconds' => 30,
            'supervisor.fail_fast' => true,
            'supervisor.apply_commands' => [
                'sudo -n supervisorctl reread',
                'sudo -n supervisorctl update',
            ],
            'access_control.default_source_type' => 'mqtt',
        ];

        $timestamp = now();
        $rows = [];

        foreach ($defaults as $key => $value) {
            $rows[] = [
                'key' => $key,
                'value' => json_encode($value, JSON_UNESCAPED_SLASHES),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        DB::table('settings')->upsert(
            $rows,
            ['key'],
            ['value', 'updated_at']
        );
    }
}
