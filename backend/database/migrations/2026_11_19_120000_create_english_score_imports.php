<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->decimal('english_test_score', 5, 2)->nullable();
        });

        Schema::create('english_score_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('program_name');
            $table->string('uploader_name');
            $table->string('file_name');
            $table->json('summary');
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('english_score_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('english_score_import_id')->constrained()->cascadeOnDelete();
            // Keep the original match in the audit even if the application is deleted.
            $table->unsignedBigInteger('application_id')->nullable();
            $table->unsignedInteger('row_number');
            $table->mediumText('email');
            $table->mediumText('score_input');
            $table->decimal('score', 5, 2)->nullable();
            $table->decimal('previous_score', 5, 2)->nullable();
            $table->string('status', 32);
            $table->string('reason', 32)->nullable();
            $table->unique(['english_score_import_id', 'row_number'], 'english_score_import_row_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('english_score_import_rows');
        Schema::dropIfExists('english_score_imports');
        Schema::table('applications', fn (Blueprint $table) => $table->dropColumn('english_test_score'));
    }
};
