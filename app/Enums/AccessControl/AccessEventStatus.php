<?php

namespace App\Enums\AccessControl;

enum AccessEventStatus: int
{
    case ADMIN_AUTOLOCK_UPDATED = 1;
    case ADMIN_LOCK_REQUESTED = 2;
    case ADMIN_UNLOCK_REQUESTED = 3;
    case DOORBELL_PRESSED = 4;
    case EXIT_REQUEST_DETECTED = 5;
    case EMERGENCY_EXIT_REQUEST_DETECTED = 6;
    case INVALID_CARD = 7;
    case INVALID_READER = 8;
    case INACTIVE_CARD = 9;
    case AREA_NOT_PERMITTED = 10;
    case AREA_DENIED = 11;
    case LOCK_COMMAND_FAILED = 12;
    case LOCK_LOCKED = 13;
    case LOCK_UNLOCKED = 14;
    case LOCK_STATE_RECONCILED = 15;
    case MQTT_AUTOLOCK_UPDATED = 16;
    case MQTT_TOGGLE_REQUESTED = 17;
    case MQTT_UNLOCK_REQUESTED = 18;
    case API_LOCK_REQUESTED = 19;
    case API_UNLOCK_REQUESTED = 20;
    case API_AUTOLOCK_UPDATED = 21;
    case READER_FEEDBACK_FAILED = 22;
    case SUCCESS = 23;

    public function key(): string
    {
        return match ($this) {
            self::ADMIN_AUTOLOCK_UPDATED => 'admin_autolock_updated',
            self::ADMIN_LOCK_REQUESTED => 'admin_lock_requested',
            self::ADMIN_UNLOCK_REQUESTED => 'admin_unlock_requested',
            self::DOORBELL_PRESSED => 'doorbell_pressed',
            self::EXIT_REQUEST_DETECTED => 'exit_request_detected',
            self::EMERGENCY_EXIT_REQUEST_DETECTED => 'emergency_exit_request_detected',
            self::INVALID_CARD => 'invalid_card',
            self::INVALID_READER => 'invalid_reader',
            self::INACTIVE_CARD => 'inactive_card',
            self::AREA_NOT_PERMITTED => 'area_not_permitted',
            self::AREA_DENIED => 'area_denied',
            self::LOCK_COMMAND_FAILED => 'lock_command_failed',
            self::LOCK_LOCKED => 'lock_locked',
            self::LOCK_UNLOCKED => 'lock_unlocked',
            self::LOCK_STATE_RECONCILED => 'lock_state_reconciled',
            self::MQTT_AUTOLOCK_UPDATED => 'mqtt_autolock_updated',
            self::MQTT_TOGGLE_REQUESTED => 'mqtt_toggle_requested',
            self::MQTT_UNLOCK_REQUESTED => 'mqtt_unlock_requested',
            self::API_LOCK_REQUESTED => 'api_lock_requested',
            self::API_UNLOCK_REQUESTED => 'api_unlock_requested',
            self::API_AUTOLOCK_UPDATED => 'api_autolock_updated',
            self::READER_FEEDBACK_FAILED => 'reader_feedback_failed',
            self::SUCCESS => 'success',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ADMIN_AUTOLOCK_UPDATED => 'Auto-Lock Updated (via Admin)',
            self::ADMIN_LOCK_REQUESTED => 'Lock Request (via Admin)',
            self::ADMIN_UNLOCK_REQUESTED => 'Unlock Request (via Admin)',
            self::DOORBELL_PRESSED => 'Doorbell Pressed',
            self::EXIT_REQUEST_DETECTED => 'Exit Request Detected',
            self::EMERGENCY_EXIT_REQUEST_DETECTED => 'Emergency Exit Request Detected',
            self::INVALID_CARD => 'Invalid Card',
            self::INVALID_READER => 'Invalid Reader',
            self::INACTIVE_CARD => 'Inactive Card',
            self::AREA_NOT_PERMITTED => 'No Area Permission',
            self::AREA_DENIED => 'Area Access Denied',
            self::LOCK_COMMAND_FAILED => 'Lock Command Failed',
            self::LOCK_LOCKED => 'Locked',
            self::LOCK_UNLOCKED => 'Unlocked',
            self::LOCK_STATE_RECONCILED => 'Lock State Reconciled',
            self::MQTT_AUTOLOCK_UPDATED => 'Auto-Lock Updated (via MQTT)',
            self::MQTT_TOGGLE_REQUESTED => 'Lock Toggle Request (via MQTT)',
            self::MQTT_UNLOCK_REQUESTED => 'Unlock Request (via MQTT)',
            self::API_LOCK_REQUESTED => 'Lock Request (via API)',
            self::API_UNLOCK_REQUESTED => 'Unlock Request (via API)',
            self::API_AUTOLOCK_UPDATED => 'Auto-Lock Updated (via API)',
            self::READER_FEEDBACK_FAILED => 'Reader Feedback Failed',
            self::SUCCESS => 'Credential Accepted',
        };
    }

    public static function fromStored(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_int($value) || (is_string($value) && is_numeric(trim($value)))) {
            return self::tryFrom((int) $value);
        }

        if (! is_scalar($value)) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        foreach (self::cases() as $case) {
            if ($normalized === $case->key()) {
                return $case;
            }
        }

        return null;
    }

    public static function normalizeValue(mixed $value): ?int
    {
        return self::fromStored($value)?->value;
    }

    public static function keyFor(mixed $value): ?string
    {
        return self::fromStored($value)?->key();
    }

    public static function labelFor(mixed $value): string
    {
        $status = self::fromStored($value);

        if ($status instanceof self) {
            return $status->label();
        }

        if (is_scalar($value)) {
            $raw = trim((string) $value);

            if ($raw !== '') {
                return $raw;
            }
        }

        return 'Unknown';
    }
}
