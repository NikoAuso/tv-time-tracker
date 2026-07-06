<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tmdb_id')->nullable()->unique();
            $table->unsignedBigInteger('tvdb_id')->nullable()->index();
            $table->string('name');
            $table->string('poster_path')->nullable();
            $table->text('overview')->nullable();
            $table->date('first_air_date')->nullable();
            $table->unsignedInteger('total_episodes')->nullable();
            $table->string('status')->nullable();
            $table->json('genres')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shows');
    }
};
