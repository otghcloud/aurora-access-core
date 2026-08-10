<?php

namespace App\Enums\AccessControl;

enum AccessBindingActionKey: int
{
    case LOCK_POWER = 1;
    case READER_FEEDBACK_STATE = 2;
    case EXIT_REQUEST = 3;
    case DOORBELL = 4;
    case EMERGENCY_EXIT_REQUEST = 5;

    public function key(): string
    {
        return match ($this) {
            self::LOCK_POWER => 'LockPower',
            self::READER_FEEDBACK_STATE => 'ReaderFeedbackState',
            self::EXIT_REQUEST => 'ExitRequest',
            self::DOORBELL => 'Doorbell',
            self::EMERGENCY_EXIT_REQUEST => 'EmergencyExitRequest',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::LOCK_POWER => 'Lock Power',
            self::READER_FEEDBACK_STATE => 'Reader Feedback State',
            self::EXIT_REQUEST => 'Exit Request',
            self::DOORBELL => 'Doorbell',
            self::EMERGENCY_EXIT_REQUEST => 'Emergency Exit Request',
        };
    }

    public function isInputAction(): bool
    {
        return in_array($this, [
            self::EXIT_REQUEST,
            self::DOORBELL,
            self::EMERGENCY_EXIT_REQUEST,
        ], true);
    }

    public function isOutputAction(): bool
    {
        return in_array($this, [
            self::LOCK_POWER,
            self::READER_FEEDBACK_STATE,
        ], true);
    }

    /**
     * @return array<int|string>
     */
    public function queryCandidates(): array
    {
        return array_values(array_unique([
            $this->value,
            $this->key(),
            strtolower($this->key()),
        ], SORT_REGULAR));
    }

    /**
     * @return array<int,array{value:int,key:string,label:string}>
     */
    public static function options(?string $direction = null): array
    {
        $cases = self::cases();

        if ($direction === 'input') {
            $cases = array_values(array_filter($cases, fn (self $case): bool => $case->isInputAction()));
        }

        if ($direction === 'output') {
            $cases = array_values(array_filter($cases, fn (self $case): bool => $case->isOutputAction()));
        }

        return array_map(static fn (self $case): array => [
            'value' => $case->value,
            'key' => $case->key(),
            'label' => $case->label(),
        ], $cases);
    }

    /**
     * @param  array<self>  $actions
     * @return array<int|string>
     */
    public static function queryCandidatesFor(array $actions): array
    {
        $values = [];

        foreach ($actions as $action) {
            foreach ($action->queryCandidates() as $candidate) {
                $values[] = $candidate;
            }
        }

        return array_values(array_unique($values, SORT_REGULAR));
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

        return match ($normalized) {
            'lockpower' => self::LOCK_POWER,
            'readerfeedbackstate' => self::READER_FEEDBACK_STATE,
            'exitrequest' => self::EXIT_REQUEST,
            'doorbell' => self::DOORBELL,
            'emergencyexitrequest' => self::EMERGENCY_EXIT_REQUEST,
            default => null,
        };
    }

    public static function normalizeValue(mixed $value): ?int
    {
        return self::fromStored($value)?->value;
    }

    public static function keyFor(mixed $value): ?string
    {
        return self::fromStored($value)?->key();
    }
}
