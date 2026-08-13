<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reader_lock_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reader_id')->constrained('readers')->cascadeOnDelete();
            $table->foreignId('lock_id')->constrained('locks')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->tinyInteger('action_type')->default(1); // LOCK = 1, UNLOCK = 2, TOGGLE = 3, AUTOLOCK = 4
            $table->boolean('enabled')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['reader_id', 'lock_id'], 'bindings_reader_lock_idx');
            $table->index(['area_id', 'reader_id'], 'bindings_area_reader_idx');
            $table->unique(['reader_id', 'lock_id'], 'bindings_reader_lock_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reader_lock_bindings');
    }
};
