<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('identifier')->unique();
            $table->json('config')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('individuals', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('individuals')->cascadeOnDelete();
            $table->string('card_number')->unique();
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('readers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('identifier')->unique();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->json('config')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('locks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->string('name');
            $table->string('identifier')->unique();
            $table->boolean('is_primary')->default(false);
            $table->json('config')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['area_id', 'is_primary']);
        });

        Schema::create('switches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->string('name');
            $table->string('identifier')->unique();
            $table->json('config')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sources', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('identifier')->unique();
            $table->string('type');
            $table->string('endpoint')->nullable();
            $table->boolean('enabled')->default(true);
            $table->json('config')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('adapter_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->string('direction');
            $table->string('adapter_type');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->tinyInteger('action_key');
            $table->string('channel')->nullable();
            $table->boolean('signal_reversed')->default(false);
            $table->boolean('enabled')->default(true);
            $table->json('config')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['target_type', 'target_id'], 'bindings_target_idx');
            $table->index(['direction', 'adapter_type'], 'bindings_direction_adapter_idx');
            $table->index('action_key');
            $table->index('signal_reversed');
        });

        Schema::create('area_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('individual_id')->constrained('individuals')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->string('permission')->default('allow');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['individual_id', 'area_id'], 'area_permission_lookup_idx');
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('access_card_id')->nullable()->constrained('cards')->nullOnDelete();
            $table->foreignId('access_area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('access_lock_id')->nullable()->constrained('locks')->nullOnDelete();
            $table->foreignId('access_source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('individuals')->nullOnDelete();
            $table->string('card_number')->nullable();
            $table->string('origin_type')->nullable();
            $table->unsignedBigInteger('origin_id')->nullable();
            $table->string('origin_label')->nullable();
            $table->boolean('granted')->default(false);
            $table->tinyInteger('status')->nullable();
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['origin_type', 'origin_id'], 'events_origin_index');
            $table->index('access_area_id');
            $table->index('access_lock_id');
            $table->index('access_source_id');
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('events');
        Schema::dropIfExists('area_permissions');
        Schema::dropIfExists('adapter_bindings');
        Schema::dropIfExists('sources');
        Schema::dropIfExists('switches');
        Schema::dropIfExists('locks');
        Schema::dropIfExists('readers');
        Schema::dropIfExists('cards');
        Schema::dropIfExists('individuals');
        Schema::dropIfExists('areas');
    }
};
