<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL auto-commits. Pause absence writes while this migration runs.
        Schema::table('absences', function (Blueprint $table) {
            $table->boolean('is_canonical')->nullable()->default(true);
        });

        // Keep all historical records and their references. Only legacy duplicates receive NULL.
        DB::table('absences')->select('trainee_id', 'date')->selectRaw('MIN(id) AS first_id')
            ->groupBy('trainee_id', 'date')->havingRaw('COUNT(*) > 1')
            ->orderBy('trainee_id')->orderBy('date')
            ->chunk(500, function ($groups) {
                foreach ($groups as $group) {
                    DB::table('absences')->where('trainee_id', $group->trainee_id)
                        ->where('date', $group->date)->where('id', '!=', $group->first_id)
                        ->update(['is_canonical' => null]);
                }
            });

        Schema::table('absences', function (Blueprint $table) {
            $table->unique(['trainee_id', 'date', 'is_canonical'], 'absences_daily_unique');
        });
    }

    public function down(): void
    {
        // InnoDB may replace its automatically created foreign-key index with the composite index.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)
            && ! Schema::hasIndex('absences', 'absences_trainee_id_foreign')) {
            Schema::table('absences', function (Blueprint $table) {
                $table->index('trainee_id', 'absences_trainee_id_foreign');
            });
        }

        Schema::table('absences', function (Blueprint $table) {
            $table->dropUnique('absences_daily_unique');
        });

        Schema::table('absences', function (Blueprint $table) {
            $table->dropColumn('is_canonical');
        });
    }
};
