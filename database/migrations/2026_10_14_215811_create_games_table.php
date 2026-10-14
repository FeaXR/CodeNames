<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('spymaster_token', 40);
            $table->string('language', 8);
            $table->json('cards');
            $table->string('starting_team', 8);
            $table->string('current_team', 8);
            $table->string('status', 16)->default('playing');
            $table->string('winner', 8)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
