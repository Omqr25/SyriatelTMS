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
        Schema::create('reevaluation_requests', function (Blueprint $table) {
            $table->id();
            $table->text('reason');
            $table->string('status');
            $table->text('decision_note');
            $table->foreignId('trainee_id')
                ->constrained('trainees')
                ->cascadeOnDelete();
            $table->foreignId('trainer_id')
                ->nullable()
                ->constrained('trainers')
                ->nullOnDelete();
            $table->foreignId('evaluation_id')->nullable()
                ->constrained('evaluations')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reevaluation_requests');
    }
};
