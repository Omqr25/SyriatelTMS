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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('national_id_number',11)->unique();
            $table->foreignId('training_program_id')
                ->constrained('training_programs')
                ->restrictOnDelete();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone_number');
            $table->date('birth_date')->nullable();
            $table->string('city');
            $table->string('home_address');
            $table->string('university');
            $table->string('field_of_study');
            $table->string('academic_major');
            $table->string('year_of_study');
            $table->decimal('gpa', 5, 2)->nullable();
            $table->text('technical_skills')->nullable();
            $table->text('academic_qualification')->nullable();
            $table->text('cv');
            $table->text('national_id');
            $table->text('personal_photo');
            $table->boolean('commitment')->default(false);
            $table->boolean('previous_training')->default(false);
            $table->string('how_heard')->nullable();
            $table->text('additional_info')->nullable();
            $table->string('status')->default(\App\Enums\ApplicationStatus::PENDING->value);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
