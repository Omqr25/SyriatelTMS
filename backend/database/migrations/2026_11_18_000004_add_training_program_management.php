<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('training_program_admin')->default(false);
        });
        Schema::table('training_programs', function (Blueprint $table) {
            // NULL means the previous, editable schedule has no reliable history.
            $table->boolean('registration_has_opened')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedBigInteger('revision')->default(1);
        });
        DB::table('training_programs')
            ->whereDate('application_start_date', '<=', today())
            ->orWhere('status', 'Open')
            ->orWhereIn('id', DB::table('applications')->select('training_program_id'))
            ->update(['registration_has_opened' => true]);

        // Serialize calendar writes even when no program exists yet.
        Schema::create('training_program_calendar', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedBigInteger('revision')->default(0);
        });
        DB::table('training_program_calendar')->insert(['id' => 1]);

        Schema::create('training_program_audits', function (Blueprint $table) {
            $table->id();
            // Keep the audit trail after a program or staff account is deleted.
            $table->unsignedBigInteger('training_program_id')->index();
            $table->unsignedBigInteger('actor_id');
            $table->string('action', 16);
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_program_audits');
        Schema::dropIfExists('training_program_calendar');
        Schema::table('training_programs', function (Blueprint $table) {
            $table->dropColumn(['registration_has_opened', 'revision', 'archived_at']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('training_program_admin'));
    }
};
