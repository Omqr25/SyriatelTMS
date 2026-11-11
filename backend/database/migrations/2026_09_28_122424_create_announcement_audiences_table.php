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
        Schema::create('announcement_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')
                ->constrained('announcements')
                ->cascadeOnDelete();
            $table->foreignId('audience_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->boolean('read')->default(false);
            $table->unique([
                'announcement_id',
                'audience_id'
            ]);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_audiences');
    }
};
