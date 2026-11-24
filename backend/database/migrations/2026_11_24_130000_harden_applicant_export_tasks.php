<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicant_exports', function (Blueprint $table) {
            $table->uuid('request_key')->nullable();
            $table->unique(['program_id', 'request_key'], 'applicant_export_request_unique');
        });
        Schema::table('applicant_export_tasks', function (Blueprint $table) {
            $table->unsignedSmallInteger('lock_failures')->default(0);
            $table->unsignedBigInteger('capacity_required')->nullable();
            $table->unsignedBigInteger('capacity_available')->nullable();
        });
        Schema::table('applicant_export_capacity', fn (Blueprint $table) => $table->unsignedTinyInteger('excel_streak')->default(0));

        // Preserve requests that the old worker held behind document capture.
        DB::table('applicant_exports')->whereNotNull('next_operation')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::transaction(function () use ($row) {
                    if ($row->next_operation === 'excel') {
                        DB::table('applicant_export_tasks')->insertOrIgnore([
                            'export_id' => $row->id, 'kind' => 'excel', 'part' => 0, 'state' => 'queued',
                            'estimate' => 1024 ** 2 + $row->applicant_count * 32768, 'due_at' => now(),
                        ]);
                    }
                    DB::table('applicant_exports')->where('id', $row->id)->update([
                        $row->next_operation.'_state' => $row->next_operation === 'documents'
                            && in_array($row->capture_state, ['failed', 'cancelled'], true) ? $row->capture_state : 'queued',
                        'next_operation' => null,
                    ]);
                });
            }
        });
    }

    public function down(): void
    {
        Schema::table('applicant_exports', function (Blueprint $table) {
            $table->dropUnique('applicant_export_request_unique');
            $table->dropColumn('request_key');
        });
        Schema::table('applicant_export_tasks', fn (Blueprint $table) => $table->dropColumn(['lock_failures', 'capacity_required', 'capacity_available']));
        Schema::table('applicant_export_capacity', fn (Blueprint $table) => $table->dropColumn('excel_streak'));
    }
};
