<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'access_individual_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('access_individual_id')
                ->nullable()
                ->after('email')
                ->constrained('individuals')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'access_individual_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['access_individual_id']);
            $table->dropColumn('access_individual_id');
        });
    }
};
