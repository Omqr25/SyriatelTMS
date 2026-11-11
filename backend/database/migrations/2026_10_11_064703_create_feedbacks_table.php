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
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_id')
                ->constrained('evidences')
                ->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('trainers')
                ->cascadeOnDelete();
            $table->text('feedback')->nullable();
            $table->string('status')->default(\App\Enums\TaskStatus::NeedFix->value);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
