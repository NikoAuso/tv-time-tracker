<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_shows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('show_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('following');
            $table->boolean('is_favorite')->default(false);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamp('followed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'show_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_shows');
    }
};
