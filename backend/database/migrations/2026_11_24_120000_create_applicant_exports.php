<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_exports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Retention is independent of program and user deletion.
            $table->unsignedBigInteger('program_id')->index();
            $table->unsignedBigInteger('created_by');
            $table->string('program_name');
            $table->unsignedInteger('applicant_count');
            $table->unsignedInteger('document_warnings')->default(0);
            $table->json('columns');
            $table->string('capture_state');
            $table->string('excel_state');
            $table->string('documents_state');
            $table->string('operation')->nullable();
            $table->string('next_operation')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::create('applicant_export_rows', function (Blueprint $table) {
            $table->foreignUlid('export_id')->constrained('applicant_exports')->cascadeOnDelete();
            $table->unsignedBigInteger('application_id');
            $table->json('fields');
            $table->json('documents')->nullable();
            $table->unsignedInteger('part')->nullable();
            $table->primary(['export_id', 'application_id']);
            $table->index(['export_id', 'part', 'application_id']);
        });

        Schema::create('applicant_export_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('export_id')->constrained('applicant_exports')->cascadeOnDelete();
            $table->string('kind');
            $table->unsignedInteger('part')->default(0);
            $table->string('state');
            $table->string('owner')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('lease_until')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('dispatch_after')->nullable();
            $table->unsignedBigInteger('queue_job_id')->nullable();
            $table->unsignedBigInteger('estimate')->default(0);
            $table->unsignedBigInteger('reservation')->default(0);
            $table->unsignedBigInteger('bytes')->nullable();
            $table->unsignedInteger('progress')->default(0);
            $table->string('file')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->string('error')->nullable();
            $table->timestamp('handoff_until')->nullable();
            $table->unique(['export_id', 'kind', 'part']);
            $table->index(['state', 'due_at', 'dispatch_after']);
        });

        Schema::create('applicant_export_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('applicant_export_capacity', fn (Blueprint $table) => $table->unsignedTinyInteger('id')->primary());
        DB::table('applicant_export_capacity')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_export_jobs');
        Schema::dropIfExists('applicant_export_tasks');
        Schema::dropIfExists('applicant_export_rows');
        Schema::dropIfExists('applicant_exports');
        Schema::dropIfExists('applicant_export_capacity');
    }
};
