<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('certificates')->select('trainee_id', 'training_program_id')
            ->selectRaw('COUNT(*) AS copies')->groupBy('trainee_id', 'training_program_id')
            ->havingRaw('COUNT(*) > 1')->orderBy('trainee_id')->orderBy('training_program_id')->limit(20)->get();

        $orphans = DB::table('certificates as c')->leftJoin('trainees as t', 't.id', '=', 'c.trainee_id')
            ->leftJoin('training_programs as p', 'p.id', '=', 'c.training_program_id')
            ->where(fn ($query) => $query->whereNull('t.id')->orWhereNull('p.id'))
            ->orderBy('c.id')->limit(20)->pluck('c.id');

        if ($duplicates->isNotEmpty() || $orphans->isNotEmpty()) {
            throw new RuntimeException('Certificate archive preflight failed. Review duplicate pairs (up to 20): '
                .$duplicates->toJson().'; orphan certificate IDs (up to 20): '.$orphans->toJson()
                .'. No records were changed.');
        }

        if (DB::getDriverName() === 'mysql') {
            // One atomic DDL on MySQL 8, so the archive is never left without its trainee FK.
            DB::statement('ALTER TABLE certificates DROP FOREIGN KEY certificates_trainee_id_foreign, '
                .'ADD CONSTRAINT certificates_trainee_archive_fk FOREIGN KEY (trainee_id) REFERENCES trainees(id) ON DELETE RESTRICT, '
                .'ADD UNIQUE INDEX certificates_trainee_program_unique (trainee_id, training_program_id)');

            return;
        }

        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['trainee_id']);
            $table->foreign('trainee_id', 'certificates_trainee_archive_fk')->references('id')->on('trainees')->restrictOnDelete();
            $table->unique(['trainee_id', 'training_program_id'], 'certificates_trainee_program_unique');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Certificate archive safeguards are forward-only. Roll back application code with the schema in place.');
    }
};
