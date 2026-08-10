@php
    $config = is_array($accessSource?->config ?? null) ? $accessSource->config : [];

    $opcNodes = data_get($config, 'opc_ua.nodes', data_get($config, 'nodes', []));
    if (! is_array($opcNodes)) {
        $opcNodes = [];
    }

    $scriptArgs = data_get($config, 'script.args', []);
    if (! is_array($scriptArgs)) {
        $scriptArgs = [];
    }

    $metadataJson = old('metadata_json');
    if ($metadataJson === null) {
        $metadataJson = json_encode($accessSource?->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    $selectedType = strtolower((string) old('type', $accessSource?->type ?? ''));
    if (in_array($selectedType, ['opc', 'opc_ua'], true)) {
        $selectedType = 'opcua';
    }

    $edgelinkBaseUrl = old('edgelink_base_url', data_get($config, 'base_url', data_get($config, 'edgelink.endpoint', $accessSource?->endpoint)));
    $edgelinkPassword = old('edgelink_password', data_get($config, 'password', data_get($config, 'edgelink.password')));
    $edgelinkReferer = old('edgelink_referer', data_get($config, 'referer', data_get($config, 'edgelink.referer', $edgelinkBaseUrl)));
    $edgelinkVerifyTls = (string) old('edgelink_verify_tls', data_get($config, 'verify_tls', data_get($config, 'edgelink.verify_tls', false)) ? '1' : '0');
    $edgelinkTimeoutSeconds = old('edgelink_timeout_seconds', data_get($config, 'timeout_seconds', data_get($config, 'edgelink.timeout_seconds', 10)));

    $modbusHost = old('modbus_host', data_get($config, 'modbus.host'));
    $modbusPort = old('modbus_port', data_get($config, 'modbus.port', 502));
    $modbusUnitId = old('modbus_unit_id', data_get($config, 'modbus.unit_id', 1));
    $modbusInputCount = old('modbus_digital_input_count', data_get($config, 'modbus.digital_input_count', 8));
    $modbusRelayCount = old('modbus_relay_channel_count', data_get($config, 'modbus.relay_channel_count', 8));
    $modbusPollIntervalMs = old('modbus_poll_interval_ms', data_get($config, 'modbus.poll_interval_ms', 500));
    $modbusInputStartAddress = old('modbus_input_start_address', data_get($config, 'modbus.input_start_address', 0));
    $modbusCoilStartAddress = old('modbus_coil_start_address', data_get($config, 'modbus.coil_start_address', 0));
    $modbusTimeoutMs = old('modbus_timeout_ms', data_get($config, 'modbus.timeout_ms', 3000));
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Name</label>
        <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $accessSource?->name ?? '') }}" required maxlength="255">
    </div>

    <div class="col-md-6">
        <label for="identifier" class="form-label">Identifier</label>
        <input type="text" class="form-control" id="identifier" name="identifier" value="{{ old('identifier', $accessSource?->identifier ?? '') }}" required maxlength="255">
    </div>

    <div class="col-md-4">
        <label for="type" class="form-label">Type</label>
        <select class="form-select" id="type" name="type" required>
            @foreach (($sourceTypeOptions ?? []) as $type)
                <option value="{{ $type['value'] }}" @selected($selectedType === $type['value'])>{{ $type['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-8">
        <label for="endpoint" class="form-label">Endpoint</label>
        <input type="text" class="form-control" id="endpoint" name="endpoint" value="{{ old('endpoint', $accessSource?->endpoint ?? '') }}" maxlength="500">
        <div class="form-text">Optional override. Leave blank to auto-generate from the selected type fields.</div>
    </div>

    <div class="col-md-4">
        <label for="enabled" class="form-label">Enabled</label>
        <select class="form-select" id="enabled" name="enabled" required>
            <option value="1" @selected((string) old('enabled', ($accessSource?->enabled ?? true) ? '1' : '0') === '1')>Yes</option>
            <option value="0" @selected((string) old('enabled', ($accessSource?->enabled ?? true) ? '1' : '0') === '0')>No</option>
        </select>
    </div>

    <div class="col-12" data-type-section="mqtt" style="display: none;">
        <div class="border rounded p-3">
            <h2 class="h6 mb-3">MQTT Settings</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="mqtt_host" class="form-label">Broker Host</label>
                    <input type="text" class="form-control" id="mqtt_host" name="mqtt_host" value="{{ old('mqtt_host', data_get($config, 'mqtt.host')) }}" maxlength="255">
                </div>
                <div class="col-md-2">
                    <label for="mqtt_port" class="form-label">Port</label>
                    <input type="number" class="form-control" id="mqtt_port" name="mqtt_port" value="{{ old('mqtt_port', data_get($config, 'mqtt.port', 1883)) }}" min="1" max="65535">
                </div>
                <div class="col-md-3">
                    <label for="mqtt_client_id" class="form-label">Client ID</label>
                    <input type="text" class="form-control" id="mqtt_client_id" name="mqtt_client_id" value="{{ old('mqtt_client_id', data_get($config, 'mqtt.client_id')) }}" maxlength="255">
                </div>
                <div class="col-md-3">
                    <label for="mqtt_topic_prefix" class="form-label">Topic Prefix</label>
                    <input type="text" class="form-control" id="mqtt_topic_prefix" name="mqtt_topic_prefix" value="{{ old('mqtt_topic_prefix', data_get($config, 'mqtt.topic_prefix')) }}" maxlength="255">
                </div>
                <div class="col-md-3">
                    <label for="mqtt_username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="mqtt_username" name="mqtt_username" value="{{ old('mqtt_username', data_get($config, 'mqtt.username')) }}" maxlength="255">
                </div>
                <div class="col-md-3">
                    <label for="mqtt_password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="mqtt_password" name="mqtt_password" value="{{ old('mqtt_password', data_get($config, 'mqtt.password')) }}" maxlength="255">
                </div>
                <div class="col-md-2">
                    <label for="mqtt_qos" class="form-label">QoS</label>
                    <input type="number" class="form-control" id="mqtt_qos" name="mqtt_qos" value="{{ old('mqtt_qos', data_get($config, 'mqtt.qos', 0)) }}" min="0" max="2">
                </div>
                <div class="col-md-2">
                    <label for="mqtt_use_tls" class="form-label">TLS</label>
                    <select class="form-select" id="mqtt_use_tls" name="mqtt_use_tls">
                        <option value="0" @selected((string) old('mqtt_use_tls', data_get($config, 'mqtt.use_tls', false) ? '1' : '0') === '0')>No</option>
                        <option value="1" @selected((string) old('mqtt_use_tls', data_get($config, 'mqtt.use_tls', false) ? '1' : '0') === '1')>Yes</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12" data-type-section="http" style="display: none;">
        <div class="border rounded p-3">
            <h2 class="h6 mb-3">HTTP Settings</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="http_base_url" class="form-label">Base URL</label>
                    <input type="text" class="form-control" id="http_base_url" name="http_base_url" value="{{ old('http_base_url', data_get($config, 'http.base_url', $accessSource?->endpoint)) }}" maxlength="500">
                </div>
                <div class="col-md-2">
                    <label for="http_method" class="form-label">Method</label>
                    <select class="form-select" id="http_method" name="http_method">
                        @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method)
                            <option value="{{ $method }}" @selected(old('http_method', data_get($config, 'http.method', 'GET')) === $method)>{{ $method }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="http_timeout_ms" class="form-label">Timeout (ms)</label>
                    <input type="number" class="form-control" id="http_timeout_ms" name="http_timeout_ms" value="{{ old('http_timeout_ms', data_get($config, 'http.timeout_ms', 5000)) }}" min="100" max="120000">
                </div>
                <div class="col-12">
                    <label for="http_headers_json" class="form-label">Headers (JSON object)</label>
                    <textarea class="form-control font-monospace" id="http_headers_json" name="http_headers_json" rows="4">{{ old('http_headers_json', json_encode(data_get($config, 'http.headers', []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12" data-type-section="opc" style="display: none;">
        <div class="border rounded p-3">
            <h2 class="h6 mb-3">OPC UA Settings</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="opc_endpoint" class="form-label">OPC Endpoint</label>
                    <input type="text" class="form-control" id="opc_endpoint" name="opc_endpoint" value="{{ old('opc_endpoint', data_get($config, 'opc_ua.endpoint', $accessSource?->endpoint)) }}" maxlength="500" placeholder="opc.tcp://host:4840">
                </div>
                <div class="col-md-2">
                    <label for="opc_namespace" class="form-label">Namespace</label>
                    <input type="number" class="form-control" id="opc_namespace" name="opc_namespace" value="{{ old('opc_namespace', data_get($config, 'opc_ua.namespace', data_get($config, 'namespace', 2))) }}" min="0">
                </div>
                <div class="col-md-2">
                    <label for="opc_publishing_interval_ms" class="form-label">Pub Interval (ms)</label>
                    <input type="number" class="form-control" id="opc_publishing_interval_ms" name="opc_publishing_interval_ms" value="{{ old('opc_publishing_interval_ms', data_get($config, 'opc_ua.publishing_interval_ms', data_get($config, 'publishing_interval_ms', 1000))) }}" min="50">
                </div>
                <div class="col-md-2">
                    <label for="opc_sampling_interval_ms" class="form-label">Sample Interval (ms)</label>
                    <input type="number" class="form-control" id="opc_sampling_interval_ms" name="opc_sampling_interval_ms" value="{{ old('opc_sampling_interval_ms', data_get($config, 'opc_ua.sampling_interval_ms', data_get($config, 'sampling_interval_ms', 1000))) }}" min="50">
                </div>
                <div class="col-12">
                    <label for="opc_nodes_text" class="form-label">Nodes (one per line)</label>
                    <textarea class="form-control font-monospace" id="opc_nodes_text" name="opc_nodes_text" rows="6">{{ old('opc_nodes_text', implode("\n", $opcNodes)) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12" data-type-section="edgelink" style="display: none;">
        <div class="border rounded p-3">
            <h2 class="h6 mb-3">Edgelink Settings</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="edgelink_base_url" class="form-label">Base URL</label>
                    <input type="text" class="form-control" id="edgelink_base_url" name="edgelink_base_url" value="{{ $edgelinkBaseUrl }}" maxlength="500" placeholder="https://10.5.1.60">
                </div>
                <div class="col-md-3">
                    <label for="edgelink_password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="edgelink_password" name="edgelink_password" value="{{ $edgelinkPassword }}" maxlength="255">
                </div>
                <div class="col-md-3">
                    <label for="edgelink_referer" class="form-label">Referer</label>
                    <input type="text" class="form-control" id="edgelink_referer" name="edgelink_referer" value="{{ $edgelinkReferer }}" maxlength="500" placeholder="https://10.5.1.60">
                </div>
                <div class="col-md-2">
                    <label for="edgelink_verify_tls" class="form-label">Verify TLS</label>
                    <select class="form-select" id="edgelink_verify_tls" name="edgelink_verify_tls">
                        <option value="0" @selected($edgelinkVerifyTls === '0')>No</option>
                        <option value="1" @selected($edgelinkVerifyTls === '1')>Yes</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="edgelink_timeout_seconds" class="form-label">Timeout (s)</label>
                    <input type="number" class="form-control" id="edgelink_timeout_seconds" name="edgelink_timeout_seconds" value="{{ $edgelinkTimeoutSeconds }}" min="1" max="120">
                </div>
            </div>
        </div>
    </div>

    <div class="col-12" data-type-section="script" style="display: none;">
        <div class="border rounded p-3">
            <h2 class="h6 mb-3">Script Settings</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="script_command" class="form-label">Command</label>
                    <input type="text" class="form-control" id="script_command" name="script_command" value="{{ old('script_command', data_get($config, 'script.command')) }}" maxlength="500">
                </div>
                <div class="col-md-4">
                    <label for="script_working_directory" class="form-label">Working Directory</label>
                    <input type="text" class="form-control" id="script_working_directory" name="script_working_directory" value="{{ old('script_working_directory', data_get($config, 'script.working_directory')) }}" maxlength="500">
                </div>
                <div class="col-md-2">
                    <label for="script_timeout_ms" class="form-label">Timeout (ms)</label>
                    <input type="number" class="form-control" id="script_timeout_ms" name="script_timeout_ms" value="{{ old('script_timeout_ms', data_get($config, 'script.timeout_ms', 5000)) }}" min="100" max="300000">
                </div>
                <div class="col-12">
                    <label for="script_args_text" class="form-label">Arguments (one per line)</label>
                    <textarea class="form-control font-monospace" id="script_args_text" name="script_args_text" rows="5">{{ old('script_args_text', implode("\n", $scriptArgs)) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12" data-type-section="modbus" style="display: none;">
        <div class="border rounded p-3">
            <h2 class="h6 mb-3">Modbus TCP Settings</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="modbus_host" class="form-label">Host</label>
                    <input type="text" class="form-control" id="modbus_host" name="modbus_host" value="{{ $modbusHost }}" maxlength="255" placeholder="192.168.0.100">
                </div>
                <div class="col-md-2">
                    <label for="modbus_port" class="form-label">Port</label>
                    <input type="number" class="form-control" id="modbus_port" name="modbus_port" value="{{ $modbusPort }}" min="1" max="65535">
                </div>
                <div class="col-md-2">
                    <label for="modbus_unit_id" class="form-label">Unit ID</label>
                    <input type="number" class="form-control" id="modbus_unit_id" name="modbus_unit_id" value="{{ $modbusUnitId }}" min="1" max="255">
                </div>
                <div class="col-md-2">
                    <label for="modbus_timeout_ms" class="form-label">Timeout (ms)</label>
                    <input type="number" class="form-control" id="modbus_timeout_ms" name="modbus_timeout_ms" value="{{ $modbusTimeoutMs }}" min="100" max="120000">
                </div>
                <div class="col-md-2">
                    <label for="modbus_poll_interval_ms" class="form-label">Poll (ms)</label>
                    <input type="number" class="form-control" id="modbus_poll_interval_ms" name="modbus_poll_interval_ms" value="{{ $modbusPollIntervalMs }}" min="50" max="60000">
                </div>
                <div class="col-md-3">
                    <label for="modbus_digital_input_count" class="form-label">Digital Inputs</label>
                    <input type="number" class="form-control" id="modbus_digital_input_count" name="modbus_digital_input_count" value="{{ $modbusInputCount }}" min="1" max="2000">
                </div>
                <div class="col-md-3">
                    <label for="modbus_relay_channel_count" class="form-label">Relay Channels</label>
                    <input type="number" class="form-control" id="modbus_relay_channel_count" name="modbus_relay_channel_count" value="{{ $modbusRelayCount }}" min="1" max="2000">
                </div>
                <div class="col-md-3">
                    <label for="modbus_input_start_address" class="form-label">Input Start Address</label>
                    <input type="number" class="form-control" id="modbus_input_start_address" name="modbus_input_start_address" value="{{ $modbusInputStartAddress }}" min="0" max="65535">
                </div>
                <div class="col-md-3">
                    <label for="modbus_coil_start_address" class="form-label">Coil Start Address</label>
                    <input type="number" class="form-control" id="modbus_coil_start_address" name="modbus_coil_start_address" value="{{ $modbusCoilStartAddress }}" min="0" max="65535">
                </div>
                <div class="col-12">
                    <div class="form-text">Channels are 1-based in bindings. Effective wire address is start_address + channel - 1.</div>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="metadata_json" name="metadata_json" value="{{ $metadataJson }}">
</div>

<script>
    (() => {
        const typeEl = document.getElementById('type');
        if (!typeEl) {
            return;
        }

        const sections = Array.from(document.querySelectorAll('[data-type-section]'));

        const normalizeType = (value) => {
            if (value === 'opcua' || value === 'opc_ua') {
                return 'opc';
            }

            return value;
        };

        const syncSections = () => {
            const activeType = normalizeType(typeEl.value);
            sections.forEach((section) => {
                const sectionType = section.getAttribute('data-type-section');
                const isActive = sectionType === activeType;
                section.style.display = isActive ? '' : 'none';

                section.querySelectorAll('input,select,textarea').forEach((field) => {
                    field.disabled = !isActive;
                });
            });
        };

        typeEl.addEventListener('change', syncSections);
        syncSections();
    })();
</script>
