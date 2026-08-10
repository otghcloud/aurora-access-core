<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->string('name');
            $table->string('identifier')->unique();
            $table->boolean('state')->default(false);
            $table->json('config')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['area_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensors');
    }
};
