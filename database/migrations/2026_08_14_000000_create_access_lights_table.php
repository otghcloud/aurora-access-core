<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_lights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('access_areas')->cascadeOnDelete();
            $table->string('name');
            $table->string('identifier')->unique();
            $table->boolean('state')->default(false);
            $table->unsignedTinyInteger('brightness')->default(100);
            $table->string('color')->default('#ffffff');
            $table->json('config')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('area_id');
            $table->index('identifier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_lights');
    }
};
