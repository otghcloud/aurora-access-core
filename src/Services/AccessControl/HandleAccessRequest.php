<?php

namespace OTGH\AccessControl\Core\Services\AccessControl;

use Illuminate\Database\Eloquent\Collection;
use OTGH\AccessControl\Core\Enums\AccessControl\AccessEventStatus;
use OTGH\AccessControl\Core\Jobs\ProcessReaderEvent;
use OTGH\AccessControl\Core\Jobs\PulseReaderFeedbackState;
use OTGH\AccessControl\Core\Models\Access\AreaPermission;
use OTGH\AccessControl\Core\Models\Access\Card;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Models\Hardware\ReaderLockBinding;

class HandleAccessRequest
{
    public function validateCard(string|int $cardNumber, string $readerIdentifier, ?string $ipAddress = null): AccessRequestResult
    {
        $normalizedCardNumber = (string) $cardNumber;

        info("Validating card: {$normalizedCardNumber} for reader: {$readerIdentifier}");

        $accessCard = Card::query()
            ->where('card_number', $normalizedCardNumber)
            ->first();
        $accessReader = Reader::query()
            ->where('identifier', $readerIdentifier)
            ->first();

        $reason = 'Card not found.';
        $status = AccessEventStatus::INVALID_CARD;

        if (! $accessReader) {
            $status = AccessEventStatus::INVALID_READER;
            $reason = 'Reader not found.';
        } elseif (! $accessCard) {
            $status = AccessEventStatus::INVALID_CARD;
            $reason = 'Card not found.';
        } elseif (! $accessCard->active) {
            $status = AccessEventStatus::INACTIVE_CARD;
            $reason = 'Card is inactive.';
        } elseif (! $this->isUserAllowedForReader($accessCard, $accessReader, $status, $reason)) {
        } else {
            $status = AccessEventStatus::SUCCESS;
            $reason = null;
        }

        // Get target locks from reader bindings, with fallback to area's primary lock
        $targetLocks = $this->getTargetLocksForReader($accessReader);

        // If we have target locks, create an event for each; otherwise create one event with no lock
        if ($targetLocks->isNotEmpty()) {
            $events = [];
            foreach ($targetLocks as $targetLock) {
                $event = Event::create([
                    'access_card_id' => $accessCard?->id,
                    'access_area_id' => $accessReader?->area_id,
                    'access_lock_id' => $targetLock->id,
                    'user_id' => $accessCard?->user_id,
                    'card_number' => $normalizedCardNumber,
                    'origin_type' => 'reader',
                    'origin_id' => $accessReader?->id,
                    'origin_label' => $accessReader?->name ?? $readerIdentifier,
                    'granted' => $status === AccessEventStatus::SUCCESS,
                    'status' => $status,
                    'reason' => $reason,
                    'metadata' => null,
                    'ip_address' => $ipAddress,
                ]);
                $events[] = $event;
            }
            // Use the first event as the primary event for the response
            $event = $events[0];
        } else {
            $event = Event::create([
                'access_card_id' => $accessCard?->id,
                'access_area_id' => $accessReader?->area_id,
                'access_lock_id' => null,
                'user_id' => $accessCard?->user_id,
                'card_number' => $normalizedCardNumber,
                'origin_type' => 'reader',
                'origin_id' => $accessReader?->id,
                'origin_label' => $accessReader?->name ?? $readerIdentifier,
                'granted' => $status === AccessEventStatus::SUCCESS,
                'status' => $status,
                'reason' => $reason,
                'metadata' => null,
                'ip_address' => $ipAddress,
            ]);
        }

        if ($status === AccessEventStatus::SUCCESS) {
            ProcessReaderEvent::dispatch($accessCard, $accessReader);
            PulseReaderFeedbackState::dispatch($accessReader, 1, 'access_granted');
        }

        return new AccessRequestResult($status, $reason, $accessCard, $accessReader, $event);
    }

    public function recordDoorbellPress(string $readerIdentifier, ?string $ipAddress = null, array $metadata = ['source' => 'physical_button']): AccessRequestResult
    {
        $accessReader = Reader::query()
            ->where('identifier', $readerIdentifier)
            ->first();

        // Get target locks from reader bindings, with fallback to area's primary lock
        $targetLocks = $this->getTargetLocksForReader($accessReader);

        if ($targetLocks->isNotEmpty()) {
            $events = [];
            foreach ($targetLocks as $targetLock) {
                $event = Event::create([
                    'access_card_id' => null,
                    'access_area_id' => $accessReader?->area_id,
                    'access_lock_id' => $targetLock->id,
                    'user_id' => null,
                    'card_number' => null,
                    'origin_type' => 'reader',
                    'origin_id' => $accessReader?->id,
                    'origin_label' => $accessReader?->name ?? $readerIdentifier,
                    'granted' => true,
                    'status' => AccessEventStatus::DOORBELL_PRESSED,
                    'reason' => null,
                    'metadata' => $metadata,
                    'ip_address' => $ipAddress,
                ]);
                $events[] = $event;
            }
            // Use the first event as the primary event for the response
            $event = $events[0];
        } else {
            $event = Event::create([
                'access_card_id' => null,
                'access_area_id' => $accessReader?->area_id,
                'access_lock_id' => null,
                'user_id' => null,
                'card_number' => null,
                'origin_type' => 'reader',
                'origin_id' => $accessReader?->id,
                'origin_label' => $accessReader?->name ?? $readerIdentifier,
                'granted' => true,
                'status' => AccessEventStatus::DOORBELL_PRESSED,
                'reason' => null,
                'metadata' => $metadata,
                'ip_address' => $ipAddress,
            ]);
        }

        return new AccessRequestResult(AccessEventStatus::DOORBELL_PRESSED, null, null, $accessReader, $event);
    }

    /**
     * Get target locks for a reader using ReaderLockBinding, with fallback to area's primary lock.
     *
     * @return Collection<int, Lock>
     */
    private function getTargetLocksForReader(?Reader $reader): Collection
    {
        if (! $reader) {
            return new Collection;
        }

        // First, try to get locks from reader bindings
        $lockBindings = $reader->lockBindings()
            ->where('enabled', true)
            ->get();

        if ($lockBindings->isNotEmpty()) {
            return new Collection($lockBindings->pluck('lock')->filter()->values()->all());
        }

        // Fallback to area's primary lock (backward compatibility)
        $primaryLock = $reader->area?->primaryLock();

        return $primaryLock ? new Collection([$primaryLock]) : new Collection;
    }

    private function isUserAllowedForReader(Card $accessCard, Reader $accessReader, AccessEventStatus &$status, ?string &$reason): bool
    {
        $roomId = (int) ($accessReader->area_id ?? 0);
        $accessUserId = (int) ($accessCard->user_id ?? 0);

        if ($roomId <= 0 || $accessUserId <= 0) {
            return true;
        }

        $userPermissionCount = AreaPermission::query()
            ->where('individual_id', $accessUserId)
            ->count('*');

        if ($userPermissionCount === 0) {
            return true;
        }

        $roomPermission = AreaPermission::query()
            ->where('individual_id', $accessUserId)
            ->where('area_id', $roomId)
            ->latest('id')
            ->first();

        if (! $roomPermission) {
            $status = AccessEventStatus::AREA_NOT_PERMITTED;
            $reason = 'No area permission exists for this user and area.';

            return false;
        }

        if (strtolower(trim((string) $roomPermission->permission)) !== 'allow') {
            $status = AccessEventStatus::AREA_DENIED;
            $reason = 'Area permission denies access.';

            return false;
        }

        return true;
    }
}
