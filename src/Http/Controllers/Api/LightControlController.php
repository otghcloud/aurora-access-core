<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OTGH\AccessControl\Core\Jobs\ProcessLightEvent;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\Hardware\Light;

class LightControlController
{
    /**
     * Get light status
     *
     * GET /api/lights/{lightId}
     */
    public function show(Request $request, Light $light): JsonResponse
    {
        // Authorize access to area
        if (! $request->user()->hasAreaPermission($light->area_id)) {
            return response()->json(['error' => 'User does not have permission to access this area'], 403);
        }

        return response()->json([
            'data' => [
                'id' => (string) $light->id,
                'name' => $light->name,
                'identifier' => $light->identifier,
                'area_id' => (string) $light->area_id,
                'area' => $light->area->name,
                'state' => $light->state_display,
                'state_raw' => $light->state,
                'brightness' => $light->brightness,
                'color' => $light->color,
                'features' => [
                    'brightness' => $light->supportsBrightness(),
                    'color' => $light->supportsColor(),
                ],
                'metadata' => $light->metadata ?? [],
                'updated_at' => $light->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Turn light on
     *
     * POST /api/lights/{lightId}/on
     */
    public function on(Request $request, Light $light): JsonResponse
    {
        // Authorize access to area
        if (! $request->user()->hasAreaPermission($light->area_id)) {
            return response()->json(['error' => 'User does not have permission to access this area'], 403);
        }

        $validated = $request->validate([
            'brightness' => 'nullable|integer|min:0|max:100',
            'reason' => 'nullable|string|max:500',
        ]);

        $brightness = $validated['brightness'] ?? null;
        $reason = $validated['reason'] ?? 'API on command';

        // Update light state
        $light->update([
            'state' => true,
            'brightness' => $brightness ?? $light->brightness ?? 100,
        ]);

        // Create event
        Event::create([
            'access_light_id' => $light->id,
            'status' => 'api_light_on_requested',
            'origin_type' => 'api',
            'reason' => $reason,
            'metadata' => [
                'light_id' => $light->id,
                'brightness' => $brightness,
            ],
            'ip_address' => $request->ip(),
        ]);

        // Queue light command
        dispatch(new ProcessLightEvent(
            lightId: $light->id,
            action: 'on',
            brightness: $brightness,
            originType: 'api',
        ));

        return response()->json([
            'message' => 'Light turned on',
            'light' => $light->identifier,
            'state' => 'on',
            'brightness' => $light->brightness,
        ], 202);
    }

    /**
     * Turn light off
     *
     * POST /api/lights/{lightId}/off
     */
    public function off(Request $request, Light $light): JsonResponse
    {
        // Authorize access to area
        if (! $request->user()->hasAreaPermission($light->area_id)) {
            return response()->json(['error' => 'User does not have permission to access this area'], 403);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $reason = $validated['reason'] ?? 'API off command';

        // Update light state
        $light->update(['state' => false]);

        // Create event
        Event::create([
            'access_light_id' => $light->id,
            'status' => 'api_light_off_requested',
            'origin_type' => 'api',
            'reason' => $reason,
            'metadata' => [
                'light_id' => $light->id,
            ],
            'ip_address' => $request->ip(),
        ]);

        // Queue light command
        dispatch(new ProcessLightEvent(
            lightId: $light->id,
            action: 'off',
            originType: 'api',
        ));

        return response()->json([
            'message' => 'Light turned off',
            'light' => $light->identifier,
            'state' => 'off',
        ], 202);
    }

    /**
     * Set light brightness
     *
     * PUT /api/lights/{lightId}/brightness
     */
    public function setBrightness(Request $request, Light $light): JsonResponse
    {
        // Authorize access to area
        if (! $request->user()->hasAreaPermission($light->area_id)) {
            return response()->json(['error' => 'User does not have permission to access this area'], 403);
        }

        if (! $light->supportsBrightness()) {
            return response()->json(['error' => 'This light does not support brightness control'], 400);
        }

        $validated = $request->validate([
            'brightness' => 'required|integer|min:0|max:100',
            'reason' => 'nullable|string|max:500',
        ]);

        $brightness = $validated['brightness'];
        $reason = $validated['reason'] ?? 'API brightness set';

        // Update light brightness
        $light->setBrightness($brightness);

        // Create event
        Event::create([
            'access_light_id' => $light->id,
            'status' => 'api_light_brightness_set',
            'origin_type' => 'api',
            'reason' => $reason,
            'metadata' => [
                'light_id' => $light->id,
                'brightness' => $brightness,
            ],
            'ip_address' => $request->ip(),
        ]);

        // Queue light command
        dispatch(new ProcessLightEvent(
            lightId: $light->id,
            action: 'brightness',
            brightness: $brightness,
            originType: 'api',
        ));

        return response()->json([
            'message' => 'Light brightness updated',
            'light' => $light->identifier,
            'brightness' => $brightness,
        ]);
    }

    /**
     * Set light color
     *
     * PUT /api/lights/{lightId}/color
     */
    public function setColor(Request $request, Light $light): JsonResponse
    {
        // Authorize access to area
        if (! $request->user()->hasAreaPermission($light->area_id)) {
            return response()->json(['error' => 'User does not have permission to access this area'], 403);
        }

        if (! $light->supportsColor()) {
            return response()->json(['error' => 'This light does not support color control'], 400);
        }

        $validated = $request->validate([
            'color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'reason' => 'nullable|string|max:500',
        ]);

        $color = $validated['color'];
        $reason = $validated['reason'] ?? 'API color set';

        // Update light color
        $light->setColor($color);

        // Create event
        Event::create([
            'access_light_id' => $light->id,
            'status' => 'api_light_color_set',
            'origin_type' => 'api',
            'reason' => $reason,
            'metadata' => [
                'light_id' => $light->id,
                'color' => $color,
            ],
            'ip_address' => $request->ip(),
        ]);

        // Queue light command
        dispatch(new ProcessLightEvent(
            lightId: $light->id,
            action: 'color',
            color: $color,
            originType: 'api',
        ));

        return response()->json([
            'message' => 'Light color updated',
            'light' => $light->identifier,
            'color' => $color,
        ]);
    }

    /**
     * Toggle light on/off
     *
     * POST /api/lights/{lightId}/toggle
     */
    public function toggle(Request $request, Light $light): JsonResponse
    {
        // Authorize access to area
        if (! $request->user()->hasAreaPermission($light->area_id)) {
            return response()->json(['error' => 'User does not have permission to access this area'], 403);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $reason = $validated['reason'] ?? 'API toggle command';
        $newState = ! $light->state;

        // Update light state
        $light->update(['state' => $newState]);

        // Create event
        $status = $newState ? 'api_light_on_requested' : 'api_light_off_requested';
        Event::create([
            'access_light_id' => $light->id,
            'status' => $status,
            'origin_type' => 'api',
            'reason' => $reason,
            'metadata' => [
                'light_id' => $light->id,
                'toggled' => true,
            ],
            'ip_address' => $request->ip(),
        ]);

        // Queue light command
        $action = $newState ? 'on' : 'off';
        dispatch(new ProcessLightEvent(
            lightId: $light->id,
            action: $action,
            originType: 'api',
        ));

        return response()->json([
            'message' => 'Light toggled',
            'light' => $light->identifier,
            'state' => $light->state_display,
        ], 202);
    }

    /**
     * Private helper to authorize area access
     *
     * @param  mixed  $user
     */
    private function authorizeAreaAccess($user, int $areaId): bool
    {
        return $user->hasAreaPermission($areaId);
    }
}
