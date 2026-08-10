<?php

namespace App\Http\Controllers\Admin\Hardware;

use App\Http\Controllers\Controller;
use App\Models\Hardware\Source;
use App\Services\AccessControl\AccessControlCapabilityRegistry;
use App\Services\AccessControl\SourceConnectionTesterRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AccessSourceController extends Controller
{
    public function index(): View
    {
        return view('admin.hardware.sources.index', [
            'accessSources' => Source::query()->latest('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.hardware.sources.create', [
            'sourceTypeOptions' => app(AccessControlCapabilityRegistry::class)->sourceTypeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAndNormalize($request);

        Source::create($validated);

        return redirect()->route('admin.access-sources.index')->with('status', 'Access source created successfully.');
    }

    public function edit(Source $source): View
    {
        return view('admin.hardware.sources.edit', [
            'accessSource' => $source,
            'sourceTypeOptions' => app(AccessControlCapabilityRegistry::class)->sourceTypeOptions(),
        ]);
    }

    public function update(Request $request, Source $source): RedirectResponse
    {
        $validated = $this->validateAndNormalize($request, $source);

        $source->update($validated);

        return redirect()->route('admin.access-sources.index')->with('status', 'Access source updated successfully.');
    }

    public function destroy(Source $source): RedirectResponse
    {
        $source->delete();

        return redirect()->route('admin.access-sources.index')->with('status', 'Access source deleted successfully.');
    }

    public function testConnection(
        Source $source,
        SourceConnectionTesterRegistry $testerRegistry,
        AccessControlCapabilityRegistry $capabilities,
    ): RedirectResponse {
        $type = $capabilities->normalizeSourceType((string) $source->type) ?? strtolower(trim((string) $source->type));

        try {
            $tester = $testerRegistry->resolve($type);

            if ($tester !== null) {
                return redirect()->route('admin.access-sources.index')->with(
                    'success',
                    $tester->test($source)
                );
            }

            return redirect()->route('admin.access-sources.index')->with(
                'error',
                sprintf('Test is not implemented for source type [%s] yet.', strtoupper($type))
            );
        } catch (Throwable $e) {
            return redirect()->route('admin.access-sources.index')->withErrors([
                'error' => sprintf('Source test failed for [%s]: %s', $source->identifier, $e->getMessage()),
            ]);
        }
    }

    /**
     * @return array{name:string,identifier:string,type:string,endpoint:?string,enabled:bool,config:array<string,mixed>,metadata:array<string,mixed>}
     */
    private function validateAndNormalize(Request $request, ?Source $source = null): array
    {
        $capabilities = app(AccessControlCapabilityRegistry::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'identifier' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sources', 'identifier')->ignore($source?->id),
            ],
            'type' => ['required', 'string', Rule::in($capabilities->sourceTypeValidationValues())],
            'endpoint' => ['nullable', 'string', 'max:500'],
            'enabled' => ['required', 'boolean'],
            'metadata_json' => ['nullable', 'string'],
        ]);

        $type = $capabilities->normalizeSourceType($validated['type']) ?? strtolower(trim($validated['type']));
        $endpoint = $this->nullableString($validated['endpoint'] ?? null);
        $config = [];

        if ($type === 'mqtt') {
            $mqtt = $request->validate([
                'mqtt_host' => ['required', 'string', 'max:255'],
                'mqtt_port' => ['required', 'integer', 'min:1', 'max:65535'],
                'mqtt_client_id' => ['nullable', 'string', 'max:255'],
                'mqtt_username' => ['nullable', 'string', 'max:255'],
                'mqtt_password' => ['nullable', 'string', 'max:255'],
                'mqtt_topic_prefix' => ['nullable', 'string', 'max:255'],
                'mqtt_use_tls' => ['nullable', 'boolean'],
                'mqtt_qos' => ['nullable', 'integer', 'min:0', 'max:2'],
            ]);

            $endpoint ??= sprintf('mqtt://%s:%d', $mqtt['mqtt_host'], (int) $mqtt['mqtt_port']);
            $config['mqtt'] = [
                'host' => trim($mqtt['mqtt_host']),
                'port' => (int) $mqtt['mqtt_port'],
                'client_id' => $this->nullableString($mqtt['mqtt_client_id'] ?? null),
                'username' => $this->nullableString($mqtt['mqtt_username'] ?? null),
                'password' => $this->nullableString($mqtt['mqtt_password'] ?? null),
                'topic_prefix' => $this->nullableString($mqtt['mqtt_topic_prefix'] ?? null),
                'use_tls' => (bool) ($mqtt['mqtt_use_tls'] ?? false),
                'qos' => (int) ($mqtt['mqtt_qos'] ?? 0),
            ];
        }

        if ($type === 'http') {
            $http = $request->validate([
                'http_base_url' => ['required', 'string', 'max:500'],
                'http_method' => ['nullable', 'string', 'in:GET,POST,PUT,PATCH,DELETE'],
                'http_timeout_ms' => ['nullable', 'integer', 'min:100', 'max:120000'],
                'http_headers_json' => ['nullable', 'string'],
            ]);

            $headers = $this->decodeJsonField($http['http_headers_json'] ?? null, 'http_headers_json');
            $endpoint ??= trim($http['http_base_url']);
            $config['http'] = [
                'base_url' => trim($http['http_base_url']),
                'method' => strtoupper((string) ($http['http_method'] ?? 'GET')),
                'timeout_ms' => (int) ($http['http_timeout_ms'] ?? 5000),
                'headers' => $headers,
            ];
        }

        if ($type === 'opcua') {
            $opc = $request->validate([
                'opc_endpoint' => ['required', 'string', 'max:500'],
                'opc_namespace' => ['nullable', 'integer', 'min:0'],
                'opc_publishing_interval_ms' => ['nullable', 'integer', 'min:50'],
                'opc_sampling_interval_ms' => ['nullable', 'integer', 'min:50'],
                'opc_nodes_text' => ['required', 'string'],
            ]);

            $nodes = array_values(array_filter(array_map(
                fn (string $line): string => trim($line),
                preg_split('/\r\n|\r|\n/', (string) $opc['opc_nodes_text']) ?: [],
            ), fn (string $line): bool => $line !== ''));

            if ($nodes === []) {
                throw ValidationException::withMessages([
                    'opc_nodes_text' => 'Provide at least one OPC node id.',
                ]);
            }

            $endpoint ??= trim($opc['opc_endpoint']);
            $config['opc_ua'] = [
                'endpoint' => trim($opc['opc_endpoint']),
                'namespace' => (int) ($opc['opc_namespace'] ?? 2),
                'publishing_interval_ms' => (int) ($opc['opc_publishing_interval_ms'] ?? 1000),
                'sampling_interval_ms' => (int) ($opc['opc_sampling_interval_ms'] ?? 1000),
                'nodes' => $nodes,
            ];
            $config['namespace'] = $config['opc_ua']['namespace'];
            $config['publishing_interval_ms'] = $config['opc_ua']['publishing_interval_ms'];
            $config['sampling_interval_ms'] = $config['opc_ua']['sampling_interval_ms'];
            $config['nodes'] = $nodes;
        }

        if ($type === 'edgelink') {
            $edgelink = $request->validate([
                'edgelink_base_url' => ['required', 'string', 'max:500'],
                'edgelink_password' => ['required', 'string', 'max:255'],
                'edgelink_referer' => ['nullable', 'string', 'max:500'],
                'edgelink_verify_tls' => ['nullable', 'boolean'],
                'edgelink_timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:120'],
            ]);

            $baseUrl = trim($edgelink['edgelink_base_url']);
            $endpoint ??= $baseUrl;
            $config = [
                'base_url' => $baseUrl,
                'password' => trim($edgelink['edgelink_password']),
                'referer' => $this->nullableString($edgelink['edgelink_referer'] ?? null) ?? $baseUrl,
                'verify_tls' => (bool) ($edgelink['edgelink_verify_tls'] ?? false),
                'timeout_seconds' => (int) ($edgelink['edgelink_timeout_seconds'] ?? 10),
            ];
        }

        if ($type === 'script') {
            $script = $request->validate([
                'script_command' => ['required', 'string', 'max:500'],
                'script_working_directory' => ['nullable', 'string', 'max:500'],
                'script_timeout_ms' => ['nullable', 'integer', 'min:100', 'max:300000'],
                'script_args_text' => ['nullable', 'string'],
            ]);

            $args = array_values(array_filter(array_map(
                fn (string $line): string => trim($line),
                preg_split('/\r\n|\r|\n/', (string) ($script['script_args_text'] ?? '')) ?: [],
            ), fn (string $line): bool => $line !== ''));

            $endpoint ??= 'script://local';
            $config['script'] = [
                'command' => trim($script['script_command']),
                'working_directory' => $this->nullableString($script['script_working_directory'] ?? null),
                'timeout_ms' => (int) ($script['script_timeout_ms'] ?? 5000),
                'args' => $args,
            ];
        }

        if ($type === 'modbus') {
            $modbus = $request->validate([
                'modbus_host' => ['required', 'string', 'max:255'],
                'modbus_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'modbus_unit_id' => ['nullable', 'integer', 'min:1', 'max:255'],
                'modbus_digital_input_count' => ['nullable', 'integer', 'min:1', 'max:2000'],
                'modbus_relay_channel_count' => ['nullable', 'integer', 'min:1', 'max:2000'],
                'modbus_poll_interval_ms' => ['nullable', 'integer', 'min:50', 'max:60000'],
                'modbus_input_start_address' => ['nullable', 'integer', 'min:0', 'max:65535'],
                'modbus_coil_start_address' => ['nullable', 'integer', 'min:0', 'max:65535'],
                'modbus_timeout_ms' => ['nullable', 'integer', 'min:100', 'max:120000'],
            ]);

            $host = trim($modbus['modbus_host']);
            $port = (int) ($modbus['modbus_port'] ?? 502);

            $endpoint ??= sprintf('modbus://%s:%d', $host, $port);
            $config['modbus'] = [
                'host' => $host,
                'port' => $port,
                'unit_id' => (int) ($modbus['modbus_unit_id'] ?? 1),
                'digital_input_count' => (int) ($modbus['modbus_digital_input_count'] ?? 8),
                'relay_channel_count' => (int) ($modbus['modbus_relay_channel_count'] ?? 8),
                'poll_interval_ms' => (int) ($modbus['modbus_poll_interval_ms'] ?? 500),
                'input_start_address' => (int) ($modbus['modbus_input_start_address'] ?? 0),
                'coil_start_address' => (int) ($modbus['modbus_coil_start_address'] ?? 0),
                'timeout_ms' => (int) ($modbus['modbus_timeout_ms'] ?? 3000),
            ];
        }

        return [
            'name' => $validated['name'],
            'identifier' => $validated['identifier'],
            'type' => $type,
            'endpoint' => $endpoint,
            'enabled' => (bool) $validated['enabled'],
            'config' => $config,
            'metadata' => $this->decodeJsonField($validated['metadata_json'] ?? null, 'metadata_json'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJsonField(?string $json, string $field): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => 'Must be valid JSON object syntax.',
            ]);
        }

        return $decoded;
    }

    private function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
