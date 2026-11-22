<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('revision')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'training_program_id'], 'app_forms_intake_key');
        });

        Schema::create('form_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_form_id')->constrained()->cascadeOnDelete();
            $table->string('prompt');
            $table->string('type', 20);
            $table->boolean('required');
            $table->unsignedInteger('display_order');
            $table->decimal('min_value', 12, 4)->nullable();
            $table->decimal('max_value', 12, 4)->nullable();
            $table->timestamps();
            $table->unique(['id', 'application_form_id'], 'form_questions_form_key');
            $table->index(['application_form_id', 'display_order'], 'form_questions_display_idx');
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_question_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('display_order');
            $table->timestamps();
            $table->unique(['id', 'form_question_id'], 'question_options_question_key');
            $table->index(['form_question_id', 'display_order'], 'question_options_display_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('form_questions');
        Schema::dropIfExists('application_forms');
    }
};
