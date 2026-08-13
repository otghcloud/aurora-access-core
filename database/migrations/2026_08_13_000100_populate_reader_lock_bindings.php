<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Hardware\Reader;

return new class extends Migration
{
    public function up(): void
    {
        // Populate reader_lock_bindings with existing reader-area-lock relationships
        // This ensures backward compatibility: each reader will continue to control its area's primary lock
        $readers = Reader::with('area')->get();

        foreach ($readers as $reader) {
            $area = $reader->area;
            if (! $area) {
                continue;
            }

            // Get the area's primary lock
            $primaryLock = $area->locks()
                ->where('is_primary', true)
                ->latest('id')
                ->first();

            // If no primary lock, use any lock in the area
            if (! $primaryLock) {
                $primaryLock = $area->locks()
                    ->latest('id')
                    ->first();
            }

            // If there's a lock, create a binding
            if ($primaryLock) {
                DB::table('reader_lock_bindings')->insertOrIgnore([
                    'reader_id' => $reader->id,
                    'lock_id' => $primaryLock->id,
                    'area_id' => $area->id,
                    'action_type' => 1, // LOCK
                    'enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // This migration is a data population migration - no rollback needed
        // The bindings table itself is removed by the create_reader_lock_bindings_table migration
    }
};
