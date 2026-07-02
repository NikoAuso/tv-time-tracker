<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tmdb_id')->nullable();
            $table->unsignedInteger('season_number');
            $table->unsignedInteger('episode_number');
            $table->string('name')->nullable();
            $table->text('overview')->nullable();
            $table->string('still_path')->nullable();
            $table->date('air_date')->nullable();
            $table->unsignedInteger('runtime')->nullable();
            $table->timestamps();

            $table->unique(['show_id', 'season_number', 'episode_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episodes');
    }
};
