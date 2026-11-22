<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id');
            $table->foreignId('form_question_id');
            $table->foreignId('application_form_id');
            $table->text('text_value')->nullable();
            $table->decimal('numeric_value', 12, 4)->nullable();
            $table->timestamps();
            $table->unique(['application_id', 'form_question_id'], 'app_answers_once');
            $table->unique(['id', 'form_question_id'], 'app_answers_question_key');
            $table->index(['application_id', 'application_form_id'], 'app_answers_application_form_idx');
            $table->index(['form_question_id', 'application_form_id'], 'app_answers_question_form_idx');
            $table->foreign(['application_id', 'application_form_id'], 'app_answers_application_form_fk')
                ->references(['id', 'application_form_id'])->on('applications')->cascadeOnDelete();
            $table->foreign(['form_question_id', 'application_form_id'], 'app_answers_question_form_fk')
                ->references(['id', 'application_form_id'])->on('form_questions')->restrictOnDelete();
        });

        Schema::create('answer_options', function (Blueprint $table) {
            $table->foreignId('application_answer_id');
            $table->foreignId('question_option_id');
            $table->foreignId('form_question_id');
            $table->timestamps();
            $table->primary(['application_answer_id', 'question_option_id']);
            $table->index(['application_answer_id', 'form_question_id'], 'answer_options_answer_question_idx');
            $table->index(['question_option_id', 'form_question_id'], 'answer_options_option_question_idx');
            $table->foreign(['application_answer_id', 'form_question_id'], 'answer_options_answer_question_fk')
                ->references(['id', 'form_question_id'])->on('application_answers')->cascadeOnDelete();
            $table->foreign(['question_option_id', 'form_question_id'], 'answer_options_option_question_fk')
                ->references(['id', 'form_question_id'])->on('question_options')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_options');
        Schema::dropIfExists('application_answers');
    }
};
