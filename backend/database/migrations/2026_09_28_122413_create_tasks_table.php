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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            $table->dateTime('deadline');
            $table->integer('priority');
            $table->text('instructions')->nullable();
            $table->foreignId('trainee_id')
                ->constrained('trainees')
                ->cascadeOnDelete();
            $table->foreignId('milestone_id')
                ->constrained('milestones')
                ->cascadeOnDelete();
            $table->string('status')->default(\App\Enums\TaskStatus::PENDING->value);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
