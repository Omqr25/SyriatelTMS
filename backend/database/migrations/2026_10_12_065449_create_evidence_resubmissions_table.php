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
        Schema::create('evidence_resubmissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')
                ->unique()
                ->constrained('feedbacks')
                ->cascadeOnDelete();
            $table->foreignId('resubmitted_evidence_id')
                ->unique()
                ->constrained('evidences')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evidence_resubmissions');
    }
};
