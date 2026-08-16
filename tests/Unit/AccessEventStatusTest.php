<?php

namespace OTGH\AccessControl\Core\Tests\Unit;

use OTGH\AccessControl\Core\Enums\AccessControl\AccessEventStatus;
use PHPUnit\Framework\TestCase;

class AccessEventStatusTest extends TestCase
{
    public function test_home_assistant_autolock_status_round_trips(): void
    {
        $status = AccessEventStatus::HA_AUTOLOCK_UPDATED;

        $this->assertSame('ha_autolock_updated', $status->key());
        $this->assertSame($status, AccessEventStatus::fromStored('ha_autolock_updated'));
        $this->assertSame($status->value, AccessEventStatus::normalizeValue('ha_autolock_updated'));
        $this->assertSame('Auto-Lock Updated (via Home Assistant)', $status->label());
    }
}
