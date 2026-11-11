<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\ApplicationStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('application_assigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')
            ->ondelete('cascade');
            $table->foreignId('trainer_id')->constrained('trainers')
                ->ondelete('cascade');
            $table->string('status')->default(ApplicationStatus::ASSIGNED);
            $table->unique(['trainer_id', 'application_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_assigns');
    }
};
