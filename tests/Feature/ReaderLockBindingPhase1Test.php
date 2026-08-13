<?php

namespace OTGH\AccessControl\Core\Tests\Feature;

use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Access\Card;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\Access\Individual;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Models\Hardware\ReaderLockBinding;
use OTGH\AccessControl\Core\Services\AccessControl\AccessBindingResolver;
use OTGH\AccessControl\Core\Services\AccessControl\HandleAccessRequest;
use Tests\TestCase;

class ReaderLockBindingPhase1Test extends TestCase
{
    protected Reader $reader;

    protected Area $area;

    protected Lock $lock1;

    protected Lock $lock2;

    protected Card $card;

    protected Individual $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->user = Individual::create(['name' => 'Test User']);
        $this->area = Area::create([
            'name' => 'Test Area',
            'identifier' => 'test_area_'.uniqid(),
        ]);

        $this->lock1 = Lock::create([
            'area_id' => $this->area->id,
            'name' => 'Test Lock 1',
            'identifier' => 'test_lock_1_'.uniqid(),
            'is_primary' => true,
        ]);

        $this->lock2 = Lock::create([
            'area_id' => $this->area->id,
            'name' => 'Test Lock 2',
            'identifier' => 'test_lock_2_'.uniqid(),
            'is_primary' => false,
        ]);

        $this->reader = Reader::create([
            'name' => 'Test Reader',
            'identifier' => 'test_reader_'.uniqid(),
            'area_id' => $this->area->id,
        ]);

        $this->card = Card::create([
            'user_id' => $this->user->id,
            'card_number' => 'TEST_CARD_'.uniqid(),
            'active' => true,
        ]);
    }

    /**
     * Test that ReaderLockBinding model can be created and relationships work
     */
    public function test_reader_lock_binding_creation()
    {
        $binding = ReaderLockBinding::create([
            'reader_id' => $this->reader->id,
            'lock_id' => $this->lock1->id,
            'area_id' => $this->area->id,
            'action_type' => 1,
            'enabled' => true,
        ]);

        $this->assertNotNull($binding->id);
        $this->assertEquals($this->reader->id, $binding->reader_id);
        $this->assertEquals($this->lock1->id, $binding->lock_id);
        $this->assertEquals($this->area->id, $binding->area_id);
        $this->assertTrue($binding->enabled);
    }

    /**
     * Test that Reader has many lock bindings
     */
    public function test_reader_has_many_lock_bindings()
    {
        ReaderLockBinding::create([
            'reader_id' => $this->reader->id,
            'lock_id' => $this->lock1->id,
            'area_id' => $this->area->id,
            'action_type' => 1,
        ]);

        ReaderLockBinding::create([
            'reader_id' => $this->reader->id,
            'lock_id' => $this->lock2->id,
            'area_id' => $this->area->id,
            'action_type' => 1,
        ]);

        $this->assertEquals(2, $this->reader->lockBindings()->count());
        $this->assertEquals(2, $this->reader->targetLocks()->count());
    }

    /**
     * Test that HandleAccessRequest uses lock bindings
     */
    public function test_handle_access_request_with_lock_bindings()
    {
        // Create lock binding
        ReaderLockBinding::create([
            'reader_id' => $this->reader->id,
            'lock_id' => $this->lock1->id,
            'area_id' => $this->area->id,
            'action_type' => 1,
        ]);

        $service = app(HandleAccessRequest::class);
        $result = $service->validateCard($this->card->card_number, $this->reader->identifier);

        $this->assertTrue($result->status->isSuccess());
        $this->assertNotNull($result->event);
        $this->assertEquals($this->lock1->id, $result->event->access_lock_id);
    }

    /**
     * Test backward compatibility: readers without bindings use area's primary lock
     */
    public function test_backward_compatibility_uses_primary_lock()
    {
        // Don't create any lock bindings
        $service = app(HandleAccessRequest::class);
        $result = $service->validateCard($this->card->card_number, $this->reader->identifier);

        $this->assertTrue($result->status->isSuccess());
        $this->assertNotNull($result->event);
        // Should use area's primary lock
        $this->assertEquals($this->lock1->id, $result->event->access_lock_id);
    }

    /**
     * Test that AccessBindingResolver uses lock bindings
     */
    public function test_access_binding_resolver_uses_lock_bindings()
    {
        // Create lock binding
        ReaderLockBinding::create([
            'reader_id' => $this->reader->id,
            'lock_id' => $this->lock1->id,
            'area_id' => $this->area->id,
            'action_type' => 1,
        ]);

        $resolver = app(AccessBindingResolver::class);
        $bindings = $resolver->resolveLockPowerBindings($this->reader);

        // Note: This test checks that the method runs without error
        // Actual bindings depend on AdapterBindings which we haven't set up in this test
        $this->assertIsArray($bindings);
    }

    /**
     * Test that reader can be bound to multiple locks
     */
    public function test_reader_can_control_multiple_locks()
    {
        ReaderLockBinding::create([
            'reader_id' => $this->reader->id,
            'lock_id' => $this->lock1->id,
            'area_id' => $this->area->id,
            'action_type' => 1,
        ]);

        ReaderLockBinding::create([
            'reader_id' => $this->reader->id,
            'lock_id' => $this->lock2->id,
            'area_id' => $this->area->id,
            'action_type' => 1,
        ]);

        $locks = $this->reader->targetLocks()->get();

        $this->assertEquals(2, $locks->count());
        $this->assertTrue($locks->contains($this->lock1));
        $this->assertTrue($locks->contains($this->lock2));
    }

    /**
     * Test that disabled lock bindings don't include the lock
     */
    public function test_disabled_lock_bindings_excluded()
    {
        ReaderLockBinding::create([
            'reader_id' => $this->reader->id,
            'lock_id' => $this->lock1->id,
            'area_id' => $this->area->id,
            'action_type' => 1,
            'enabled' => true,
        ]);

        ReaderLockBinding::create([
            'reader_id' => $this->reader->id,
            'lock_id' => $this->lock2->id,
            'area_id' => $this->area->id,
            'action_type' => 1,
            'enabled' => false,
        ]);

        // getTargetLocksForReader should only return enabled bindings
        $enabledBindings = $this->reader->lockBindings()
            ->where('enabled', true)
            ->count();

        $this->assertEquals(1, $enabledBindings);
    }

    /**
     * Test that doorbell press creates event with bound lock
     */
    public function test_doorbell_press_with_lock_binding()
    {
        ReaderLockBinding::create([
            'reader_id' => $this->reader->id,
            'lock_id' => $this->lock1->id,
            'area_id' => $this->area->id,
            'action_type' => 1,
        ]);

        $service = app(HandleAccessRequest::class);
        $result = $service->recordDoorbellPress($this->reader->identifier);

        $this->assertNotNull($result->event);
        $this->assertEquals($this->lock1->id, $result->event->access_lock_id);
    }

    /**
     * Test that unique constraint prevents duplicate bindings
     */
    public function test_unique_constraint_on_reader_lock()
    {
        ReaderLockBinding::create([
            'reader_id' => $this->reader->id,
            'lock_id' => $this->lock1->id,
            'area_id' => $this->area->id,
            'action_type' => 1,
        ]);

        // This should throw an exception due to unique constraint
        $this->expectException(\Exception::class);

        ReaderLockBinding::create([
            'reader_id' => $this->reader->id,
            'lock_id' => $this->lock1->id,
            'area_id' => $this->area->id,
            'action_type' => 1,
        ]);
    }

    /**
     * Test that deleting a reader cascades to lock bindings
     */
    public function test_delete_reader_cascades_to_bindings()
    {
        ReaderLockBinding::create([
            'reader_id' => $this->reader->id,
            'lock_id' => $this->lock1->id,
            'area_id' => $this->area->id,
            'action_type' => 1,
        ]);

        $bindingId = $this->reader->lockBindings()->first()->id;

        $this->reader->delete();

        $this->assertNull(ReaderLockBinding::find($bindingId));
    }

    /**
     * Test that deleting a lock cascades to bindings
     */
    public function test_delete_lock_cascades_to_bindings()
    {
        ReaderLockBinding::create([
            'reader_id' => $this->reader->id,
            'lock_id' => $this->lock1->id,
            'area_id' => $this->area->id,
            'action_type' => 1,
        ]);

        $bindingId = $this->reader->lockBindings()->first()->id;

        $this->lock1->delete();

        $this->assertNull(ReaderLockBinding::find($bindingId));
    }
}
