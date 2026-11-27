<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('training_program_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('format', 3);
            $table->string('status', 12);
            $table->json('certificate_ids');
            $table->string('input_hash', 64);
            $table->unsignedSmallInteger('total');
            $table->unsignedSmallInteger('processed')->default(0);
            $table->unsignedInteger('dispatch')->default(1);
            $table->uuid('attempt')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->unsignedBigInteger('failed_certificate_id')->nullable();
            $table->string('failure_code', 32)->nullable();
            $table->timestamps();
            $table->index(['requested_by', 'status']);
            $table->index(['requested_by', 'training_program_id', 'created_at'], 'certificate_package_requester_program');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Keep the certificate package records and private storage for expiry cleanup when rolling back code.');
    }
};
