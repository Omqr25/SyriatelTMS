<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('vacation_requests', function (Blueprint $table) {
            $table->index(['status', 'trainee_id', 'start_date', 'end_date'], 'leave_status_trainee_period');
        });
    }

    public function down(): void
    {
        // Removing this column would make deleted records visible and bookable again.
        if (DB::table('absences')->whereNotNull('deleted_at')->exists()) {
            throw new RuntimeException('Retain or archive soft-deleted absences before rolling back.');
        }

        if (DB::table('vacation_requests')->where('status', 'expired')->exists()) {
            throw new RuntimeException('Keep the attendance compatibility schema while expired leave records exist.');
        }

        Schema::table('vacation_requests', function (Blueprint $table) {
            $table->dropIndex('leave_status_trainee_period');
        });

        Schema::table('absences', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
