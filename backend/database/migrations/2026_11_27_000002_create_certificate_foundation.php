<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('certificate_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_template_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('background_path');
            $table->char('background_sha256', 64);
            $table->json('configuration');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['certificate_template_id', 'version'], 'certificate_template_version_unique');
        });

        Schema::create('certificate_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->char('request_hash', 64);
            $table->foreignId('training_program_id')->constrained()->restrictOnDelete();
            $table->foreignId('certificate_template_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at');
            $table->date('issue_date');
            $table->timestamps();
        });

        Schema::create('certificate_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedBigInteger('last_number');
        });

        Schema::create('certificate_name_windows', function (Blueprint $table) {
            $table->foreignId('training_program_id')->primary()->constrained()->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
        });

        Schema::create('certificate_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainee_id')->constrained()->restrictOnDelete();
            $table->foreignId('training_program_id')->constrained()->restrictOnDelete();
            $table->string('source', 16);
            $table->string('name', 200);
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['trainee_id', 'training_program_id', 'source'], 'certificate_name_source_unique');
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->text('path')->nullable()->change();
            $table->string('state', 16)->default('issued');
            $table->string('number', 32)->nullable()->unique();
            $table->foreignId('certificate_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('certificate_template_version_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('snapshot')->nullable();
            $table->unsignedInteger('generation_attempts')->default(0);
            $table->uuid('attempt_token')->nullable();
            $table->timestamp('generation_started_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->index(['training_program_id', 'state', 'id'], 'certificate_program_state_index');
        });

        Schema::create('certificate_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainee_id')->constrained()->restrictOnDelete();
            $table->foreignId('training_program_id')->constrained()->restrictOnDelete();
            $table->foreignId('certificate_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 24);
            $table->text('reason')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index(['training_program_id', 'trainee_id', 'id'], 'certificate_events_roster_index');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Certificate foundation is forward-only. Roll back application code with the schema in place.');
    }
};
