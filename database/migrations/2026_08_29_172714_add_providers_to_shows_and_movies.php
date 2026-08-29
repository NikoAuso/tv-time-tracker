<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table): void {
            $table->json('providers')->nullable();
            $table->timestamp('providers_synced_at')->nullable();
        });

        Schema::table('movies', function (Blueprint $table): void {
            $table->json('providers')->nullable();
            $table->timestamp('providers_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table): void {
            $table->dropColumn(['providers', 'providers_synced_at']);
        });

        Schema::table('movies', function (Blueprint $table): void {
            $table->dropColumn(['providers', 'providers_synced_at']);
        });
    }
};
